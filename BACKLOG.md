# BACKLOG — KSeF Dashboard

**Product Vision:** Profesjonalna aplikacja do obsługi KSeF wzorowana na infakt.pl —
pełne zarządzanie fakturami wychodządymi, przegląd finansów, obsługa kontrahentów i raportowanie,
z KSeF jako warstwą transportową i walidacyjną.

**Stan bazowy (zrealizowane):**
- Dashboard React (dark/light mode, Tailwind)
- Dashboard application shell: sidebar, topbar, breadcrumbs, mobile drawer
- Wysyłanie faktury XML do KSeF
- Lista faktur z KSeF + lokalny bufor JSON
- Pobieranie XML i PDF faktury

---

## FAZA 0 — Stabilizacja i naprawy krytyczne

> Musi być zrealizowana zanim cokolwiek nowego trafi do użytkownika.
> Odpowiada pozycjom 1–8 z code review.

### EPIC 0.1 — Infrastruktura i konfiguracja

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 0.1.1 | Przenieść `KSEF_API_URL` do zmiennej środowiskowej; usunąć hardcoded URL z `KsefApiClient` | 🔴 | XS |
| 0.1.2 | Zastąpić `var/submitted_invoices.json` tabelą PostgreSQL `submitted_invoices` | 🔴 | M |
| 0.1.3 | Migracja Doctrine: schemat tabeli `submitted_invoices` (ksefNumber, sessionRef, invoiceRef, submittedAt, status) | 🔴 | S |

### EPIC 0.2 — Token i sesja KSeF

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 0.2.1 | Przechowywać access token w sesji Symfony (nie in-memory) — `AccessTokenStore` → `SessionAccessTokenStore` | 🔴 | M |
| 0.2.2 | TTL tokenu KSeF — automatyczne wygasanie i re-auth gdy token expired (401/403 → re-auth flow) | 🔴 | M |
| 0.2.3 | `TokenRefreshingExecutor` — obsługa 401 z re-auth w `GetInvoiceOverviewHandler` | 🟠 | S |

### EPIC 0.3 — Async processing

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 0.3.1 | Symfony Messenger + Redis transport — `SendInvoiceCommand` trafia do kolejki | 🔴 | L |
| 0.3.2 | `POST /send` zwraca natychmiast `{jobId}`, nie czeka na KSeF | 🔴 | M |
| 0.3.3 | `GET /send/status/{jobId}` — endpoint SSE lub polling statusu job | 🔴 | M |
| 0.3.4 | Worker PHP (`bin/console messenger:consume`) w docker-compose jako osobny serwis | 🔴 | S |
| 0.3.5 | Frontend: progress bar / spinner polling statusu wysyłki co 2s | 🔴 | S |

### EPIC 0.4 — Jakość kodu

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 0.4.1 | Dodać `try/catch` do `GetInvoiceRowsAction` → JSON `{ok: false, message: '...'}` | 🟠 | XS |
| 0.4.2 | Usunąć `rows` z odpowiedzi `POST /send` — frontend odświeża listę osobno | 🟠 | XS |
| 0.4.3 | Zastąpić OpenSSL CLI w `OpenSslInvoiceEncryptor` natywnym `openssl_public_encrypt()` z `OPENSSL_PKCS1_OAEP_PADDING` | 🟠 | M |
| 0.4.4 | Ujednolicić format daty `submittedAt` → ISO 8601 (`DateTimeInterface::ATOM`) | 🟡 | XS |
| 0.4.5 | Użyć `KsefNip` value object w `AuthenticateHandler` zamiast `string` | 🟡 | XS |
| 0.4.6 | Włączyć `tests/` do analizy PHPStan | 🟡 | XS |

---

## FAZA 1 — Dashboard i nawigacja (infakt-like UX)

> Cel: profesjonalny pulpit z nawigacją boczną, widgetami finansowymi i tabelą faktur.

### EPIC 1.1 — Layout i nawigacja

Status: `DONE` w aktualnym layoucie dashboardu.
Zakres zrealizowany: sidebar nawigacyjny, topbar z wyszukiwarką i przełącznikiem motywu,
responsywny drawer mobilny oraz breadcrumbs. Kolejne prace w Fazie 1 zaczynają się od `1.2` i `1.3`.

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 1.1.1 | Sidebar nawigacyjny: Start, Faktury, Kontrahenci, Raporty, Ustawienia | 🔴 | M |
| 1.1.2 | Topbar: logo, user avatar, przełącznik dark/light, wyszukiwarka globalna (placeholder) | 🔴 | S |
| 1.1.3 | Responsywność — hamburger + drawer na mobile | 🟠 | M |
| 1.1.4 | Breadcrumbs per sekcja | 🟡 | XS |

### EPIC 1.2 — Widgety pulpitu (Start)

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 1.2.1 | Widget "Przychody" — suma faktur opłaconych w bieżącym miesiącu | 🔴 | S |
| 1.2.2 | Widget "Faktury do opłacenia" — count + suma nieopłaconych | 🔴 | S |
| 1.2.3 | Widget "Przeterminowane" — count + suma przeterminowanych | 🔴 | S |
| 1.2.4 | Widget "Wysłane przez KSeF" — count w miesiącu | 🟠 | XS |
| 1.2.5 | Wykres słupkowy: przychody vs. miesiące (ostatnie 12 miesięcy) — Recharts | 🟠 | M |
| 1.2.6 | Lista ostatnich 5 faktur na dashboardzie | 🟠 | S |

### EPIC 1.3 — Tabela faktur

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 1.3.1 | Kolumny: Nr KSeF, Nr faktury, Kontrahent, Data wystawienia, Kwota brutto, Status, Akcje | 🔴 | M |
| 1.3.2 | Statusy z kolorowymi badge'ami: Wysłana, Przetworzona, Błąd, Lokalna (bufor) | 🔴 | S |
| 1.3.3 | Paginacja po stronie serwera (`?page=1&limit=25`) | 🔴 | M |
| 1.3.4 | Filtrowanie: status, zakres dat, kontrahent | 🟠 | M |
| 1.3.5 | Sortowanie po kolumnach (data, kwota) | 🟠 | S |
| 1.3.6 | Wyszukiwanie po numerze KSeF / numerze faktury | 🟠 | S |
| 1.3.7 | Eksport CSV listy faktur | 🟡 | M |

---

## FAZA 2 — Zarządzanie fakturami

> Cel: pełny cykl życia faktury — tworzenie formularza, podgląd, historia statusów.

### EPIC 2.1 — Formularz tworzenia faktury

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 2.1.1 | Formularz React: dane sprzedawcy (wypełniane z konfiguracji), dane nabywcy (NIP → autocomplete z bazy kontrahentów), pozycje (nazwa, ilość, j.m., cena, VAT), suma netto/brutto, metoda płatności, termin płatności | 🔴 | XL |
| 2.1.2 | Backend: `GenerateInvoiceXmlHandler` — generowanie FA(3) XML z danych formularza | 🔴 | XL |
| 2.1.3 | Walidacja formularza po stronie frontendu (React Hook Form / Zod) | 🟠 | M |
| 2.1.4 | Szkic faktury (`status: draft`) — zapis lokalny bez wysyłki do KSeF | 🟠 | M |
| 2.1.5 | Podgląd PDF przed wysyłką | 🟡 | M |
| 2.1.6 | Duplikowanie istniejącej faktury jako punkt startowy | 🟡 | S |

### EPIC 2.2 — Typy dokumentów (etapami)

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 2.2.1 | Faktura VAT standardowa | 🔴 | — (w 2.1) |
| 2.2.2 | Faktura zaliczkowa | 🟠 | L |
| 2.2.3 | Faktura korygująca (credit note) | 🟠 | L |
| 2.2.4 | Faktura pro forma (nie do KSeF, tylko PDF) | 🟡 | M |

### EPIC 2.3 — Szczegóły faktury

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 2.3.1 | Widok szczegółów faktury: wszystkie pola, historia statusów KSeF, akcje (pobierz XML, pobierz PDF) | 🔴 | M |
| 2.3.2 | Timeline statusów KSeF (Wysłana → W trakcie → Przetworzona / Błąd) | 🟠 | M |
| 2.3.3 | Oznaczanie faktury jako opłaconej (lokalnie) | 🟠 | S |
| 2.3.4 | Wysłanie faktury na email kontrahenta (SMTP) | 🟡 | M |
| 2.3.5 | Link do pobrania faktury (publiczny, tymczasowy token) | 🟡 | L |

---

## FAZA 3 — Kontrahenci

> Cel: baza kontrahentów do autocomplete i historii rozliczeń.

### EPIC 3.1 — CRUD kontrahentów

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 3.1.1 | Tabela `contractors` w PostgreSQL (NIP, nazwa, adres, email, telefon) | 🔴 | S |
| 3.1.2 | Lista kontrahentów z wyszukiwarką | 🔴 | M |
| 3.1.3 | Formularz dodawania / edycji kontrahenta | 🔴 | M |
| 3.1.4 | Autocomplete NIP → dane z GUS API (`api.stat.gov.pl/NDS/api`) | 🟠 | M |
| 3.1.5 | Weryfikacja VAT kontrahenta w VIES / Biała lista MF | 🟡 | M |

### EPIC 3.2 — Historia kontrahenta

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 3.2.1 | Widok kontrahenta: dane + lista jego faktur + suma przychodów | 🟠 | M |
| 3.2.2 | Zaległości kontrahenta — faktury przeterminowane | 🟡 | S |

---

## FAZA 4 — Statusy płatności

> Cel: śledzenie cyklu należności (nieopłacona → opłacona → przeterminowana).

### EPIC 4.1 — Statusy faktur

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 4.1.1 | Kolumna `payment_status` w tabeli `submitted_invoices` (draft, unpaid, paid, overdue) | 🔴 | S |
| 4.1.2 | Automatyczne oznaczanie jako `overdue` po przekroczeniu terminu płatności (cron/command) | 🟠 | M |
| 4.1.3 | Ręczna zmiana statusu płatności z UI | 🔴 | S |
| 4.1.4 | Filtr "Przeterminowane" w tabeli faktur | 🟠 | S |

---

## FAZA 5 — Raporty i statystyki

> Cel: widok finansowy firmy — przychody, należności, podsumowania miesięczne.

### EPIC 5.1 — Raporty przychodów

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 5.1.1 | Zestawienie przychodów: suma brutto/netto/VAT per miesiąc | 🟠 | M |
| 5.1.2 | Wykres: przychody miesięczne (12 miesięcy wstecz) — Recharts | 🟠 | M |
| 5.1.3 | Filtr: zakres dat, status płatności, kontrahent | 🟠 | M |
| 5.1.4 | Eksport raportu do CSV | 🟡 | S |
| 5.1.5 | Eksport JPK_FA (Jednolity Plik Kontrolny faktur) — XML wg schematu MF | 🟡 | XL |

### EPIC 5.2 — Podsumowanie miesiąca

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 5.2.1 | Widget "Podsumowanie miesiąca" na dashboardzie: wystawione / opłacone / przeterminowane | 🟠 | M |
| 5.2.2 | Porównanie miesiąc do miesiąca (MoM) | 🟡 | M |

---

## FAZA 6 — Ustawienia

> Cel: konfiguracja aplikacji bez zmiany kodu.

### EPIC 6.1 — Dane firmy

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 6.1.1 | Strona ustawień: dane firmy (nazwa, NIP, adres, bank, email) — persystowane w DB | 🔴 | M |
| 6.1.2 | Wstrzyknięcie danych firmy do formularza faktury i PDF | 🟠 | S |

### EPIC 6.2 — Konfiguracja KSeF

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 6.2.1 | Strona "KSeF" w ustawieniach: środowisko (test/prod), NIP, status połączenia (ping API) | 🔴 | M |
| 6.2.2 | Przełącznik test ↔ produkcja — zmiana `KSEF_API_URL` bez restartu kontenera | 🟠 | M |
| 6.2.3 | Zarządzanie certyfikatem: upload pliku `.p12` / `.pem` przez UI, bez dostępu do kontenera | 🟡 | L |

### EPIC 6.3 — Numeracja faktur

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 6.3.1 | Konfiguracja serii numeracyjnej (prefix, format, rok, miesiąc) | 🟡 | M |
| 6.3.2 | Automatyczny numer kolejny przy tworzeniu faktury | 🟠 | S |

---

## FAZA 7 — Operacje na fakturach KSeF (odbiór)

> Cel: pobieranie faktur kosztowych wystawionych przez dostawców.

### EPIC 7.1 — Faktury przychodzące (purchase invoices)

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 7.1.1 | Endpoint KSeF: `GET /sessions` (batch) — pobieranie faktur kosztowych wystawionych na NIP firmy | 🟡 | L |
| 7.1.2 | Lista faktur kosztowych w osobnej zakładce "Koszty" | 🟡 | L |
| 7.1.3 | Pobieranie XML i PDF faktury kosztowej | 🟡 | M |
| 7.1.4 | Automatyczny polling nowych faktur kosztowych (cron co 15 min) | 🟡 | M |

---

## FAZA 8 — Jakość, DevOps, CI

### EPIC 8.1 — Testy

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 8.1.1 | Testy funkcjonalne `WebTestCase` dla wszystkich 5 endpointów | 🟠 | L |
| 8.1.2 | Testy jednostkowe dla nowych handlerów (Faza 0–2) | 🟠 | M |
| 8.1.3 | Włączyć `tests/` do PHPStan | 🟡 | XS |
| 8.1.4 | Testy E2E (Playwright) dla kluczowych flows: wysyłka faktury, lista | 🟡 | XL |

### EPIC 8.2 — CI/CD

| ID | Zadanie | Priorytet | Effort |
|----|---------|-----------|--------|
| 8.2.1 | GitHub Actions: PHPStan + CS Fixer + PHPUnit na każdy PR | 🟠 | M |
| 8.2.2 | Docker image build + push do registry na merge do `main` | 🟡 | M |

---

## Mapa drogowa (roadmap)

```
Q1 2026  │  FAZA 0 (stabilizacja) + FAZA 1 (dashboard)
Q2 2026  │  FAZA 2 (formularz faktury) + FAZA 3 (kontrahenci) + FAZA 4 (statusy)
Q3 2026  │  FAZA 5 (raporty) + FAZA 6 (ustawienia) + FAZA 8 (CI)
Q4 2026  │  FAZA 7 (faktury przychodzące) + mobile / PWA
```

---

## Legenda

| Symbol | Znaczenie |
|--------|-----------|
| 🔴 | Krytyczne — blokuje wartość produktu |
| 🟠 | Poważne — wpływa na jakość UX |
| 🟡 | Dług techniczny / nice-to-have |
| XS | < 2h | S | 2–4h | M | 4–8h | L | 1–3 dni | XL | > 3 dni |
