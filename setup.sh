#!/bin/bash

set -e

echo "🏭 OpenMES Setup Script"
echo "======================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if .env exists
if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file from .env.example...${NC}"
    cp .env.example .env
    echo -e "${GREEN}✓ .env file created${NC}"
else
    echo -e "${GREEN}✓ .env file already exists${NC}"
fi

# Check if backend/.env exists
if [ ! -f backend/.env ]; then
    echo -e "${YELLOW}Creating backend/.env file...${NC}"
    cp backend/.env.example backend/.env
    echo -e "${GREEN}✓ backend/.env file created${NC}"
else
    echo -e "${GREEN}✓ backend/.env file already exists${NC}"
fi

# Generate APP_KEY if not set
if ! grep -q "APP_KEY=base64:" backend/.env; then
    echo -e "${YELLOW}Generating Laravel APP_KEY...${NC}"

    # Generate a random base64 key
    APP_KEY=$(openssl rand -base64 32)

    # Update backend/.env
    sed -i "s|APP_KEY=|APP_KEY=base64:${APP_KEY}|" backend/.env

    # Update root .env
    sed -i "s|APP_KEY=|APP_KEY=base64:${APP_KEY}|" .env

    echo -e "${GREEN}✓ APP_KEY generated${NC}"
else
    echo -e "${GREEN}✓ APP_KEY already set${NC}"
fi

# Sync DB credentials between root .env and backend/.env
echo -e "${YELLOW}Syncing database credentials...${NC}"
DB_PASSWORD=$(grep "^DB_PASSWORD=" .env | cut -d '=' -f2)

# Update backend/.env with the same password
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" backend/.env

echo -e "${GREEN}✓ Database credentials synced${NC}"
echo ""

echo -e "${GREEN}Setup complete!${NC}"
echo ""
echo "Next steps:"
echo "1. Edit .env and set your DB_PASSWORD and DEFAULT_ADMIN_PASSWORD"
echo "2. Run: docker-compose up -d"
echo "3. Run: docker-compose exec backend php artisan migrate:fresh --seed"
echo "4. Access the app at http://localhost"
echo ""
echo "Default login: admin / CHANGE_ON_FIRST_LOGIN"
