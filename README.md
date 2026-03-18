# KSeF — Krajowy System e-Faktur

Symfony 8 application for sending and receiving structured invoices (FA(3)) via the Polish Ministry of Finance e-invoicing API (KSeF).

## Features

- XAdES digital signature authentication (RSA/ECDSA + X509 certificate)
- Invoice encryption (AES-256-CBC key wrapped with RSA OAEP SHA-256)
- Online session management with async status polling
- Web dashboard: invoice list, XML download, PDF preview
- FA(3) invoice XML parsing and validation

## Tech Stack

| Component | Version |
|-----------|---------|
| PHP | 8.5 |
| Symfony | 8 |
| PostgreSQL | 18 |
| PHPUnit | 12 |
| PHPStan | level 8 |

## Architecture

DDD + CQRS with two bounded contexts:

- **`src/Backend/`** — KSeF API integration: authentication, invoice encryption, session management, XML parsing
- **`src/Frontend/`** — Web dashboard: ADR action classes, Twig templates, PDF generation, JSON persistence

```
src/
├── Backend/
│   ├── Shared/          # KsefApi interface, KsefApiClient, shared exceptions
│   ├── Authentication/  # XAdES auth flow, AccessTokenStore
│   ├── Invoice/         # Send invoice flow, AES/RSA encryption
│   └── Parser/          # FA(3) XML parser and validator
└── Frontend/
    └── Dashboard/
        ├── UI/Action/   # 5 ADR action classes (one per route)
        ├── Application/ # GetInvoiceOverviewHandler, RenderInvoicePdfHandler
        ├── Domain/      # SubmittedInvoice
        └── Infrastructure/ # SubmittedInvoiceRepository (JSON)
```

## Local Setup

### Prerequisites
- Docker + Docker Compose

### 1. Configure environment

```bash
cp docker-compose.yml.dist docker-compose.yml
```

Edit `docker-compose.yml` and set:
- `KSEF_NIP` — your NIP (tax ID)
- `KSEF_CERTIFICATE_PATH` — path to your X509 certificate (PEM)
- `KSEF_CERTIFICATE_KEY_PATH` — path to your private key (PEM)
- `KSEF_CERTIFICATE_PASSWORD` — private key password (if encrypted)
- `APP_SECRET` — random secret string

### 2. Start the application

```bash
make start
```

Dashboard available at `http://localhost:8099`

### 3. Run tests

```bash
make tests
make phpstan
```

## Web Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Dashboard |
| POST | `/send` | Submit invoice (form upload or raw XML body) |
| GET | `/invoices/rows` | Invoice list (AJAX, JSON) |
| GET | `/invoices/download/{ksefNumber}` | Download invoice XML |
| GET | `/invoices/download/{ksefNumber}/pdf` | Render invoice PDF |

## KSeF API

Base URL: `https://api-test.ksef.mf.gov.pl/v2` (test environment)

Authentication flow:
1. `POST /auth/challenge` — request challenge
2. Sign challenge XML with XAdES + X509 certificate
3. `POST /auth/xades-signature` — init authentication
4. Poll `GET /auth/{referenceNumber}` until status 200
5. `POST /auth/token/redeem` — exchange for access token

## Makefile Commands

```bash
make start          # Build and start Docker services
make tests          # Run PHPUnit test suite
make phpstan        # PHPStan static analysis (level 8)
make csfixer        # PHP CS Fixer dry run
make cc             # Clear Symfony cache
make router         # List registered routes
make logs           # Follow Docker logs
```

## Contact

- [GitHub](https://github.com/JakubSzczerba)
- [LinkedIn](https://www.linkedin.com/in/jakub-szczerba-3492751b4/)
