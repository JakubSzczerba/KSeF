# Code Review — KSeF Project

**Reviewer:** Software Architect + Senior Developer perspective
**Date:** 2026-03-18
**Scope:** `src/`, `tests/`, `config/`, `Makefile`
**Stan:** PHPStan L8 ✅ | PHPUnit 11/11 ✅ | Dashboard działa ✅

---

## Po co jest `var/submitted_invoices.json`?

Służy jako **lokalny bufor** dla faktur wysłanych przez aplikację. Problem który rozwiązuje: po wysłaniu faktury KSeF API nie zwraca jej natychmiast w `GET /sessions`. Jest opóźnienie indeksowania — sesja może być jeszcze w trakcie przetwarzania. `GetInvoiceOverviewHandler::mergeWithLocalRows()` dołącza do listy z KSeF te faktury z JSON-a, których KSeF jeszcze nie pokazuje.

**Dlaczego to jest tymczasowe rozwiązanie:**
- Działa tylko na jednym serwerze (plik lokalny)
- Brak czyszczenia — plik rośnie bez ograniczeń
- Restart kontenera z `tmpfs` na `var/` — dane znikają
- Nie nadaje się do środowiska wieloinstancyjnego

**Właściwe rozwiązanie:** PostgreSQL (już w docker-compose) z tabelą `submitted_invoices`.

---

## 🔴 Krytyczne — wymagają naprawy przed produkcją

### 1. `AccessTokenStore` jest bezużyteczny między requestami

**Plik:** `src/Backend/Authentication/Application/AccessTokenStore.php`

```php
final class AccessTokenStore
{
    private ?AccessToken $accessToken = null; // in-memory only
}
```

PHP-FPM resetuje stan procesu po każdym requeście. Token przechowywany w polu klasy znika po zakończeniu żądania. **Każde żądanie do KSeF (wysyłka, lista, download) wykonuje pełny flow uwierzytelnienia od zera** — challenge → sign → init → poll → redeem. To 5 dodatkowych HTTP calls + do 60 sekund czekania przy każdym requeście użytkownika.

**Naprawa:** Przechowywać token w sesji Symfony, Redis, lub bazie danych z TTL.

---

### 2. Blokujący polling przez PHP-FPM (do 240 sekund)

**Plik:** `src/Backend/Invoice/Application/SendInvoiceHandler.php`

```
Invoice polling:  60 prób × 2s = 120 sekund max
Session polling:  30 prób × 2s =  60 sekund max
Łącznie:                         180 sekund max
```

Nginx ma domyślny `fastcgi_read_timeout` 60 sekund. Żądanie `POST /send` **zawsze kończy się timeoutem nginxa**, zanim invoice status będzie terminalny. Użytkownik widzi błąd połączenia, mimo że faktura faktycznie się przetworzyła.

Dodatkowo: `sleep()` blokuje cały worker PHP-FPM — brak możliwości obsługi innych żądań.

**Naprawa:** Symfony Messenger + async transport (Redis/RabbitMQ). `POST /send` → zwraca natychmiast jobId → frontend polluje status przez WebSocket lub SSE.

---

### 3. API URL jest hardcoded na środowisko testowe

**Plik:** `src/Backend/Shared/Infrastructure/Api/KsefApiClient.php`

```php
private const API_URL = 'https://api-test.ksef.mf.gov.pl/v2';
```

Przejście na produkcję wymaga zmiany kodu. Nie ma możliwości konfiguracji przez zmienną środowiskową.

**Naprawa:** Wstrzyknąć przez konstruktor z `config/services.yaml`:
```yaml
Ksef\Backend\Shared\Infrastructure\Api\KsefApiClient:
    arguments:
        $apiUrl: '%env(KSEF_API_URL)%'
```

---

## 🟠 Poważne — wpływają na jakość i niezawodność

### 4. `GetInvoiceRowsAction` — brak obsługi wyjątków

**Plik:** `src/Frontend/Dashboard/UI/Action/GetInvoiceRowsAction.php`

```php
public function __invoke(Request $request): JsonResponse
{
    return new JsonResponse([
        'ok' => true,
        'rows' => $this->getInvoiceOverviewHandler->provide(), // może rzucić wyjątek
    ]);
}
```

`provide()` robi pełne uwierzytelnienie + dziesiątki calls do KSeF. Każdy może rzucić `ApiClientException`, `AuthenticationFailedException`, itp. Brak `try/catch` oznacza że dashboard AJAX endpoint zwraca błąd 500 zamiast `{'ok': false, 'message': '...'}`. Frontend nie obsłuży tego poprawnie.

`SendInvoiceAction` ma poprawny `try/catch` — `GetInvoiceRowsAction` nie.

---

### 5. `GetInvoiceOverviewHandler` — potiencjalnie setki calls HTTP na jedno odświeżenie

**Plik:** `src/Frontend/Dashboard/Application/GetInvoiceOverview/GetInvoiceOverviewHandler.php`

```
Fetch Online sessions:  max 10 stron × 100 sesji = 1 000 sesji
Fetch Batch sessions:   max 10 stron × 100 sesji = 1 000 sesji
Per sesja: max 10 stron × 100 faktur = 1 000 faktur

Łącznie: do 20 calls dla sesji + do 20 000 calls dla faktur
```

Potem dla każdej faktury lokalnej (z JSON) nieobecnej w wynikach KSeF: **2 dodatkowe calls** (`getSessionInvoiceStatus` + `getSessionStatus`). Przy 50 fakturach lokalnych: 100 calls więcej.

Każde odświeżenie strony (`GET /invoices/rows`) uruchamia to od nowa. Zero cachowania.

**Naprawa:** Cache odpowiedzi (Redis TTL 30s), paginacja po stronie klienta, lazy loading sesji.

---

### 6. `SendInvoiceAction` wywołuje `provide()` po każdej wysyłce

**Plik:** `src/Frontend/Dashboard/UI/Action/SendInvoiceAction.php:68`

```php
'rows' => $this->getInvoiceOverviewHandler->provide(), // pełny refresh po wysyłce
```

Po wysłaniu faktury (które już zajmuje do 180 sekund) akcja wykonuje jeszcze jeden pełny fetch listy z KSeF. Podwaja czas odpowiedzi i ryzyko timeoutu.

**Naprawa:** Usunąć `rows` z odpowiedzi `POST /send` — frontend niech sam odświeży listę osobnym żądaniem.

---

### 7. `OpenSslInvoiceEncryptor` — szyfrowanie przez OpenSSL CLI

**Plik:** `src/Backend/Invoice/Infrastructure/Encryption/OpenSslInvoiceEncryptor.php`

RSA OAEP jest wykonywany przez wywołanie komendy shell (`openssl pkeyutl ...`). PHP ma natywne wsparcie RSA OAEP przez `openssl_public_encrypt()` z flagą `OPENSSL_PKCS1_OAEP_PADDING`. CLI jest kruchym obejściem — zależne od wersji OpenSSL w kontenerze, trudne do debugowania, potencjalnie wolniejsze.

**Naprawa:** Użyć `openssl_public_encrypt($data, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING)` bezpośrednio w PHP.

---

### 8. Brak logowania (PSR-3 Logger)

W całym projekcie nie ma ani jednego `$this->logger->...`. Gdy `SendInvoiceHandler` dostaje status 450 (błąd semantyki), `AuthenticateHandler` dostaje timeout, lub `KsefApiClient` dostaje odpowiedź 500 — jedynym śladem jest wyjątek propagowany do użytkownika. Brak możliwości śledzenia problemów na produkcji.

**Naprawa:** Wstrzyknąć `LoggerInterface` przynajmniej do `AuthenticateHandler`, `SendInvoiceHandler`, `XadesAuthChallengeSigner`.

---

## 🟡 Średnie — dług techniczny

### 9. `KsefNip` value object istnieje, ale nie jest używany

**Plik:** `src/Backend/Authentication/Application/AuthenticateHandler.php`

```php
public function __construct(
    // ...
    private readonly string $ksefNip  // powinno być KsefNip
) {}
```

`KsefNip` waliduje format NIP (10 cyfr) i rzuca `DomainValidationException` przy złym formacie. Handler bierze `string` — walidacja nigdy nie działa.

---

### 10. `SubmittedInvoice::submittedAt` — niespójny format daty

**Plik:** `src/Frontend/Dashboard/UI/Action/SendInvoiceAction.php:57`

```php
(new DateTimeImmutable())->format('Y-m-d H:i:s')  // "2026-03-18 14:22:00" — brak strefy
```

KSeF API zwraca daty w ISO 8601: `"2026-02-21T00:45:17.102739+00:00"`. Lokalne faktury mają inny format — sortowanie po dacie w `resolveTimestamp()` działa (przez `strtotime`), ale prezentacja w UI jest niespójna.

**Naprawa:** `(new DateTimeImmutable())->format(DateTimeInterface::ATOM)` dla spójności.

---

### 11. PHPStan nie analizuje testów

**Plik:** `phpstan.neon`

```yaml
excludePaths:
    - ./tests
```

Testy są wyłączone z analizy statycznej. Błędy typów w testach (np. zły typ mocka) przejdą niezauważone.

**Naprawa:** Dodać testy do ścieżek PHPStan lub osobny krok CI dla testów.

---

### 12. Polling duplikuje logikę — brak `KsefStatusPoller`

**Plik:** `src/Backend/Invoice/Application/SendInvoiceHandler.php`
**Plik:** `src/Backend/Authentication/Application/AuthenticateHandler.php`

Dwie identyczne pętle poll-sleep-check są zduplikowane między handlerami. SKILL `ksef-polling` opisuje już `KsefStatusPoller` jako wzorzec — ale klasa nie istnieje w projekcie.

---

### 13. Brak obsługi wygasłego access tokena w `GetInvoiceOverviewHandler`

**Plik:** `src/Frontend/Dashboard/Application/GetInvoiceOverview/GetInvoiceOverviewHandler.php:47`

```php
$accessToken = $this->accessTokenStore->get() ?? $this->authenticateHandler->execute()->accessToken;
```

Jeśli token jest nieważny (wygasł), `querySessions()` rzuci `ApiClientException` — nigdy nie dojdzie do re-autentykacji. Podobny problem w `SendInvoiceHandler`.

**Naprawa:** Przechwytywać `ApiClientException` z kodem 401/403 i wyczyścić store przed re-auth.

---

## 🟢 Co działa dobrze

### Architektura
- **DDD i bounded contexty** są czytelne. Granica `Backend` / `Frontend` jest respektowana — Frontend nie implementuje logiki biznesowej.
- **Port/Adapter** (interfejsy w Application, implementacje w Infrastructure) umożliwia podmianę implementacji bez zmiany logiki biznesowej.
- **ADR pattern** w kontrolerach — każda klasa robi dokładnie jedną rzecz, jest `final`, nie dziedziczy.
- **Hierarchia wyjątków per warstwa** (`DomainException`, `ApplicationException`, `InfrastructureException`) — poprawna i spójna.

### Kod
- Konsekwentne używanie `final` i `readonly` — minimalizuje przypadkowe dziedziczenie i mutację stanu.
- `Fa3StructuredInvoiceParser` — dobra walidacja wejścia: empty check, placeholder check (`your_nip`), namespace check, formCode check. Fail-fast przed wysyłką.
- `XadesAuthChallengeSigner` — poprawna implementacja XAdES z obsługą zarówno RSA jak i ECDSA, z dedykowanym `XadesSignatureSupport`.
- `SendInvoiceHandler::ensureInvoiceSucceeded()` — wyciąga szczegóły błędu z odpowiedzi API i zwraca je w wyjątku — bardzo pomocne przy debugowaniu odrzuceń KSeF.
- `DomainValidationException::empty()` i `::invalid()` — named constructors to dobry wzorzec.

### Testy
- `makeCapturingClient()` pattern w `KsefApiClientTest` — eliminuje boilerplate i jest czytelniejszy niż rozbudowane closures.
- Testy jednostkowe `SendInvoiceHandler` pokrywają: happy path, brak tokena (re-auth), błąd semantyczny (450). Dobry zakres dla krytycznej ścieżki.

### Konfiguracja
- Aliasy interfejsów w `config/services.yaml` — DI skonfigurowane explicite, nie magicznie.
- `services_test.yaml` — serwisy testowe oddzielone od produkcyjnych.

---

## Priorytetyzacja napraw

| # | Problem | Priorytet | Effort |
|---|---------|-----------|--------|
| 1 | `AccessTokenStore` bezużyteczny między requestami | 🔴 Krytyczny | M |
| 2 | Blokujący polling (timeout 180s) | 🔴 Krytyczny | XL |
| 3 | API URL hardcoded na test | 🔴 Krytyczny | S |
| 4 | Brak try/catch w `GetInvoiceRowsAction` | 🟠 Poważny | XS |
| 5 | N+1 calls w `GetInvoiceOverviewHandler` | 🟠 Poważny | L |
| 6 | `provide()` po wysyłce | 🟠 Poważny | XS |
| 7 | OpenSSL CLI zamiast PHP extension | 🟠 Poważny | M |
| 8 | Brak logowania | 🟠 Poważny | M |
| 9 | `KsefNip` nieużywany | 🟡 Techniczny | XS |
| 10 | Format daty `submittedAt` | 🟡 Techniczny | XS |
| 11 | PHPStan wyklucza testy | 🟡 Techniczny | XS |
| 12 | Polling duplikuje logikę | 🟡 Techniczny | S |
| 13 | Wygasły token nie powoduje re-auth | 🟡 Techniczny | S |
| — | `var/submitted_invoices.json` → PostgreSQL | 🟡 Techniczny | M |
