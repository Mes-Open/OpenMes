<div align="center">

# OpenMES

### Industrial-Grade Open-Source Manufacturing Execution System

*Advanced, flexible, and tablet-ready MES for small to medium manufacturers*

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-336791?logo=postgresql&logoColor=white)](https://www.postgresql.org)

</div>

---

## What is OpenMES?

**OpenMES** is a high-precision Manufacturing Execution System designed for modern workshops and factories. Transformed from a simple tracking app into an industrial platform, OpenMES solves real-world production challenges with its event-sourcing architecture, real-time machine integration, and advanced analytics engine.

### Why OpenMES?

- 🏭 **Industrial Strength** - Machine state tracking, tool lifecycle management, and high-precision OEE.
- 📱 **Tablet-First Design** - Touch-optimized for operators, even with gloves.
- 🔒 **Security & Compliance** - Granular RBAC, audit trails, and industrial-standard security gates.
- 📊 **Digital Twin & Simulation** - Virtualize production flow and predict bottlenecks before they happen.
- 🚀 **Fast Deployment** - Deploy an industrial MES in minutes with Docker.

---

## Features

### Industrial Integration
- **Multi-Protocol Support**: Direct communication via MQTT and Modbus TCP.
- **Machine State Tracking**: Real-time monitoring (RUNNING, FAULT, SETUP, IDLE).
- **Event-Sourced Core**: Microsecond precision for total data integrity.
- **Edge Computing**: Local buffering and cloud synchronization for unreliable networks.

### Analytics & Traceability
- **Real-Time OEE**: Automatic Availability, Performance, and Quality calculation.
- **Fault Intelligence**: MTBF and MTTR analytics for maintenance planning.
- **Traceability Graph**: Full "Birth Certificate" tracking from lot to serial number.
- **Constraint-Based Scheduling**: Intelligent planning based on machine, tool, and skill.

---

## Architecture

```
┌────────────────────────────────┐
│   Industrial User Dashboard    │  (Real-time Dashboard / Livewire)
└───────────────┬────────────────┘
                ▼
┌────────────────────────────────┐
│     OpenMES Core Engine        │  (Event Store / OEE Engine / Traceability)
└───────────────┬────────────────┘
                ▼
┌───────────────┴────────────────┐
│ Communication & Adapter Layer  │  (MQTT / Modbus / OPC-UA Abstraction)
└───────────────┬────────────────┘
                ▼
┌───────────────┴────────────────┐
│     Physical Factory Floor     │  (CNC / PLC / Assembly Stations)
└────────────────────────────────┘
```

---

## 🚀 Installation

```bash
# 1. Clone repository
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes

# 2. Start containers
docker-compose up -d
```

---

## 📄 License

OpenMES is open-source software licensed under the **MIT License**.

---

<div align="center">

**Built with ❤️ for the factories of the future**

Made by manufacturers, for manufacturers

⭐ If you find OpenMES useful, please give it a star!

</div>
