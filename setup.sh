#!/bin/bash
set -e

echo "🚀 OpenMES Quick Setup"
echo "====================="

# Create .env from example if it doesn't exist
if [ ! -f backend/.env ]; then
    echo "📝 Creating .env from .env.example..."
    cp backend/.env.example backend/.env
else
    echo "✅ .env already exists"
fi

# Start containers
echo "🐳 Starting Docker containers..."
docker-compose up -d --build

# Wait for backend to be ready
echo "⏳ Waiting for backend to start..."
sleep 5

# Generate APP_KEY if not set
echo "🔑 Checking APP_KEY..."
if ! docker-compose exec -T backend grep -q "APP_KEY=base64:" backend/.env 2>/dev/null; then
    echo "🔑 Generating APP_KEY..."
    docker-compose exec -T backend php artisan key:generate --force
fi

echo ""
echo "✅ Setup complete!"
echo "🌐 Open http://localhost in your browser"
echo ""
