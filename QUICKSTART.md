# OpenMES Quick Start Guide

This guide will get you up and running with OpenMES in under 5 minutes.

## Prerequisites

- Docker and Docker Compose installed
- PostgreSQL client (optional, for manual database access)
- Modern web browser (Chrome/Firefox/Edge)

## Installation Steps

### 1. Set up PostgreSQL Database (Local Development)

If you want to run locally without Docker first:

```bash
# Create PostgreSQL database
createdb openmmes

# Create database user (optional)
psql -c "CREATE USER openmmes_user WITH PASSWORD 'openmmes_secure_password';"
psql -c "GRANT ALL PRIVILEGES ON DATABASE openmmes TO openmmes_user;"
```

### 2. Backend Setup

```bash
cd backend

# Install dependencies
composer install

# The .env file already exists, but verify database settings
cat .env | grep DB_

# Run migrations and seed database
php artisan migrate:fresh --seed

# Start Laravel development server
php artisan serve
```

You should see:
```
INFO  Server running on [http://127.0.0.1:8000].
```

**Test the API:**
```bash
curl http://localhost:8000/api/health
```

### 3. Frontend Setup

Open a new terminal:

```bash
cd frontend

# Install dependencies (already done, but run if needed)
npm install

# Start Vite development server
npm run dev
```

You should see:
```
VITE v5.x.x  ready in xxx ms

➜  Local:   http://localhost:5173/
➜  Network: use --host to expose
```

### 4. Verify Installation

1. **Check Database**
   ```bash
   cd backend
   php artisan db:show
   ```

2. **View Seeded Data**
   ```bash
   php artisan tinker
   >>> User::count()
   => 1
   >>> User::first()
   => App\Models\User {...}
   >>> Role::pluck('name')
   => ["Admin", "Supervisor", "Operator"]
   >>> IssueType::count()
   => 9
   ```

3. **Test Default Admin Login**
   - Username: `admin`
   - Password: `CHANGE_ON_FIRST_LOGIN` (or value from `.env`)

## Docker Setup (Alternative)

If you prefer to run everything in Docker:

### 1. Copy Environment File

```bash
cp .env.example .env
```

### 2. Edit .env File

Update these values:
```env
DB_PASSWORD=your_secure_password
APP_KEY=  # Will be generated
DEFAULT_ADMIN_PASSWORD=your_admin_password
```

### 3. Generate Application Key

```bash
cd backend
php artisan key:generate
# Copy the generated key to .env
```

### 4. Start All Services

```bash
# From project root
docker-compose up -d

# Wait for PostgreSQL to be ready (check logs)
docker-compose logs -f postgres

# Once healthy, run migrations
docker-compose exec backend php artisan migrate:fresh --seed
```

### 5. Access Application

- Frontend: http://localhost (port 80)
- Backend API: http://localhost:8000
- Database: localhost:5432

## Testing the System

### 1. View Database Tables

```bash
cd backend
php artisan migrate:status
```

Expected output:
```
Migration name ................................................ Batch / Status
0001_01_01_000000_create_users_table .......................... [1] Ran
0001_01_01_000001_create_cache_table .......................... [1] Ran
...
2026_02_16_210135_create_work_orders_table .................... [1] Ran
```

### 2. Check Roles and Permissions

```bash
php artisan tinker
```

```php
>>> $admin = User::first()
>>> $admin->username
=> "admin"

>>> $admin->roles->pluck('name')
=> ["Admin"]

>>> $admin->getAllPermissions()->pluck('name')
=> ["view work orders", "create work orders", ...]
```

### 3. Test Audit Log Immutability

```bash
php artisan tinker
```

```php
>>> $log = \App\Models\AuditLog::create([
...   'user_id' => 1,
...   'entity_type' => 'Test',
...   'entity_id' => 1,
...   'action' => 'created',
... ]);

>>> $log->delete()
// Should throw exception: "Audit logs are immutable"
```

### 4. View System Settings

```bash
php artisan tinker
```

```php
>>> DB::table('system_settings')->get()
=> [
  {
    "key": "allow_overproduction",
    "value": "false",
    ...
  },
  {
    "key": "force_sequential_steps",
    "value": "true",
    ...
  }
]
```

## Common Issues and Solutions

### Issue: Migration fails with "relation already exists"

**Solution:**
```bash
php artisan migrate:fresh --seed
```
This drops all tables and recreates them.

### Issue: PostgreSQL connection refused

**Solution:**
1. Check PostgreSQL is running: `pg_isready`
2. Verify credentials in `.env`
3. Check PostgreSQL is listening: `netstat -an | grep 5432`

### Issue: "Class 'Laravel\Sanctum\Sanctum' not found"

**Solution:**
```bash
composer install
php artisan config:clear
php artisan cache:clear
```

### Issue: Frontend can't connect to backend

**Solution:**
1. Check backend is running: `curl http://localhost:8000/api/health`
2. Verify `VITE_API_URL` in frontend `.env`
3. Check CORS settings in `config/cors.php`

## Next Steps

Once you've verified the installation:

1. **Explore the Database Schema**
   - Use a database client (DBeaver, pgAdmin, DataGrip)
   - Connect to: `localhost:5432`, database: `openmmes`

2. **Review the Migrations**
   - Check `backend/database/migrations/` for table structures

3. **Understand the Seeders**
   - Review `backend/database/seeders/` for initial data

4. **Start Development**
   - See `IMPLEMENTATION_STATUS.md` for next steps
   - Begin with Phase 2: Work Order Core

## Useful Commands

### Laravel
```bash
# Clear all caches
php artisan optimize:clear

# View routes
php artisan route:list

# Create a new controller
php artisan make:controller Api/V1/WorkOrderController --api

# Create a new model with migration
php artisan make:model WorkOrder -m

# Run specific seeder
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Docker
```bash
# View logs
docker-compose logs -f backend

# Execute artisan commands
docker-compose exec backend php artisan migrate

# Access PostgreSQL shell
docker-compose exec postgres psql -U openmmes_user -d openmmes

# Rebuild containers
docker-compose build --no-cache

# Stop and remove all containers
docker-compose down -v
```

### Database
```bash
# Backup database
pg_dump -U openmmes_user openmmes > backup.sql

# Restore database
psql -U openmmes_user openmmes < backup.sql

# View all tables
psql -U openmmes_user -d openmmes -c "\dt"
```

## Support

For issues or questions:
- Check `IMPLEMENTATION_STATUS.md` for known issues
- Review `README.md` for detailed documentation
- Check Laravel logs: `backend/storage/logs/laravel.log`
- Check Docker logs: `docker-compose logs`

---

**You're ready to start developing OpenMES!**
