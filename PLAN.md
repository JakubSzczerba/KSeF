# PLAN — KSeF Dashboard / Implementacja techniczna

**Rola:** Software Architect + Tech Lead
**Data:** 2026-03-18
**Źródło wymagań:** BACKLOG.md
**Stack:** PHP 8.5, Symfony 8, PostgreSQL 18, React (SPA), Docker

---

## 1. Stan aktualny — analiza przed rozpoczęciem

### Co jest zrealizowane (wbrew BACKLOG)

| Element | Status | Uwaga |
|---------|--------|-------|
| `SessionAccessTokenStore` | ✅ DONE | Zaimplementowane i podpięte w `services.yaml` jako alias `AccessTokenStoreInterface` |
| `KSEF_API_URL` jako env | ✅ DONE | `services.yaml` już przyjmuje `%env(KSEF_API_URL)%` — brakuje tylko wpisu w `docker-compose.yml` |
| `TokenRefreshingExecutor` | ✅ DONE | Istnieje, obsługuje 401 → re-auth |
| PSR-3 Logger w handlerach | ✅ DONE | `AuthenticateHandler`, `SendInvoiceHandler`, `XadesAuthChallengeSigner` mają logger |
| `KsefStatusPoller` | ✅ DONE | Klasa istnieje w `Backend/Shared/Application/` |

### Co faktycznie wymaga naprawy (Faza 0 po weryfikacji)

1. `docker-compose.yml` — brak `KSEF_API_URL` w env serwisu php
2. `var/submitted_invoices.json` → PostgreSQL (tabela `submitted_invoices`)
3. `SendInvoiceHandler` — blokujący polling (Messenger/async)
4. `GetInvoiceRowsAction` — brak try/catch
5. `SendInvoiceAction` — usuwa `rows` z odpowiedzi
6. `OpenSslInvoiceEncryptor` — CLI zamiast PHP extension
7. Format daty `submittedAt`
8. `KsefNip` value object w `AuthenticateHandler`

### Frontend — kluczowa obserwacja

React SPA jest jako **pre-compiled** `public/ui/react-dashboard.js` — brak źródeł w repozytorium.
Decyzja do podjęcia: czy źródła są w osobnym repo, czy zagubione.
**Plan:** odtworzyć setup `assets/` z Vite + TypeScript + React w tym samym repozytorium
(Symfony AssetMapper lub Vite standalone z wyjściem do `public/ui/`).

### Frontend — status po domknięciu layoutu

EPIC `1.1` jest praktycznie zamknięty w aktualnym dashboardzie:
- sidebar z sekcjami `Start`, `Faktury`, `Kontrahenci`, `Raporty`, `Ustawienia`
- topbar z wyszukiwarką, przełącznikiem motywu i avatarem
- breadcrumbs dla aktywnej sekcji
- mobilny drawer dla nawigacji

Wniosek: kolejny frontendowy krok to już nie shell aplikacji, tylko `1.2` widgety dashboardu
albo `1.3` tabela faktur. Z perspektywy całości projektu nadal wyżej stoją zadania stabilizacyjne z Fazy 0.

---

## 2. Decyzje architektoniczne (ADR)

### ADR-01: Async processing — Symfony Messenger + Redis

**Opcje:** (a) Messenger + Redis, (b) Messenger + Doctrine transport, (c) brak async (status quo)

**Decyzja: Messenger + Redis**

Uzasadnienie: Redis jest szybki, bezstanowy między workerami, obsługuje TTL. Doctrine transport
jest prostszy w setup, ale blokuje tabele DB przy dużym ruchu. Redis to standard w ekosystemie Symfony.

Konsekwencje:
- Dodać `redis` do `docker-compose.yml` (obraz `redis:7-alpine`)
- `composer require symfony/messenger symfony/redis-messenger`
- Dodać `MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages` do env
- Nowy serwis `worker` w docker-compose: `bin/console messenger:consume async`

### ADR-02: ORM — Doctrine DBAL zamiast pełnego ORM

**Opcje:** (a) Doctrine ORM (Entities, Repositories), (b) Doctrine DBAL (raw SQL + RowMapper)

**Decyzja: Doctrine ORM** dla nowych tabel (contractors, submitted_invoices, company_settings).

Uzasadnienie: Projekt już ma Doctrine w zależnościach (Symfony), migracje Doctrine są standardem.
Dla prostych encji (kontrahent, faktura lokalna) ORM jest wystarczający i nie wprowadza overhead.
Repozytorium domenowe (port interface w Application) ukrywa ORM — zgodnie z Port/Adapter.

### ADR-03: Frontend — Vite + TypeScript + React w monorepo

**Decyzja:** Źródła w `assets/` w tym samym repozytorium, build przez Vite do `public/ui/`.

Uzasadnienie:
- Brak osobnego repo = brak problemu synchronizacji backend/frontend kontraktów
- Symfony nie wymaga Webpack Encore — Vite jest prostszy i szybszy
- TypeScript wymusza kontrakty API (typy odpowiedzi), ogranicza runtime bugs

Struktura:
```
assets/
  src/
    components/       # współdzielone komponenty (Button, Badge, Table...)
    features/
      dashboard/      # widgety, wykresy
      invoices/       # lista, formularz, szczegóły
      contractors/    # CRUD
      reports/        # wykresy, eksport
      settings/       # ustawienia
    api/              # fetch wrappers + typy TypeScript
    hooks/            # useInvoices, useContractors...
    types/            # Invoice, Contractor, ApiResponse...
  vite.config.ts
  tsconfig.json
  package.json
```

### ADR-04: Statusy płatności — w lokalnej tabeli, nie w KSeF

KSeF nie przechowuje statusów płatności. `payment_status` (unpaid/paid/overdue) żyje wyłącznie
w tabeli `submitted_invoices` w PostgreSQL. Jest aktualizowany ręcznie z UI lub automatycznie
przez cron (overdue detection).

### ADR-05: Generowanie FA(3) XML — w PHP, bez zewnętrznych bibliotek

Formularz faktury → `InvoiceFormData` DTO → `Fa3XmlGenerator` (nowa klasa w `Backend/Parser/`
lub nowy kontekst `Backend/XmlGeneration/`). Generator buduje XML string zgodny ze schematem FA(3)
używając `\DOMDocument`. Bez Twig XML templates (zbyt magiczne), bez zewnętrznych pakietów.

### ADR-06: Kontrahenci — integracja z GUS przez własny adapter

GUS API (`api.stat.gov.pl/NDS/api`) jest publiczne, bez klucza dla podstawowych danych.
Nowy port `GusApi` w `Backend/Contractor/Application/Contract/GusApi.php` + adapter HTTP.
Nie używamy zewnętrznych pakietów PHP do GUS — API jest proste (GET `/firmy/daneFirmy?nip=...`).

---

## 3. Mapa zależności faz

```
Faza 0 (stabilizacja)
  └─► Faza 1 (dashboard + nawigacja)
        └─► Faza 2 (formularz faktury) ──► wymaga ADR-05 (XmlGenerator)
        └─► Faza 3 (kontrahenci)        ──► blokuje Faza 2 (autocomplete NIP)
        └─► Faza 4 (statusy płatności)  ──► wymaga migracji z Fazy 0
  └─► Faza 5 (raporty)      ──► wymaga danych z Fazy 4
  └─► Faza 6 (ustawienia)   ──► niezależna, może iść równolegle z Fazą 2
  └─► Faza 7 (przychodzące) ──► niezależna od Faz 2–6
Faza 8 (CI/testy)           ──► równolegle z każdą fazą
```

**Optymalny order realizacji:**
`0 → 1.1 (layout) → 0.4 (quick fixes) → 1.2+1.3 (dashboard) → 3 (kontrahenci) → 2 (faktury) → 4 → 5 → 6 → 7`

**Status na teraz:**
`1.1 (layout)` — zrealizowane
Następny logiczny krok: `0.4 (quick fixes)`, potem `0.1.2/0.1.3` i `0.3.x`

---

## 4. Faza 0 — Plan techniczny (krok po kroku)

### Krok 0.1: Fix docker-compose.yml + env

```yaml
# docker-compose.yml — dopisać do serwisu php environment:
KSEF_API_URL: "https://api-test.ksef.mf.gov.pl/v2"
MESSENGER_TRANSPORT_DSN: "redis://redis:6379/messages"
SESSION_HANDLER_DSN: "redis://redis:6379"

# nowy serwis redis:
redis:
  image: redis:7-alpine
  ports:
    - "6379:6379"

# nowy serwis worker:
worker:
  build:
    context: ./docker/php
  command: ["php", "bin/console", "messenger:consume", "async", "--time-limit=3600"]
  depends_on: [php, redis, db]
  volumes:
    - .:/var/www/ksef:delegated
  environment: *php-environment  # YAML anchor
```

### Krok 0.2: PostgreSQL — migracja submitted_invoices

**Nowa encja:** `Ksef\Frontend\Dashboard\Infrastructure\Persistence\SubmittedInvoiceEntity`

```sql
CREATE TABLE submitted_invoices (
    id              BIGSERIAL PRIMARY KEY,
    session_ref     VARCHAR(255) NOT NULL,
    invoice_ref     VARCHAR(255) NOT NULL,
    ksef_number     VARCHAR(255),
    invoice_number  VARCHAR(255),
    submitted_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    payment_status  VARCHAR(20)  NOT NULL DEFAULT 'unpaid',
    UNIQUE(session_ref, invoice_ref)
);
CREATE INDEX idx_submitted_invoices_submitted_at ON submitted_invoices(submitted_at DESC);
CREATE INDEX idx_submitted_invoices_payment_status ON submitted_invoices(payment_status);
```

**Migracja danych:** jednorazowy command `bin/console app:migrate-json-to-db` — czyta
`var/submitted_invoices.json`, importuje do tabeli, kasuje plik.

**Nowe klasy:**
- `Frontend/Dashboard/Infrastructure/Persistence/DoctrineSubmittedInvoiceRepository` implements `SubmittedInvoiceRepositoryInterface`
- Port interface `Frontend/Dashboard/Application/Contract/SubmittedInvoiceRepositoryInterface` (wyciągnąć z klasy)
- Stara `SubmittedInvoiceRepository` (JSON) → usunąć po migracji

### Krok 0.3: Async — Symfony Messenger

**Nowe klasy:**
```
Backend/Invoice/Application/
  SendInvoiceCommand.php          ← już istnieje, dodać implements Message
  SendInvoiceMessageHandler.php   ← nowy, opakowuje SendInvoiceHandler
  Dto/SendInvoiceJobStatus.php    ← pending|processing|done|failed + details

Frontend/Dashboard/
  Application/GetSendJobStatus/GetSendJobStatusHandler.php
  UI/Action/SendInvoiceAction.php  ← zmienić: dispatch Message, zwróć jobId
  UI/Action/GetSendJobStatusAction.php  ← nowy endpoint GET /send/status/{jobId}
```

**Flow po zmianie:**
```
POST /send
  → dispatch(SendInvoiceCommand) → Redis queue
  → zwróć {ok: true, jobId: "uuid"}  ← natychmiast

worker: messenger:consume async
  → SendInvoiceMessageHandler::__invoke(SendInvoiceCommand)
  → wywołuje SendInvoiceHandler::execute()
  → zapisuje wynik do cache (Redis TTL 300s, klucz: "job:{uuid}")

GET /send/status/{jobId}
  → czyta z cache status job
  → zwraca {status: "done"|"processing"|"failed", ksefNumber?, error?}
```

**Frontend:** po dispatch polling co 2s na `/send/status/{jobId}`, progress bar, po `done` odświeżenie listy.

### Krok 0.4: Quick fixes (XS)

W kolejności realizacji:
1. `GetInvoiceRowsAction` — owinąć `provide()` w try/catch → `{ok: false, message: $e->getMessage()}`
2. `SendInvoiceAction` — usunąć `rows` z odpowiedzi JSON (frontend polluje `/invoices/rows` osobno)
3. `SubmittedInvoice::submittedAt` format → `DateTimeInterface::ATOM`
4. `AuthenticateHandler` — zmienić `string $ksefNip` na `KsefNip $ksefNip`

### Krok 0.5: OpenSSL CLI → PHP extension

W `OpenSslInvoiceEncryptor::encryptSymmetricKey()` zastąpić wywołanie `proc_open`/`shell_exec`
przez:
```php
openssl_public_encrypt($symmetricKey, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
```
PHP 8.x obsługuje OAEP SHA-256 natywnie przez `openssl_public_encrypt` z odpowiednią flagą.
Jeśli padding SHA-256 wymaga innego podejścia: użyć `openssl_pkey_get_public()` + `openssl_seal()`.
Uwaga: zweryfikować dokładny padding wymagany przez KSeF API (SHA-1 OAEP vs SHA-256 OAEP).

---

## 5. Faza 1 — Dashboard i nawigacja

### 5.1 Frontend setup (wykonać przed Fazą 1)

Uwaga: obecny layout został domknięty w istniejącym `public/ui/react-dashboard.js`.
Migracja do `assets/` + Vite pozostaje decyzją architektoniczną na kolejny etap, ale nie blokuje już
dalszych prac produktowych nad dashboardem.

```
assets/
  package.json        # react, react-dom, typescript, vite, tailwindcss, recharts, lucide-react
  vite.config.ts      # build → public/ui/react-dashboard.js
  src/
    main.tsx          # mount #ksef-react-root
    App.tsx           # Router + Layout
    layouts/
      AppLayout.tsx   # Sidebar + Topbar + <Outlet />
    router.tsx        # React Router v6 — deklaratywne routes
```

**Sidebar routes:**
| Path | Komponent | Sekcja |
|------|-----------|--------|
| `/` | `DashboardPage` | Start |
| `/invoices` | `InvoiceListPage` | Faktury |
| `/invoices/new` | `InvoiceFormPage` | Faktury > Nowa |
| `/invoices/:ksefNumber` | `InvoiceDetailPage` | Faktury |
| `/contractors` | `ContractorListPage` | Kontrahenci |
| `/reports` | `ReportsPage` | Raporty |
| `/settings` | `SettingsPage` | Ustawienia |

Uwaga: to jest SPA — Symfony obsługuje tylko `/` (i API endpoints). Wszystkie SPA routes
muszą być fallback do `DashboardAction`. Dodać catch-all route w `config/routes.yaml`
lub nginx `try_files`.

### 5.2 Backend — nowe API endpoints dla dashboardu

Obecny `GET /invoices/rows` zwraca płaską listę. Do Fazy 1 dodać:

```
GET /api/dashboard/stats
  Response: {
    revenueThisMonth: float,
    unpaidCount: int,
    unpaidAmount: float,
    overdueCount: int,
    overdueAmount: float,
    sentThisMonth: int,
    revenueByMonth: [{month: "2026-01", amount: float}, ...]  // 12 mies.
  }

GET /api/invoices?page=1&limit=25&status=unpaid&dateFrom=...&dateTo=...&search=...
  Response: {
    items: InvoiceRow[],
    total: int,
    page: int,
    pages: int
  }
```

Nowe klasy backend:
- `Frontend/Dashboard/UI/Action/GetDashboardStatsAction` → `GET /api/dashboard/stats`
- `Frontend/Dashboard/Application/GetDashboardStats/GetDashboardStatsHandler`
- Rozszerzyć `GetInvoiceRowsAction` o paginację i filtry lub nowy `ListInvoicesAction`

---

## 6. Faza 2 — Formularz faktury i generowanie FA(3) XML

### 6.1 Nowy bounded context: `Backend/InvoiceGeneration/`

```
Backend/InvoiceGeneration/
  Domain/
    InvoiceFormData.php       # DTO: dane sprzedawcy, nabywcy, pozycji, warunków płatności
    InvoiceLine.php           # pozycja faktury: nazwa, PKWiU, j.m., ilość, cena, stawka VAT
    VatRate.php               # enum: 23, 8, 5, 0, ZW, NP, OO
  Application/
    GenerateInvoiceXmlCommand.php
    GenerateInvoiceXmlHandler.php   # InvoiceFormData → XML string (FA3)
    Contract/InvoiceXmlGenerator.php
  Infrastructure/
    DomDocumentXmlGenerator.php    # implementacja port interface przez \DOMDocument
```

### 6.2 Schemat FA(3) — kluczowe pola do wygenerowania

```xml
<Faktura xmlns="http://crd.gov.pl/wzor/2023/06/29/12648/">
  <Naglowek>
    <KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza>
    <WariantFormularza>3</WariantFormularza>
    <DataWytworzeniaFa>...</DataWytworzeniaFa>  <!-- ISO 8601 -->
    <SystemInfo>KSeF Dashboard</SystemInfo>
  </Naglowek>
  <Podmiot1><!-- Sprzedawca: NIP, nazwa, adres --></Podmiot1>
  <Podmiot2><!-- Nabywca: NIP, nazwa, adres --></Podmiot2>
  <Fa>
    <KodWaluty>PLN</KodWaluty>
    <P_1>data faktury</P_1>
    <P_2>numer faktury</P_2>
    <P_6>data dostawy/usługi</P_6>
    <FaWiersz><!-- per pozycja --></FaWiersz>
    <P_13_1>suma netto 23%</P_13_1>
    <P_14_1>VAT 23%</P_14_1>
    <P_15>suma brutto</P_15>
    <Adnotacje>...</Adnotacje>
    <Platnosc>
      <Zaplacono>1|2</Zaplacono>
      <TerminPlatnosci><Termin>data</Termin></TerminPlatnosci>
    </Platnosc>
    <PodatnikVAT>1</PodatnikVAT>
  </Fa>
</Faktura>
```

**Walidacja:** uruchomić `Fa3StructuredInvoiceParser` na wygenerowanym XML przed wysyłką —
parser już waliduje namespace, formCode, placeholder NIP. Fail-fast.

### 6.3 Frontend — formularz

```tsx
// InvoiceFormPage.tsx
// Sekcje:
// 1. Dane nabywcy (NIP → autocomplete → dane z GUS / bazy kontrahentów)
// 2. Pozycje faktury (dynamic list: dodaj/usuń wiersz, auto-przeliczanie sum)
// 3. Płatność (metoda, termin, waluta)
// 4. Akcje: [Podgląd PDF] [Wyślij do KSeF]
```

Library: `react-hook-form` + `zod` dla walidacji. Tabela pozycji: own component, nie zewnętrzna lib.

---

## 7. Faza 3 — Kontrahenci

### 7.1 Schemat bazy

```sql
CREATE TABLE contractors (
    id          BIGSERIAL PRIMARY KEY,
    nip         VARCHAR(10)  NOT NULL UNIQUE,
    name        VARCHAR(500) NOT NULL,
    address     VARCHAR(500),
    city        VARCHAR(200),
    postal_code VARCHAR(10),
    email       VARCHAR(255),
    phone       VARCHAR(50),
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_contractors_nip ON contractors(nip);
CREATE INDEX idx_contractors_name ON contractors(name);
```

### 7.2 Nowy bounded context: `Backend/Contractor/`

```
Backend/Contractor/
  Domain/
    Contractor.php            # value object (NIP, name, address)
    ContractorId.php
  Application/
    Contract/ContractorRepository.php
    Contract/GusApiClient.php          # port: lookupByNip(string): ?GusCompanyData
    SaveContractorCommand.php
    SaveContractorHandler.php
    LookupNipCommand.php
    LookupNipHandler.php               # calls GusApiClient
  Infrastructure/
    Persistence/DoctrineContractorRepository.php
    Gus/HttpGusApiClient.php           # GET api.stat.gov.pl/NDS/api/firmy/daneFirmy?nip=...
```

### 7.3 Nowe endpoints

```
GET  /api/contractors?search=...&page=1&limit=25
POST /api/contractors
PUT  /api/contractors/{id}
DELETE /api/contractors/{id}
GET  /api/contractors/lookup/{nip}    ← GUS lookup, nie zapisuje do DB
```

---

## 8. Faza 4 — Statusy płatności

### Logika statusów

```
draft   → faktura zapisana lokalnie, niewysłana do KSeF
unpaid  → wysłana do KSeF, bez potwierdzenia zapłaty
paid    → oznaczona ręcznie lub (przyszłość) matched z bankiem
overdue → unpaid + termin_platnosci < TODAY
```

### Implementacja

- Dodać kolumny `payment_status`, `payment_due_date`, `gross_amount` do tabeli `submitted_invoices`
- `PUT /api/invoices/{id}/payment-status` → `UpdatePaymentStatusAction`
- `bin/console app:mark-overdue-invoices` — Symfony Command, odpalany przez cron (codziennie)
- Cron w docker-compose: osobny serwis lub `supercrond` w kontenerze worker

---

## 9. Faza 5 — Raporty

### Backend

```
GET /api/reports/revenue-by-month?year=2026
  → JOIN submitted_invoices WHERE payment_status='paid' GROUP BY month

GET /api/reports/summary?dateFrom=...&dateTo=...
  → {totalGross, totalNet, totalVat, paidCount, unpaidCount, overdueCount}

GET /api/reports/export/csv?dateFrom=...&dateTo=...
  → StreamedResponse: CSV (symfony/http-foundation StreamedResponse)
```

### Frontend

- Recharts `BarChart` — przychody per miesiąc (12 słupków, 2 serie: wystawione/opłacone)
- Recharts `LineChart` — kumulatywny przychód YTD
- Tabela zestawień z eksportem CSV

---

## 10. Faza 6 — Ustawienia

### Schemat bazy

```sql
CREATE TABLE company_settings (
    key    VARCHAR(100) PRIMARY KEY,
    value  TEXT         NOT NULL
);
-- Seed: company_name, nip, address, bank_account, email,
--       invoice_number_prefix, invoice_number_format, ksef_environment
```

### Nowe klasy

- `Frontend/Settings/Domain/CompanySettings.php` — value object z getterami
- `Frontend/Settings/Infrastructure/DoctrineCompanySettingsRepository.php`
- `Frontend/Settings/UI/Action/GetSettingsAction` + `UpdateSettingsAction`
- `Frontend/Settings/Application/GetSettingsHandler` + `UpdateSettingsHandler`

### KSeF environment switch

`ksef_environment` (test|prod) w tabeli settings → przy zmianie invalidate token cache + zmień URL.
Wymaga refaktoru `KsefApiClient` — URL z `CompanySettingsRepository`, nie z env (nadpisanie).
Lub: dynamiczny parameter przez `ContainerInterface` — prostszy wariant: restart serwisu.

---

## 11. Infrastruktura — pełna mapa zmian docker-compose.yml

```yaml
services:
  db:       # bez zmian
  redis:    # NOWE — redis:7-alpine, port 6379
  php:      # dodać env: KSEF_API_URL, MESSENGER_TRANSPORT_DSN, SESSION_DSN
  nginx:    # dodać try_files dla SPA fallback
  worker:   # NOWE — php image + messenger:consume async --time-limit=3600
  cron:     # NOWE (Faza 4) — php image + supercrond / dcron dla overdue-invoices
```

---

## 12. Schemat pełnej bazy danych (target state)

```
submitted_invoices
  id, session_ref, invoice_ref, ksef_number, invoice_number,
  submitted_at, payment_status, payment_due_date, gross_amount,
  net_amount, vat_amount, contractor_nip

contractors
  id, nip, name, address, city, postal_code, email, phone,
  created_at, updated_at

company_settings
  key, value

invoice_drafts                         ← Faza 2 (szkice)
  id, form_data_json, invoice_number,
  contractor_id, created_at, updated_at

messenger_messages                     ← Doctrine Messenger transport (fallback jeśli bez Redis)
  (generowane przez Symfony)
```

---

## 13. Kontrakt API — typy TypeScript (fragment)

```typescript
// assets/src/types/invoice.ts
export type PaymentStatus = 'draft' | 'unpaid' | 'paid' | 'overdue';

export interface InvoiceRow {
  id: number;
  sessionReferenceNumber: string;
  invoiceReferenceNumber: string;
  ksefNumber: string | null;
  invoiceNumber: string | null;
  submittedAt: string;          // ISO 8601
  invoiceStatusCode: number | null;
  invoiceStatusDescription: string;
  sessionStatusCode: number | null;
  paymentStatus: PaymentStatus;
  grossAmount: number | null;
  paymentDueDate: string | null;
}

export interface PaginatedResponse<T> {
  items: T[];
  total: number;
  page: number;
  pages: number;
  limit: number;
}

export interface ApiError {
  ok: false;
  message: string;
  code?: number;
}
```

---

## 14. Strategia testowania per faza

| Faza | Co testować | Typ testu |
|------|------------|-----------|
| 0 | `DoctrineSubmittedInvoiceRepository`, `SendInvoiceMessageHandler` | Unit + Integration |
| 0 | `OpenSslInvoiceEncryptor` po zmianie na PHP extension | Unit (już istnieje) |
| 1 | `GetDashboardStatsHandler`, `ListInvoicesAction` | Unit + Functional |
| 2 | `DomDocumentXmlGenerator` — roundtrip: generate → parse → validate | Unit |
| 2 | `GenerateInvoiceXmlHandler` | Unit |
| 3 | `HttpGusApiClient` z MockHttpClient | Integration |
| 3 | `SaveContractorHandler`, `LookupNipHandler` | Unit |
| wszystkie | Happy path + error path per Action | Functional (WebTestCase) |

**Reguła:** każdy nowy handler musi mieć test jednostkowy przed merge. Functional testy dla każdego
nowego endpointu API.

---

## 15. Ryzyka i mitigacje

| Ryzyko | Prawdopodobieństwo | Impact | Mitigacja |
|--------|-------------------|--------|-----------|
| KSeF API schema FA(3) — niezgodność wygenerowanego XML | Wysokie | Krytyczne | Testy z realnym API testowym po każdej zmianie generatora |
| Brak źródeł React w repo — odtworzenie komponentów | Wysokie | Duże | Priorytet: setup Vite + odtworzenie jako pierwsze zadanie Fazy 1 |
| Migracja JSON → DB — utrata danych w `var/` | Niskie | Średnie | Command `migrate-json-to-db` z dry-run mode i backup |
| Redis unavailable w środowisku produkcyjnym | Niskie | Krytyczne | Fallback: Doctrine Messenger transport jako opcja konfiguracyjna |
| GUS API — zmiany endpointów | Niskie | Małe | Wrapper `HttpGusApiClient` izoluje zmiany do jednej klasy |
| Formularz FA(3) — złożoność schematu (korekty, zaliczki) | Wysokie | Duże | Faza 2 wyłącznie standardowa faktura VAT; korekty i zaliczki w Fazie 2.2 |

---

## 16. Kolejność commit'ów (Faza 0)

Każdy commit = jeden działający, przetestowany krok:

1. `Add KSEF_API_URL and Redis service to docker-compose`
2. `Add submitted_invoices Doctrine entity and migration`
3. `Extract SubmittedInvoiceRepositoryInterface, implement Doctrine adapter`
4. `Add migrate-json-to-db console command`
5. `Add try/catch to GetInvoiceRowsAction`
6. `Remove rows from SendInvoiceAction response`
7. `Fix submittedAt date format to ISO 8601`
8. `Use KsefNip value object in AuthenticateHandler`
9. `Replace OpenSSL CLI with native PHP openssl_public_encrypt`
10. `Add Redis transport and Symfony Messenger async configuration`
11. `Implement async SendInvoiceCommand dispatch and job status endpoint`
12. `Add worker service to docker-compose`
