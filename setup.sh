#!/bin/bash
set -e

echo "🚀 OpenMES WordPress-Style Setup"
echo "=================================="
echo ""

# Start containers
echo "🐳 Starting Docker containers..."
docker-compose up -d --build

# Wait for backend to be ready
echo "⏳ Waiting for backend to start (this may take 30-60 seconds for first build)..."
echo ""

# Wait for backend to be healthy
for i in {1..60}; do
    if docker-compose exec -T backend test -f artisan 2>/dev/null; then
        echo "✅ Backend is ready!"
        break
    fi
    echo -n "."
    sleep 1
done

echo ""
echo ""
echo "✅ Setup complete!"
echo "=================================="
echo ""
echo "🌐 NEXT STEP: Open http://localhost in your browser"
echo ""
echo "You will see a 3-step installation wizard:"
echo "  1️⃣  Basic Configuration (Site Name, URL)"
echo "  2️⃣  Database Setup (use credentials from docker-compose.yml)"
echo "  3️⃣  Create Admin Account"
echo ""
echo "Default database credentials:"
echo "  Host: postgres"
echo "  Port: 5432"
echo "  Database: openmmes"
echo "  Username: openmmes_user"
echo "  Password: openmmes_secret"
echo ""
echo "🎉 That's it! No more CLI commands needed!"
echo ""
