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
- `setup.sh` (opcjonalny skrypt instalacyjny)
- `docker-compose.yml` (konfiguracja Docker)
- katalogi: `backend/`, `nginx/`, `docs/`

---

## Krok 3: Uruchom Docker Compose

**WAŻNE:** Instalacja jest w 100% przez przeglądarkę - jak WordPress!

```bash
docker-compose up -d
```

**Oczekiwany output:**
```
Creating network "openmmes-network" with driver "bridge"
Creating volume "openmmes_postgres_data" with local driver
Creating openmmes-postgres ... done
Creating openmmes-backend  ... done
Creating openmmes-nginx    ... done
```

---

## Krok 4: Sprawdź status kontenerów

```bash
docker-compose ps
```

**Oczekiwany output:**
Wszystkie kontenery (3 sztuki) powinny mieć status `Up`:
```
NAME                 STATUS                   PORTS
openmmes-postgres    Up (healthy)            5432/tcp
openmmes-backend     Up                      8000/tcp
openmmes-nginx       Up                      0.0.0.0:80->80/tcp
```

**Jeśli któryś kontener nie działa:**
```bash
# Zobacz logi
docker-compose logs backend
docker-compose logs postgres
```

---

## Krok 5: Poczekaj na inicjalizację (10-20 sekund)

Zaczekaj chwilę, aż backend zbuduje assety i uruchomi się.

Możesz sprawdzić logi:
```bash
docker-compose logs -f backend
```

Poczekaj aż zobaczysz:
```
INFO  Server running on [http://0.0.0.0:8000]
```

Przerwij przeglądanie logów: `Ctrl+C`

---

## Krok 6: Instalacja przez przeglądarkę (jak WordPress!)

### 6.1 Otwórz instalator

Otwórz w przeglądarce:
```
http://localhost
```

**Zostaniesz automatycznie przekierowany do instalatora.**

---

### 6.2 Krok 1 z 3: Podstawowa konfiguracja

**Formularz:**
- **Site Name**: `OpenMES` (lub dowolna nazwa)
- **Site URL**: `http://localhost` (lub twój adres)

**Kliknij:** `Continue →`

System automatycznie:
- ✅ Utworzy plik `.env`
- ✅ Wygeneruje klucz szyfrowania (APP_KEY)
- ✅ Skonfiguruje podstawowe ustawienia

---

### 6.3 Krok 2 z 3: Konfiguracja bazy danych

**Formularz:**
- **Database Host**: `postgres`
- **Database Port**: `5432`
- **Database Name**: `openmmes`
- **Database Username**: `openmmes_user`
- **Database Password**: `openmmes_secret`

> **Dane z `docker-compose.yml`** - używaj dokładnie tych wartości!

**Kliknij:** `Continue →`

System automatycznie:
- ✅ Testuje połączenie (30 sekund timeout)
- ✅ Tworzy wszystkie tabele (migracje)
- ✅ Dodaje podstawowe dane (role, uprawnienia, typy problemów)

**Jeśli widzisz błąd:**
- Sprawdź czy postgres jest `healthy`: `docker-compose ps`
- Sprawdź czy hasło się zgadza z `docker-compose.yml`

---

### 6.4 Krok 3 z 3: Konto administratora

**Formularz - Informacje o stronie:**
- **Site Name**: `OpenMES` (potwierdź lub zmień)
- **Site URL**: `http://localhost` (potwierdź lub zmień)

**Formularz - Konto administratora:**
- **Username**: Twoja nazwa użytkownika (np. `admin`)
- **Email Address**: Twój email (np. `admin@example.com`)
- **Password**: Silne hasło (min. 8 znaków)
- **Confirm Password**: Powtórz hasło

**Kliknij:** `Complete Installation →`

System automatycznie:
- ✅ Tworzy konto administratora
- ✅ Przypisuje rolę Admin
- ✅ Zapisuje konfigurację do `.env`
- ✅ Oznacza instalację jako zakończoną

---

### 6.5 Instalacja zakończona! 🎉

Zobaczysz stronę potwierdzenia z linkiem do logowania.

**Kliknij:** `Go to Login →`

---

## Krok 7: Pierwsze logowanie

### 7.1 Zaloguj się

**Dane logowania:**
- **Username**: To co wpisałeś w kroku 6.4
- **Password**: To co wpisałeś w kroku 6.4

**Kliknij:** `Login`

### 7.2 Wybierz linię produkcyjną

Po zalogowaniu zobaczysz ekran wyboru linii.

> **Na razie lista będzie pusta** - to normalne!
> Najpierw musisz dodać linie produkcyjne w panelu admina.

---

## Krok 8: Testy funkcjonalne

### 8.1 Test: Panel admina

Aby dodać pierwszą linię produkcyjną:
1. Zaloguj się jako admin
2. Kliknij swoje imię w prawym górnym rogu
3. Wybierz "Admin Panel" (gdy będzie dostępny)
4. Dodaj linię produkcyjną

### 8.2 Test: Kolejka zleceń

Po dodaniu linii:
- Wybierz linię produkcyjną
- Powinieneś zobaczyć pustą listę Work Orders
- Import CSV lub ręczne dodawanie Work Orders w panelu admina

### 8.3 Test: PWA (Opcjonalne)

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

### ❌ Błąd 500 po instalacji

```bash
# Sprawdź logi backend
docker-compose logs backend | tail -50

# Zrestartuj backend
docker-compose restart backend

# Jeśli problem nadal występuje, przebuduj
docker-compose build --no-cache backend
docker-compose up -d backend
```

### ❌ Nie można otworzyć instalatora (błąd APP_KEY)

```bash
# Przebuduj kontener (APP_KEY jest generowany podczas budowania)
docker-compose down
docker-compose build --no-cache backend
docker-compose up -d

# Lub użyj skryptu setup.sh
./setup.sh
```

---

## Czyszczenie i restart od zera

Jeśli coś poszło nie tak i chcesz zacząć od początku:

```bash
# Zatrzymaj wszystko i usuń dane
docker-compose down -v

# Usuń plik installed (oznaczenie instalacji)
docker-compose run --rm backend rm -f storage/installed

# Usuń plik .env jeśli istnieje
rm -f backend/.env

# Uruchom ponownie
docker-compose up -d

# Otwórz http://localhost i przejdź przez instalator ponownie
```

---

## Potwierdzenie sukcesu ✅

Instalacja powiodła się, jeśli:

1. ✅ `docker-compose ps` pokazuje 3 kontenery jako `Up` (postgres, backend, nginx)
2. ✅ `http://localhost` przekierowuje do instalatora (przed instalacją)
3. ✅ Po zakończeniu instalatora widzisz stronę logowania
4. ✅ Możesz się zalogować swoimi danymi
5. ✅ Widzisz ekran wyboru linii produkcyjnej po zalogowaniu

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
docker-compose exec backend cat .env
```

3. Zgłoś problem na GitHub: https://github.com/Mes-Open/OpenMes/issues

Załącz:
- Output z `docker-compose ps`
- Logi (backend/frontend/postgres)
- Treść pliku .env (bez haseł!)
- System operacyjny i wersje Docker

---

**Powodzenia! 🚀**
