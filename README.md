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

OpenMES uses a **dead-simple** Laravel monolith architecture - like WordPress or PrestaShop:

```
┌─────────────────┐
│  Laravel App    │  :80 (serves everything)
│  (Blade + API)  │
└────────┬────────┘
         │
    ┌────▼─────┐
    │ PostgreSQL│
    └──────────┘
```

**Stack:**
- **Backend**: Laravel 12 with Blade templates
- **Frontend**: Tailwind CSS 4 + Alpine.js for interactivity
- **Real-time**: Livewire 4 for dynamic components
- **Charts**: Chart.js for analytics
- **Database**: PostgreSQL 14+ with immutable audit logs
- **Deployment**: Docker Compose (2 containers only!)

### Why This Architecture?

- **Ultra Simple**: Just 2 containers (Laravel + PostgreSQL)
- **One-Command Install**: Like WordPress - clone, run installer, done
- **No Reverse Proxy**: Laravel serves directly on port 80
- **Easy Maintenance**: Single codebase, traditional Laravel patterns
- **LAN Optimized**: Server-rendered pages, perfect for local networks
- **Mobile Ready**: Responsive Blade templates work on tablets
- **Fast**: Built-in assets compilation with Vite

---

## 🚀 Installation

### Prerequisites

- Docker & Docker Compose (20.10+)
- Git

### WordPress-Style Installation 🎯

**Just like WordPress - clone, open browser, configure!** No CLI commands required!

```bash
# 1. Clone the repository
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes

# 2. Start Docker containers
docker-compose up -d
```

**That's it!** Now open **http://localhost** in your browser.

### Web-Based Installation Wizard

You'll see a friendly 3-step installation wizard:

**Step 1: Basic Configuration**
- Site Name (e.g., "My Factory")
- Site URL (e.g., http://localhost)

**Step 2: Database Configuration**
- Host: `postgres` (for Docker)
- Port: `5432`
- Database: `openmmes`
- Username: `openmmes_user`
- Password: `openmmes_secret` (from docker-compose.yml)

**Step 3: Create Admin Account**
- Username (your choice)
- Email (your choice)
- Password (your choice - secure it!)

Click "Complete Installation" → **Done!** 🎉

### Quick Setup Script (Optional)

For even faster setup with default database credentials:

```bash
# Clone and run one-command setup
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes
./setup.sh
```

This automatically:
- ✅ Creates .env file
- ✅ Builds Docker containers
- ✅ Generates encryption key
- ✅ Opens http://localhost in your browser

Then just complete the 3-step wizard!

### First Steps After Installation

1. **Login** with your admin credentials
2. **Create production lines** in the admin panel
3. **Add users** (operators, supervisors) and assign them to lines
4. **Import work orders** via CSV or create manually
5. **Install PWA on tablets** for offline support

### Troubleshooting

**Containers not starting?**
```bash
# Check container logs
docker-compose logs backend
docker-compose logs postgres

# Restart containers
docker-compose restart

# Rebuild containers (if needed)
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

**Database connection errors?**
```bash
# Make sure postgres is healthy
docker-compose ps

# Check database credentials
grep DB_PASSWORD .env backend/.env

# Restart backend
docker-compose restart backend
```

**Application not loading?**
```bash
# Check if services are running
docker-compose ps

# View backend logs
docker-compose logs -f backend

# Rebuild backend (includes asset build)
docker-compose build --no-cache backend
docker-compose up -d
```

**Port 80 already in use?**
```bash
# Check what's using port 80
sudo lsof -i :80

# Edit docker-compose.yml to use different port:
# Change: - "80:8000" to "8080:8000"
# Then access at: http://localhost:8080
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
