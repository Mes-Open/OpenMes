# 🧪 Instrukcja testowania instalacji OpenMES

## Krok 1: Przygotowanie środowiska

Upewnij się, że masz zainstalowane:
- ✅ Docker (wersja 20.10+)
- ✅ Docker Compose
- ✅ Git

Sprawdź wersje:
```bash
docker --version
docker-compose --version
git --version
```

---

## Krok 2: Sklonuj repozytorium (świeża kopia)

```bash
# Wejdź do katalogu domowego lub innego wybranego miejsca
cd ~

# Sklonuj projekt
git clone https://github.com/Mes-Open/OpenMes.git

# Wejdź do katalogu
cd OpenMes

# Sprawdź, czy wszystkie pliki są na miejscu
ls -la
```

**Oczekiwany output:**
Powinieneś zobaczyć:
- `setup.sh` (skrypt instalacyjny)
- `docker-compose.yml` (konfiguracja Docker)
- `.env.example` (przykładowy plik środowiskowy)
- katalogi: `backend/`, `frontend/`, `nginx/`, `docs/`

---

## Krok 3: Uruchom skrypt setup

```bash
./setup.sh
```

**Oczekiwany output:**
```
🏭 OpenMES Setup Script
======================

Creating .env file from .env.example...
✓ .env file created
Creating backend/.env file...
✓ backend/.env file created
Generating Laravel APP_KEY...
✓ APP_KEY generated
Syncing database credentials...
✓ Database credentials synced

Setup complete!
```

---

## Krok 4: (Opcjonalne) Zmień hasła

```bash
nano .env
```

Zmień:
- `DB_PASSWORD=CHANGE_ME_SECURE_PASSWORD` → Twoje hasło do bazy
- `DEFAULT_ADMIN_PASSWORD=CHANGE_ON_FIRST_LOGIN` → Twoje hasło admina

Zapisz (Ctrl+O, Enter) i wyjdź (Ctrl+X)

---

## Krok 5: Uruchom Docker Compose

```bash
docker-compose up -d
```

**Oczekiwany output:**
```
Creating network "openmmes-network" with driver "bridge"
Creating volume "openmmes_postgres_data" with local driver
Creating openmmes-postgres ... done
Creating openmmes-backend  ... done
Creating openmmes-frontend ... done
Creating openmmes-nginx    ... done
```

---

## Krok 6: Sprawdź status kontenerów

```bash
docker-compose ps
```

**Oczekiwany output:**
Wszystkie kontenery powinny mieć status `Up` i `healthy`:
```
NAME                 STATUS                   PORTS
openmmes-postgres    Up (healthy)            5432/tcp
openmmes-backend     Up                      8000/tcp
openmmes-frontend    Up                      5173/tcp
openmmes-nginx       Up                      80/tcp
```

**Jeśli któryś kontener nie działa:**
```bash
# Zobacz logi
docker-compose logs backend
docker-compose logs frontend
docker-compose logs postgres
```

---

## Krok 7: Poczekaj na inicjalizację (30-60 sekund)

Zaczekaj chwilę, aż wszystkie serwisy się uruchomią.

Możesz sprawdzić logi:
```bash
# Backend
docker-compose logs -f backend

# Frontend
docker-compose logs -f frontend
```

Przerwij przeglądanie logów: `Ctrl+C`

---

## Krok 8: Uruchom migracje bazy danych

```bash
docker-compose exec backend php artisan migrate:fresh --seed
```

**Oczekiwany output:**
```
Dropping all tables ............................. DONE
Migration table created successfully.
Migrating: ...
Migrated:  ... (0.XX seconds)
...
Database seeding completed successfully.
```

**To polecenie:**
- Tworzy wszystkie tabele w bazie danych
- Dodaje dane testowe (admin user, przykładowe linie produkcyjne, itp.)

---

## Krok 9: Testowanie dostępu

### 9.1 Sprawdź Backend API

```bash
curl http://localhost:8000/api/health
```

**Oczekiwany output:**
```json
{"status":"ok","timestamp":"2024-..."}
```

### 9.2 Sprawdź Frontend

Otwórz w przeglądarce:
```
http://localhost
```

**Powinieneś zobaczyć:**
- Stronę logowania OpenMES
- Pola: Username, Password
- Przycisk "Login"

### 9.3 Zaloguj się

**Dane logowania:**
- Username: `admin`
- Password: `CHANGE_ON_FIRST_LOGIN` (lub to co ustawiłeś w .env)

**Po zalogowaniu:**
- System powinien poprosić o zmianę hasła
- Ustaw nowe hasło
- Zostaniesz przekierowany do panelu operatora

---

## Krok 10: Testy funkcjonalne

### 10.1 Test: Lista linii produkcyjnych

Po zalogowaniu powinieneś zobaczyć:
- Ekran wyboru linii produkcyjnej
- Lista dostępnych linii (jeśli są w bazie)

### 10.2 Test: Kolejka zleceń

- Wybierz linię produkcyjną
- Powinieneś zobaczyć listę Work Orders
- Kliknij na Work Order, aby zobaczyć szczegóły

### 10.3 Test: PWA (Opcjonalne)

W Chrome/Edge:
- Kliknij ikonę instalacji w pasku adresu (⊕ lub ikona komputera)
- Zainstaluj aplikację
- Uruchom jako standalone app

---

## Sprawdzenie zainstalowanych komponentów

### Sprawdź tabele w bazie danych

```bash
docker-compose exec postgres psql -U openmmes_user -d openmmes -c "\dt"
```

**Powinieneś zobaczyć tabele:**
- users
- roles
- lines
- work_orders
- batches
- batch_steps
- issues
- audit_logs
- event_logs
- ... i inne

### Sprawdź seedowane dane

```bash
docker-compose exec backend php artisan tinker
```

Następnie w tinkerze:
```php
App\Models\User::count();  // Powinno być >= 1
App\Models\Line::count();  // Zależy od seedera
exit
```

---

## Zatrzymanie aplikacji

```bash
# Zatrzymaj wszystkie kontenery
docker-compose down

# Zatrzymaj i usuń volumes (UWAGA: skasuje dane!)
docker-compose down -v
```

---

## Problemy i rozwiązania

### ❌ Port 80 jest zajęty

```bash
# Zmień port nginx w docker-compose.yml
# Z:
    ports:
      - "80:80"
# Na:
    ports:
      - "8080:80"

# Restart
docker-compose down
docker-compose up -d

# Dostęp przez http://localhost:8080
```

### ❌ Backend nie może połączyć się z bazą

```bash
# Sprawdź hasło
grep DB_PASSWORD .env
grep DB_PASSWORD backend/.env

# Powinny być identyczne!

# Restart backenda
docker-compose restart backend
```

### ❌ Frontend pokazuje błąd 404 dla API

```bash
# Sprawdź VITE_API_URL
grep VITE_API .env

# Powinno być:
VITE_API_URL=http://localhost:8000

# Rebuild frontend
docker-compose build frontend
docker-compose up -d frontend
```

### ❌ Brak permisji do setup.sh

```bash
chmod +x setup.sh
./setup.sh
```

---

## Czyszczenie i restart od zera

Jeśli coś poszło nie tak i chcesz zacząć od początku:

```bash
# Zatrzymaj wszystko
docker-compose down -v

# Usuń pliki .env
rm .env backend/.env

# Uruchom setup ponownie
./setup.sh

# Kontynuuj od kroku 5
docker-compose up -d
```

---

## Potwierdzenie sukcesu ✅

Instalacja powiodła się, jeśli:

1. ✅ `docker-compose ps` pokazuje wszystkie kontenery jako `Up`
2. ✅ `curl http://localhost:8000/api/health` zwraca JSON
3. ✅ `http://localhost` pokazuje stronę logowania
4. ✅ Możesz się zalogować jako admin
5. ✅ Widzisz panel operatora po zalogowaniu

---

## Raportowanie problemów

Jeśli coś nie działa:

1. Uruchom diagnostykę:
```bash
docker-compose ps
docker-compose logs backend | tail -50
docker-compose logs frontend | tail -50
docker-compose logs postgres | tail -50
```

2. Sprawdź konfigurację:
```bash
cat .env
cat backend/.env
```

3. Zgłoś problem na GitHub: https://github.com/Mes-Open/OpenMes/issues

Załącz:
- Output z `docker-compose ps`
- Logi (backend/frontend/postgres)
- Treść pliku .env (bez haseł!)
- System operacyjny i wersje Docker

---

**Powodzenia! 🚀**
