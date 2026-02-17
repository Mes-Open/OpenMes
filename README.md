<div align="center">

# 🏭 OpenMES

### Open-Source Manufacturing Execution System

*Powerful, flexible, and tablet-ready MES for small manufacturers*

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-336791?logo=postgresql&logoColor=white)](https://www.postgresql.org)

</div>

---

## 📋 What is OpenMES?

**OpenMES** is a modern, open-source Manufacturing Execution System designed specifically for **small manufacturers** (woodworking, metalworking, assembly shops) who need powerful production tracking without enterprise complexity.

### Why OpenMES?

- 🎯 **Purpose-built for small manufacturers** - No bloat, just what you need
- 📱 **Tablet-first design** - Touch-optimized for shop floor operators
- 🔒 **Security-first** - OWASP Top 10 compliant from day one
- 📊 **Real-time visibility** - Know exactly what's happening on every line
- 🆓 **Truly open-source** - MIT licensed, no vendor lock-in
- 🚀 **Deploy in minutes** - Single command Docker deployment

---

## ✨ Features

### 🏭 Production Management

- **Multi-line production** - Manage multiple production lines simultaneously
- **Work order tracking** - Complete work order lifecycle management
- **Batch production** - Support partial completion with multiple batches
- **Process templates** - Reusable, step-by-step process definitions
- **CSV Import** - Bulk import work orders with flexible column mapping
- **Real-time status** - Live production status updates

### 👷 Operator Experience

- **Step-by-step guidance** - Clear instructions for every operation
- **Sequential workflow** - Enforce process order to prevent mistakes
- **One-tap actions** - Start, complete, report issues with single tap
- **PWA support** - Install on tablets, works offline
- **Offline mode** - Queue actions when network is unavailable
- **Tablet-optimized** - Large touch targets (48px+), minimal text input

### 🔔 Issue & Andon System

- **Problem reporting** - Operators report issues instantly from any step
- **Automatic blocking** - Critical issues halt production automatically
- **Issue escalation** - Route problems to supervisors with notifications
- **Resolution tracking** - Complete issue lifecycle (Open → Acknowledged → Resolved → Closed)
- **Predefined categories** - Material shortage, quality issues, tool failures, etc.

### 📊 Analytics & Reporting

- **Supervisor Dashboard** - Real-time KPIs and production metrics
- **Interactive Charts** - Throughput, cycle time, issue trends, step performance
- **Production Reports** - Summary, batch completion, downtime reports
- **CSV Export** - Export all reports for further analysis
- **Traceability** - Complete audit trail for every action

### 🔐 Security & Compliance

- **Immutable audit logs** - PostgreSQL-enforced, cannot be altered
- **Complete traceability** - Track every action, user, and timestamp
- **Role-based access** - Admin, Supervisor, Operator roles
- **Line-based filtering** - Operators only see assigned lines
- **Compliance-ready** - ISO 9001, AS9100 compatible audit trail

---

## 🏗️ Architecture

OpenMES uses a traditional Laravel monolith architecture optimized for simplicity and ease of deployment:

- **Backend**: Laravel 12 with Blade templates for server-side rendering
- **Frontend**: Tailwind CSS 4 + Alpine.js for interactivity
- **Real-time Updates**: Livewire 4 for dynamic components (batch steps, dashboards)
- **Charts**: Chart.js for analytics visualization
- **Database**: PostgreSQL 14+ with immutable audit logs
- **Deployment**: Docker Compose (3 containers: nginx, backend, postgres)

### Why This Architecture?

- **Simple Deployment**: 3 containers instead of 4 (no separate frontend)
- **Fast Installation**: No frontend build step in development
- **Easier Maintenance**: Single codebase, traditional Laravel patterns
- **Better for LAN**: Server-rendered pages work reliably in local networks
- **Still Mobile-Ready**: Responsive Blade templates work perfectly on tablets

---

## 🚀 Installation

### Prerequisites

- Docker & Docker Compose (20.10+)
- Git

### Quick Start (Recommended)

```bash
# 1. Clone the repository
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes

# 2. Run setup script
./setup.sh

# 3. (Optional) Edit .env to change passwords
nano .env  # Set DB_PASSWORD and DEFAULT_ADMIN_PASSWORD

# 4. Start all services
docker-compose up -d

# 5. Wait for containers to be healthy (30-60 seconds)
docker-compose ps

# 6. Run database migrations and seed data
docker-compose exec backend php artisan migrate:fresh --seed

# 7. Access the application
# Application: http://localhost
# Default login: admin / CHANGE_ON_FIRST_LOGIN
```

**That's it!** 🎉 OpenMES is now running.

### Manual Setup (Alternative)

If you prefer manual setup:

```bash
# 1. Clone repository
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes

# 2. Copy environment files
cp .env.example .env
cp backend/.env.example backend/.env

# 3. Generate APP_KEY
docker-compose run --rm backend php artisan key:generate

# 4. Update .env with your passwords
nano .env

# 5. Continue from step 4 above
docker-compose up -d
```

### First Steps After Installation

1. **Login** with default credentials (admin / CHANGE_ON_FIRST_LOGIN)
2. **Change your password** when prompted
3. **Create production lines** in the admin panel
4. **Add operators** and assign them to lines
5. **Import work orders** via CSV or create manually
6. **Install PWA on tablets** for offline support

### Troubleshooting

**Containers not starting?**
```bash
# Check container logs
docker-compose logs backend
docker-compose logs nginx
docker-compose logs postgres

# Restart containers
docker-compose restart

# Rebuild containers
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

**Database connection errors?**
```bash
# Make sure postgres is healthy
docker-compose ps

# Check database credentials in .env match docker-compose.yml
grep DB_PASSWORD .env

# Restart backend
docker-compose restart backend
```

**Application not loading?**
```bash
# Check if all services are running
docker-compose ps

# Rebuild backend (includes asset build)
docker-compose build backend
docker-compose up -d backend

# Check nginx logs
docker-compose logs nginx
```

**Port conflicts?**
```bash
# Check if ports are already in use
sudo lsof -i :80    # nginx
sudo lsof -i :8000  # backend
sudo lsof -i :5432  # postgres

# Change ports in docker-compose.yml if needed
```

---

## 📱 PWA Installation (Tablets)

### iOS (iPad)
1. Open Safari and navigate to OpenMES
2. Tap the Share button
3. Select "Add to Home Screen"
4. Name it "OpenMES" and tap Add
5. Launch from home screen

### Android (Tablets)
1. Open Chrome and navigate to OpenMES
2. Tap the menu (⋮)
3. Select "Install app" or "Add to Home Screen"
4. Confirm installation
5. Launch from home screen

**Benefits:**
- Full-screen mode (no browser chrome)
- Works offline with automatic sync
- Native app-like experience
- Touch-optimized for manufacturing floor

---

## 📚 Documentation

- [User Guides](docs/) - Operator, Supervisor, and Admin guides
- [API Documentation](docs/API_DOCUMENTATION.md) - REST API reference
- [PWA Testing Guide](frontend/PWA_TESTING_GUIDE.md) - Offline functionality testing
- [Technical Documentation](docs/development.md) - For developers

---

## 🤝 Contributing

We welcome contributions! Whether it's bug reports, feature requests, documentation, or code - we'd love your help.

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests
5. Submit a pull request

See [CONTRIBUTING.md](docs/CONTRIBUTING.md) for details.

---

## 📄 License

OpenMES is open-source software licensed under the **MIT License**.

This means you can:
- ✅ Use it commercially
- ✅ Modify it
- ✅ Distribute it
- ✅ Use it privately

See [LICENSE](LICENSE) for full details.

---

## 📞 Support

### Free Support
- 📖 Read the [documentation](docs/)
- 🔍 Search [existing issues](https://github.com/Mes-Open/OpenMes/issues)
- 💬 Ask in [discussions](https://github.com/Mes-Open/OpenMes/discussions)

### Commercial Support
Need help with deployment, customization, or training?
Contact us at **support@openmmes.com**

---

<div align="center">

**Built with ❤️ for the manufacturing community**

Made by manufacturers, for manufacturers

⭐ If you find OpenMES useful, please give it a star!

</div>
