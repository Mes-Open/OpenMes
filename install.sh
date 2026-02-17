#!/bin/bash

# OpenMES - Simple Installation Script
# Like WordPress, but for Manufacturing :)

set -e

echo "=========================================="
echo "   OpenMES - Installation Wizard"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: Docker is not installed!${NC}"
    echo "Please install Docker first: https://docs.docker.com/get-docker/"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Error: Docker Compose is not installed!${NC}"
    echo "Please install Docker Compose first: https://docs.docker.com/compose/install/"
    exit 1
fi

echo -e "${GREEN}✓ Docker is installed${NC}"
echo ""

# Check if already installed
if [ -f ".env" ] && [ -f "backend/.env" ]; then
    echo -e "${YELLOW}Warning: OpenMES appears to be already installed.${NC}"
    read -p "Do you want to reinstall? This will DELETE all data! (yes/no): " REINSTALL
    if [ "$REINSTALL" != "yes" ]; then
        echo "Installation cancelled."
        exit 0
    fi
    echo ""
    echo "Stopping and removing existing containers..."
    docker-compose down -v
    echo ""
fi

# Configuration prompts
echo "Let's configure your OpenMES installation:"
echo ""

# Database Password
read -p "Database Password [default: openmmes_secure_password]: " DB_PASSWORD
DB_PASSWORD=${DB_PASSWORD:-openmmes_secure_password}

# Admin Password
read -p "Admin Password [default: admin123]: " ADMIN_PASSWORD
ADMIN_PASSWORD=${ADMIN_PASSWORD:-admin123}

# Application URL
read -p "Application URL [default: http://localhost]: " APP_URL
APP_URL=${APP_URL:-http://localhost}

# Environment (local/production)
read -p "Environment (local/production) [default: local]: " APP_ENV
APP_ENV=${APP_ENV:-local}

if [ "$APP_ENV" = "production" ]; then
    APP_DEBUG=false
else
    APP_DEBUG=true
fi

echo ""
echo "Configuration summary:"
echo "  - Database Password: ********"
echo "  - Admin Password: ********"
echo "  - Application URL: $APP_URL"
echo "  - Environment: $APP_ENV"
echo ""

read -p "Proceed with installation? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Installation cancelled."
    exit 0
fi

echo ""
echo "Installing OpenMES..."
echo ""

# Create root .env
cat > .env << EOF
# Application
APP_NAME=OpenMES
APP_ENV=$APP_ENV
APP_KEY=
APP_DEBUG=$APP_DEBUG
APP_URL=$APP_URL

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=openmmes
DB_USERNAME=openmmes_user
DB_PASSWORD=$DB_PASSWORD

# Authentication
SANCTUM_STATEFUL_DOMAINS=localhost,192.168.1.*
SESSION_DRIVER=cookie
SESSION_LIFETIME=120

# Default Admin
DEFAULT_ADMIN_USERNAME=admin
DEFAULT_ADMIN_EMAIL=admin@openmmes.local
DEFAULT_ADMIN_PASSWORD=$ADMIN_PASSWORD

# System Settings
ALLOW_OVERPRODUCTION=false
FORCE_SEQUENTIAL_STEPS=true
EOF

echo -e "${GREEN}✓ Created root .env file${NC}"

# Create backend .env
cat > backend/.env << EOF
APP_NAME=OpenMES
APP_ENV=$APP_ENV
APP_KEY=
APP_DEBUG=$APP_DEBUG
APP_URL=$APP_URL

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=openmmes
DB_USERNAME=openmmes_user
DB_PASSWORD=$DB_PASSWORD

SESSION_DRIVER=cookie
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"
EOF

echo -e "${GREEN}✓ Created backend .env file${NC}"

# Build containers
echo ""
echo "Building Docker containers (this may take a few minutes)..."
docker-compose build --no-cache backend

echo -e "${GREEN}✓ Backend container built${NC}"

# Start containers
echo ""
echo "Starting containers..."
docker-compose up -d

echo -e "${GREEN}✓ Containers started${NC}"

# Wait for database
echo ""
echo "Waiting for database to be ready..."
sleep 10

# Generate APP_KEY
echo "Generating application key..."
docker-compose exec -T backend php artisan key:generate --force

echo -e "${GREEN}✓ Application key generated${NC}"

# Run migrations
echo ""
echo "Setting up database..."
docker-compose exec -T backend php artisan migrate:fresh --seed --force

echo ""
echo -e "${GREEN}=========================================="
echo "   ✓ Installation Complete!"
echo "==========================================${NC}"
echo ""
echo "Your OpenMES installation is ready!"
echo ""
echo "  🌐 URL: $APP_URL"
echo "  👤 Username: admin"
echo "  🔑 Password: $ADMIN_PASSWORD"
echo ""
echo -e "${YELLOW}⚠️  IMPORTANT: Change the admin password after first login!${NC}"
echo ""
echo "To start/stop OpenMES:"
echo "  docker-compose up -d    # Start"
echo "  docker-compose down     # Stop"
echo ""
echo "To view logs:"
echo "  docker-compose logs -f backend"
echo ""
echo "Enjoy OpenMES! 🏭"
echo ""
