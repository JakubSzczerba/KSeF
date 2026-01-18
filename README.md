# Integracja z KseF: Krajowy System e-Faktur.

## 📌 Opis projektu

> Wysyłanie faktur ustukturyzowanych [Fa(3)] do Ministerstwa
> Odbieranie/Pobieranie faktur oraz zarządzanie obiegiem dokumentów

- Planowanie i zarządzanie misjami dronów,
- Monitorowanie statusu oraz pozycji dronów w czasie rzeczywistym,
- Integrację z infrastrukturą IoT poprzez MQTT,
- Symulację misji dla testowania zachowania roju,
- Eksponowanie API dla zewnętrznych systemów zarządzania.

## 🛠 Technologia

### **Backend**
- PHP 8.5 (Symfony 8)
- PostgreSQL 8

## Local Setup

**1. Copy the `docker-compose.yml.dist` file to `docker-compose.yml`:**
```
cp docker-compose.yml.dist docker-compose.yml
```
**2. Replace placeholder values in the docker-compose.yml file with your actual values:**
- `POSTGRES_USER` with your database username
- `POSTGRES_PASSWORD` with your database password
- `APP_SECRET` with your application secret
- `DATABASE_URL` with your actual database connection string

**3. Start the application:**
```
make start
```

**4. Compile resources:**
```
make migrate
```

**5. Run tests:**
```
make tests 
```

## Contact
* [GitHub](https://github.com/JakubSzczerba)

* [LinkedIn](https://www.linkedin.com/in/jakub-szczerba-3492751b4/)
