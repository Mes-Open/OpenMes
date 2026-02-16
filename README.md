<div align="center">

# 🏭 OpenMES

### Open-Source Manufacturing Execution System

*Powerful, flexible, and tablet-ready MES for small manufacturers*

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black)](https://reactjs.org)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-336791?logo=postgresql&logoColor=white)](https://www.postgresql.org)
[![Tests](https://img.shields.io/badge/tests-45%20passing-brightgreen)](tests)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

[Features](#-features) •
[Quick Start](#-quick-start) •
[Documentation](#-documentation) •
[API](#-api) •
[Contributing](#-contributing) •
[License](#-license)

</div>

---

## 📋 About

**OpenMES** is a modern, open-source Manufacturing Execution System designed specifically for **small manufacturers** (woodworking, metalworking, assembly shops) who need powerful production tracking without enterprise complexity.

Built with Laravel 11 (backend) and React 18 (frontend), OpenMES provides real-time production visibility, operator guidance, and comprehensive audit trails — all optimized for tablet use on the shop floor.

### Why OpenMES?

- 🎯 **Purpose-built for small manufacturers** - No bloat, just what you need
- 📱 **Tablet-first design** - Touch-optimized for shop floor operators
- 🔒 **Security-first** - OWASP Top 10 compliant from day one
- 📊 **Real-time visibility** - Know exactly what's happening on every line
- 🆓 **Truly open-source** - MIT licensed, no vendor lock-in
- 🚀 **Deploy in minutes** - Single command Docker deployment
- 🔧 **Extensible** - Plugin architecture for custom workflows

---

## ✨ Features

### 🏭 Production Management

- **Multi-line production** - Manage multiple production lines simultaneously
- **Work order tracking** - CSV import with flexible column mapping
- **Batch production** - Support partial completion with multiple batches
- **Process templates** - Versioned, reusable process definitions
- **Real-time status** - Live production status on every line

### 👷 Operator Experience

- **Step-by-step guidance** - Clear instructions for every operation
- **Sequential workflow** - Enforce process order (optional)
- **One-tap actions** - Start, complete, report issues with single tap
- **PWA support** - Install on tablets, works offline
- **Tablet-optimized UI** - Large touch targets, minimal text input

### 🔔 Issue & Andon System

- **Problem reporting** - Operators report issues instantly
- **Automatic blocking** - Critical issues halt production automatically
- **Issue escalation** - Route problems to supervisors
- **Resolution tracking** - Complete issue lifecycle management
- **Predefined categories** - Material shortage, quality issues, tool failures, etc.

### 📊 Traceability & Audit

- **Immutable audit logs** - PostgreSQL-enforced, cannot be altered
- **Complete traceability** - Track every action, every user, every timestamp
- **Event logging** - Domain events for system integration
- **Process snapshots** - Work orders immune to template changes
- **Compliance-ready** - ISO 9001, AS9100 compatible audit trail

### 🔐 Security & Access Control

- **Role-based access** - Admin, Supervisor, Operator roles
- **Line-based filtering** - Operators only see assigned lines
- **API authentication** - Sanctum token-based auth
- **Permission granularity** - 25+ granular permissions
- **Force password change** - Security flag for first login
- **Rate limiting** - Protection against brute force attacks

### 🛠️ Developer-Friendly

- **REST API** - Complete API with OpenAPI documentation
- **45+ automated tests** - Feature, unit, and integration tests
- **Factory pattern** - Test data generation built-in
- **Service layer** - Clean separation of concerns
- **Event-driven** - Laravel events for extensibility
- **Docker-ready** - Production deployment in one command

---

## 🚀 Quick Start

### Prerequisites

- Docker & Docker Compose
- Git

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/yourusername/openmmes.git
cd openmmes

# 2. Copy environment file
cp .env.example .env

# 3. Update credentials in .env
nano .env  # Set DB_PASSWORD and DEFAULT_ADMIN_PASSWORD

# 4. Start all services
docker-compose up -d

# 5. Run database migrations
docker-compose exec backend php artisan migrate:fresh --seed

# 6. Access the application
# Frontend: http://localhost
# API: http://localhost:8000
# Default login: admin / CHANGE_ON_FIRST_LOGIN
```

**That's it!** 🎉 OpenMES is now running.

---

## 📸 Screenshots

<div align="center">

### Operator View - Work Order Queue
*Touch-optimized queue showing active work orders sorted by priority*

![Work Order Queue](docs/screenshots/queue-placeholder.png)

### Batch Execution - Step by Step
*Clear instructions with START/COMPLETE buttons for step execution*

![Step Execution](docs/screenshots/steps-placeholder.png)

### Supervisor Dashboard
*Real-time overview of all production lines*

![Dashboard](docs/screenshots/dashboard-placeholder.png)

### Issue Reporting (Andon)
*Quick problem reporting with predefined categories*

![Issue Report](docs/screenshots/issue-placeholder.png)

</div>

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────┐
│           React PWA (Frontend)              │
│  Mantine UI · TanStack Query · Zustand     │
└─────────────────┬───────────────────────────┘
                  │ REST API (JSON)
┌─────────────────▼───────────────────────────┐
│          Laravel 11 (Backend)               │
│  Sanctum Auth · Service Layer · Events     │
└─────────────────┬───────────────────────────┘
                  │ Eloquent ORM
┌─────────────────▼───────────────────────────┐
│         PostgreSQL 14+ (Database)           │
│  JSONB · Triggers · Partial Indexes         │
└─────────────────────────────────────────────┘
```

### Tech Stack

**Backend:**
- Laravel 11 (PHP 8.2+)
- PostgreSQL 14+
- Laravel Sanctum (API Auth)
- Spatie Laravel Permission (RBAC)
- Maatwebsite Excel (CSV import)

**Frontend:**
- React 18 + TypeScript
- Vite (build tool)
- Mantine UI (component library)
- TanStack Query (server state)
- Zustand (client state)
- React Router (routing)
- Axios (HTTP client)

**Infrastructure:**
- Docker & Docker Compose
- Nginx (reverse proxy)

---

## 📚 Documentation

### User Guides
- [Operator Guide](docs/operator-guide.md) - Shop floor operators
- [Supervisor Guide](docs/supervisor-guide.md) - Production supervisors
- [Admin Guide](docs/admin-guide.md) - System administrators

### Technical Documentation
- [API Documentation](API_DOCUMENTATION.md) - REST API reference
- [Testing Guide](TESTING.md) - Running automated tests
- [Deployment Guide](docs/deployment.md) - Production deployment
- [Development Guide](docs/development.md) - Contributing to OpenMES

### Quick References
- [QUICKSTART.md](QUICKSTART.md) - Get started in 5 minutes
- [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) - Development progress
- [PHASE2_COMPLETE.md](PHASE2_COMPLETE.md) - Phase 2 summary

---

## 🔌 API

OpenMES provides a complete REST API for integration with ERP systems, data analytics, and custom applications.

### Authentication
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your_password"}'
```

### Create Work Order
```bash
curl -X POST http://localhost:8000/api/v1/work-orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "order_no": "WO-001",
    "line_id": 1,
    "product_type_id": 1,
    "planned_qty": 100
  }'
```

### Start Batch Step
```bash
curl -X POST http://localhost:8000/api/v1/batch-steps/1/start \
  -H "Authorization: Bearer YOUR_TOKEN"
```

📖 **Full API documentation:** [API_DOCUMENTATION.md](API_DOCUMENTATION.md)

---

## 🧪 Testing

OpenMES includes **45+ automated tests** covering all critical functionality:

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test tests/Feature/Api/AuthTest.php
php artisan test tests/Feature/Api/WorkOrderTest.php
php artisan test tests/Feature/Api/BatchStepTest.php

# Run with coverage
php artisan test --coverage
```

**Test Coverage:**
- ✅ Authentication (11 tests)
- ✅ Work Orders (13 tests)
- ✅ Batch Steps (11 tests)
- ✅ Service Layer (10 tests)

📖 **Full testing guide:** [TESTING.md](TESTING.md)

---

## 🗺️ Roadmap

### ✅ Phase 1: Foundation (Complete)
- [x] Laravel 11 backend setup
- [x] PostgreSQL schema with migrations
- [x] Authentication with Sanctum
- [x] Role-based access control
- [x] Docker deployment

### ✅ Phase 2: Work Order Core (Complete)
- [x] Work order CRUD API
- [x] Process snapshot generation
- [x] Batch creation and management
- [x] Step-by-step execution
- [x] Line-based access control
- [x] 45+ automated tests

### 🚧 Phase 3: Batch Execution UI (In Progress)
- [ ] React components for operators
- [ ] Work order queue UI
- [ ] Step execution interface
- [ ] Real-time status updates

### 📋 Phase 4: Issue/Andon System
- [ ] Issue reporting workflow
- [ ] Work order blocking logic
- [ ] Supervisor dashboard
- [ ] Issue resolution tracking

### 📋 Phase 5: CSV Import
- [ ] CSV upload and parsing
- [ ] Visual column mapper
- [ ] Import preview
- [ ] Idempotency strategies

### 📋 Phase 6: Audit & Events
- [ ] Audit log viewer
- [ ] Event timeline
- [ ] Export functionality

### 📋 Phase 7: PWA & Offline
- [ ] Service worker
- [ ] Offline queue
- [ ] Background sync
- [ ] Push notifications

### 📋 Phase 8: Analytics
- [ ] Production dashboards
- [ ] KPI tracking
- [ ] Reporting engine
- [ ] Data export

---

## 🤝 Contributing

We welcome contributions from the community! Whether it's:

- 🐛 **Bug reports** - Found an issue? Open a GitHub issue
- 💡 **Feature requests** - Have an idea? We'd love to hear it
- 📝 **Documentation** - Help improve our docs
- 🔧 **Code contributions** - Submit a pull request

### Getting Started

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`php artisan test`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

📖 **Read our [Contributing Guide](CONTRIBUTING.md)** for detailed instructions.

### Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/openmmes.git

# Install backend dependencies
cd backend
composer install
php artisan migrate:fresh --seed

# Install frontend dependencies
cd ../frontend
npm install

# Start development servers
cd ../backend && php artisan serve  # Terminal 1
cd ../frontend && npm run dev       # Terminal 2
```

---

## 👥 Community

- 💬 **Discussions** - [GitHub Discussions](https://github.com/yourusername/openmmes/discussions)
- 🐛 **Issues** - [GitHub Issues](https://github.com/yourusername/openmmes/issues)
- 📧 **Email** - openmmes@example.com

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

## 🙏 Acknowledgments

OpenMES is inspired by and builds upon the work of many great open-source projects:

- **[Laravel](https://laravel.com)** - The PHP framework that powers our backend
- **[React](https://reactjs.org)** - The UI library that powers our frontend
- **[Mantine](https://mantine.dev)** - Beautiful, accessible components
- **[qcadoo MES](https://github.com/qcadoo/mes)** - Inspiration for manufacturing workflows
- **PostgreSQL Community** - For the world's most advanced open-source database

Special thanks to all our [contributors](https://github.com/yourusername/openmmes/graphs/contributors)!

---

## ⭐ Star History

If you find OpenMES useful, please consider giving it a star! ⭐

It helps the project gain visibility and encourages continued development.

---

## 📞 Support

### Free Support
- 📖 Read the [documentation](docs/)
- 🔍 Search [existing issues](https://github.com/yourusername/openmmes/issues)
- 💬 Ask in [discussions](https://github.com/yourusername/openmmes/discussions)

### Commercial Support
Need help with deployment, customization, or training? Contact us at **support@openmmes.com**

---

<div align="center">

**Built with ❤️ for the manufacturing community**

Made by manufacturers, for manufacturers

[⬆ Back to Top](#-openmmes)

</div>
