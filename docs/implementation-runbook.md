# OpenMES Implementation Runbook

The procedure for putting OpenMES into a plant: from the server it runs on to the
day the shop floor stops using paper. Written for whoever runs the implementation —
integrator, IT lead or the vendor — not for the person who wrote the code.

A first line goes live in **1–3 weeks** of calendar time; most of that is master data
and operator habit, not software. The install itself is one command.

---

## Contents

- [Phase 0 — Qualify and plan](#phase-0--qualify-and-plan)
- [Phase 1 — Infrastructure](#phase-1--infrastructure)
- [Phase 2 — Install](#phase-2--install)
- [Phase 3 — Scope the system](#phase-3--scope-the-system)
- [Phase 4 — Master data](#phase-4--master-data)
- [Phase 5 — People and access](#phase-5--people-and-access)
- [Phase 6 — Shop-floor hardware](#phase-6--shop-floor-hardware)
- [Phase 7 — Integrations (optional)](#phase-7--integrations-optional)
- [Phase 8 — Pilot on one line](#phase-8--pilot-on-one-line)
- [Phase 9 — Go-live](#phase-9--go-live)
- [Phase 10 — Handover and operations](#phase-10--handover-and-operations)
- [Appendix A — Go-live checklist](#appendix-a--go-live-checklist)
- [Appendix B — Rollback and recovery](#appendix-b--rollback-and-recovery)
- [Appendix C — Common failure modes](#appendix-c--common-failure-modes)

---

## Phase 0 — Qualify and plan

Answer these **before** touching a server. Every one of them changes the
configuration, and finding out in week two is expensive.

| Question | Why it matters |
|---|---|
| Which line goes first? | Pick **one** line — the simplest product, the most cooperative crew. Never all lines at once. |
| What does a work order mean here? | Whether an order is one product on one line, or a campaign split into batches, decides how orders are imported. |
| Who counts production — operator or machine? | Sets the order's counting source. Machine counting needs Phase 7 and cannot be retrofitted mid-shift without confusing the numbers. |
| Does the plant track material lots? | Lot tracking is what makes traceability possible, and it is real work for operators. Decide per material, not globally. |
| Where do work orders come from? | Manual entry, CSV/Excel, or ERP push. The third one needs Phase 7. |
| Who owns stock quantities? | If an ERP owns them, OpenMES mirrors them. Getting this backwards produces two disagreeing truths. |
| Shift pattern and where the day boundary falls? | Reports and OEE are computed per shift; a wrong boundary makes every number look wrong. |
| Who is the plant-side owner? | One named person who decides master data. Implementations stall without one. |

**Deliverable of this phase:** a one-page scope note naming the pilot line, the
counting source, the order source, the shift pattern and the plant owner. Get it
signed off. Everything below assumes it exists.

---

## Phase 1 — Infrastructure

### The server

OpenMES is a Docker monolith: Postgres, the app (Octane/RoadRunner), a WebSocket
server (Reverb) and Caddy as the entry point. An ordinary office PC genuinely
suffices for a single-plant install.

| | Minimum | Comfortable |
|---|---|---|
| CPU | 2 cores | 4 cores |
| RAM | 4 GB | 8 GB |
| Disk | 20 GB SSD | 50 GB+ SSD (grows with attachments and CAD files) |
| OS | Any Linux with Docker Engine; Windows/macOS with Docker Desktop | Linux LTS |

Put it on **wired** network, on a UPS, with a static IP. A shop-floor MES that dies
with the office Wi-Fi teaches operators not to trust it.

> A standalone **desktop build** (Tauri) exists that bundles PHP, the database and
> the backend into an installer and serves the local network from a normal PC — the
> right choice for a small shop with no server and no IT staff. The rest of this
> runbook applies unchanged; skip Phase 2 and install the desktop package instead.

### Network

| Path | Requirement |
|---|---|
| Tablets/PCs → server | HTTP/80 and HTTPS/443, same L2 or routed with no captive portal |
| Server → machines (Phase 7) | Modbus TCP 502, OPC UA 4840, MQTT 1883/8883 — as needed, one-way is enough |
| Server → ERP (Phase 7) | Whatever the ERP exposes; outbound HTTPS in most cases |
| Internet | Only for updates and Let's Encrypt. An air-gapped install works — updates then go by file. |

**Live data uses WebSockets.** HTTP/2 or a clean WebSocket upgrade through any proxy
in the path; a proxy that buffers or strips upgrades silently degrades the UI to
polling. Test it before go-live, not after.

### HTTPS and naming

Give the server a hostname (`mes.plant.local` or a public FQDN). With a public DNS
name Caddy obtains a Let's Encrypt certificate on its own — set `DOMAIN` in `.env`
and nothing else. On an internal-only name, either accept plain HTTP inside the LAN
or place your own certificate in front. Tablets remember the URL: change it later and
every device needs revisiting.

---

## Phase 2 — Install

```bash
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes
./install.sh            # PowerShell: .\install.ps1
```

The installer generates credentials into `.env`, picks a free host port (80 if
available), builds the image and starts the stack in production mode. It prints the
URL and the admin login when it finishes; `./install.sh --yes` takes every default.

Set these in `.env` before the first start if you know them:

```dotenv
DOMAIN=mes.plant.example.com      # drives the Let's Encrypt certificate
APP_URL=https://mes.plant.example.com
APP_ENV=production
APP_DEBUG=false                    # never true in a plant
APP_TIMEZONE=Europe/Warsaw         # the PLANT's timezone — every timestamp uses it
ADMIN_EMAIL=…                      # first admin, created on first boot
ADMIN_PASSWORD=…                   # change it after the first login
POSTGRES_PASSWORD=…                # generated by the installer; keep it out of tickets
```

**What starts by itself** — worth knowing, because people go looking for it:

- migrations and the base seeders run on first boot, on the primary container only;
- the **scheduler** and a **queue worker** run inside that container, so OEE
  aggregation, maintenance generation and priority recalculation just work — no host
  cron to configure;
- `postgres`, `backend`, `reverb`, `caddy` are always on. `mqtt-listener`,
  `modbus-poller`, `opcua-gateway` are opt-in (`--profile connectivity`), as is a
  standalone `queue-worker` (`--profile workers`) when one plant needs more throughput.

Verify:

```bash
docker compose ps                 # postgres healthy, others Up
curl -sI http://localhost/login   # 200
docker compose exec backend php artisan migrate:status | tail -5
```

Then **log in and change the admin password**, and take the first backup (Appendix B)
before anyone enters data worth keeping.

---

## Phase 3 — Scope the system

Do this before master data — it decides which screens exist.

1. **First login opens the setup wizard** (5 steps): choose modules → create one
   line → one product type → its process template steps → one work order. It exists
   to prove the chain end to end. Use throwaway names if you are not ready with the
   real ones; you can delete them afterwards.
2. **Choose the module set.** Settings → System → Modules, or the wizard's first step:
   - **Lightweight** — core production tracking plus the work-order history report.
     The right default for a small shop. Start here.
   - **Advanced** — adds reporting, materials & tracing, product engineering,
     companies, quality, maintenance, connectivity, packaging.
   - **Custom** — tick exactly what the plant asked for.

   Every optional area can be switched on later, and switching one off hides its
   pages and 404s its routes without deleting data. **Turn on less than you think.**
   An operator faced with fourteen menu items uses none of them.
3. **Set the plant defaults.** Settings → System: production period, overproduction
   allowed or not, sequential steps enforced or not, block-negative-stock. These
   encode how the plant actually works — walk through them with the plant owner
   rather than guessing.

---

## Phase 4 — Master data

Load in this order. Each step resolves references the next one needs, and doing it
out of order produces orphan records that are tedious to clean up.

```
Structure (optional)   Sites → Areas → Factories → Divisions
        ↓
Lines                  the pilot line first, workstations under it
        ↓
Products               Product types → (optional) revisions
        ↓
Processes              Process templates → steps → (optional) checklists, documents
        ↓
Materials (optional)   Material types → materials → BOM on the template → lot sequences
        ↓
Calendar               Shifts, crews, break windows
        ↓
Orders                 manual, CSV/Excel import, or ERP push
```

Rules that save rework:

- **Codes are forever in practice.** Product, material and line codes end up in
  labels, ERP mappings and operator habit. Agree the convention with the plant owner
  before entering the second one.
- **One process template per product, versioned.** Do not model variants by creating
  near-duplicate products; use revisions, or link several BOMs to the order.
- **Only the pilot line's data now.** Loading the whole plant's catalogue before one
  line works is the single most common way to waste a week.
- **Use CSV/Excel import for work orders**, with the saved column mapping — plants
  send the same file layout every time, and the mapping is reusable.
- **Warehousing is its own rollout.** If stock and warehouse documents are in scope,
  follow [the warehouse & ERP sync runbook](warehouse-erp-rollout.md) after this
  phase, not during it.

Acceptance for this phase: create one work order on the pilot line, by hand, and
confirm its steps appear at the workstation.

---

## Phase 5 — People and access

1. **Create users** — Admin → Users. Three roles ship: **Admin**, **Supervisor**,
   **Operator**.
2. **Assign operators to lines.** An operator sees the queue for their lines; an
   unassigned operator sees an empty screen and reports the system as broken.
3. **Tune the access matrix** — Settings → Access is a role × tab grid. Narrow it so
   each role sees only its own work. This is also how you hide an area from
   supervisors while an admin still configures it.
4. **Decide identification on the floor.** Shared workstation accounts are quicker to
   roll out; named accounts are what you need if the plant wants to know who did
   what. If logistics movements or lot consumption must be attributable, use named
   accounts — retrofitting attribution to shared logins is impossible after the fact.
5. **Passwords.** Whoever holds the admin account changes it and stores it in the
   plant's password manager. Do not leave the installer's credentials in a chat
   thread or a ticket.

---

## Phase 6 — Shop-floor hardware

| Device | Use | Notes |
|---|---|---|
| Tablet (10"+) | Operator station | Install the **PWA** from the browser — it survives brief network drops and starts full-screen |
| Industrial PC / thin client | Line station, packaging station | Wired network; disable sleep and screen lock |
| Barcode scanner | Packaging, lot consumption | Any USB HID keyboard-wedge scanner works — no driver, no configuration |
| Label printer | LOT / pallet labels | Configure the label template before go-live, not on the morning of it |
| Office PC | Supervisor and admin | The planner and reports want a real screen |

Mount the tablet where the operator already stands. A station three metres from the
machine gets used for the first week and abandoned in the second.

Test each device **on the plant network with the real URL** before go-live. A station
that works on the IT desk and not at the machine is the norm, not the exception.

---

## Phase 7 — Integrations (optional)

Never in the first pass. Get one line running on manual entry, then add integrations.

### ERP

The `/api/v1/erp/*` surface takes work orders and master data in, and hands
production, quality, stock and warehouse documents back — one canonical JSON contract,
authenticated by a scoped API key (Admin → API keys). Mapping a specific ERP's tables
onto that contract is work on the ERP side.

Start with **one direction and one entity**: work-order import. Prove it for a day
before adding master data, then stock. Full reference:
[API documentation → ERP Integration API](API_DOCUMENTATION.md#erp-integration-api);
rollout order for the stock side: [warehouse & ERP sync runbook](warehouse-erp-rollout.md).

### Machines

Modbus TCP, OPC UA and MQTT are supported through the signal pipeline; start the
daemons with `docker compose --profile connectivity up -d`. Machine counters can drive
work-order progress directly — see
[Machine Connectivity](machine-connectivity.md) and [MQTT Connectivity](mqtt-connectivity.md).

Two rules from experience: read-only first (never write to a PLC during an
implementation), and **decide operator-or-machine counting per order** — both counting
the same order is how double counts happen.

### Webhooks

Outbound HTTP notifications on work-order, issue and batch events — the cheap way to
feed a dashboard, a Teams channel or a legacy system without building an integration.

---

## Phase 8 — Pilot on one line

Two weeks, in parallel with the current method. The goal is not "no bugs" — it is
"the crew trusts the numbers".

**Week 1 — supervised.** The implementer stands at the line for the first two shifts.
Every operator starts and completes at least one real order, on the real device, with
the real product. Write down every hesitation: each one is either a configuration fix
or a training gap.

**Week 2 — unsupervised, still parallel.** The plant runs OpenMES alone while paper
continues as the fallback. At the end of each day compare:

| Compare | Passes when |
|---|---|
| Produced quantity, OpenMES vs paper | Match, or every difference is explained |
| Scrap and downtime | Recorded, with sensible reasons — not everything under "other" |
| Order lead time | Plausible; no orders left running overnight because nobody closed them |
| Operator time per order | Not more than paper took. If it is, the process template has too many steps. |

**Stop rules — do not go live if:** operators are keeping a private paper tally,
the numbers need a daily correction by an admin, or the plant owner cannot explain a
report. Fix the cause and extend the pilot. A forced go-live produces a system
everyone works around.

Train while piloting, by role: operators (start / complete / report scrap and
downtime — 30 minutes at the station), supervisors (planner, issues, reports — 2 hours),
admins (master data, users, backups — half a day). Material:
[Training Program](training-program.md).

---

## Phase 9 — Go-live

Pick a **shift boundary at the start of a week** — never a Friday, never mid-shift.

Cutover, in order:

1. Fresh database backup (Appendix B).
2. Close or migrate open work orders: everything in progress either finishes on the
   old method or is entered as an order with its produced quantity so far.
3. Load the remaining lines' master data (Phase 4 order, per line).
4. Load opening stock, if warehousing is in scope — and run the reconciliation check
   from the [warehouse runbook](warehouse-erp-rollout.md#5-load-opening-stock).
5. Switch on the integrations one at a time, watching each for a full shift.
6. Walk the floor for the first full shift. Be present, not on call.
7. Retire paper **one week after** the last correction, not on day one.

Work the [go-live checklist](#appendix-a--go-live-checklist) with the plant owner and
have them sign it. It converts "it seems to work" into an agreed state.

---

## Phase 10 — Handover and operations

### What the plant must own

| Task | Cadence | Where |
|---|---|---|
| Database backup | Daily, automated, **restore-tested monthly** | Appendix B |
| Check for updates | Monthly | Admin → System → Updates |
| Review users and access | Quarterly, and on every leaver | Admin → Users, Settings → Access |
| Review reason codes | Quarterly | Scrap / downtime / anomaly reasons — codes nobody uses are noise |
| Watch disk usage | Monthly | Attachments and CAD files grow quietly |

### Automate the backup

```bash
# /etc/cron.daily/openmes-backup
#!/bin/sh
cd /opt/OpenMes || exit 1
docker compose exec -T postgres pg_dump -U openmmes_user openmmes \
  | gzip > /backup/openmes_$(date +\%Y\%m\%d).sql.gz
find /backup -name 'openmes_*.sql.gz' -mtime +30 -delete
```

Copy it **off the machine**. A backup on the disk that fails is not a backup.

### Updates

Admin → System → Updates downloads, backs up files, copies, migrates and clears
caches. Or `git pull && docker compose up -d --build`. Either way: database backup
first, and read the CHANGELOG — it flags anything that needs configuration after the
upgrade.

### Support boundary

Write down, in the handover note, who to call for what: plant IT (network, devices,
the server), the plant owner (master data, process templates, users), the vendor
(defects, upgrades, integrations). Implementations decay when every question routes
to whoever answers fastest.

---

## Appendix A — Go-live checklist

Infrastructure

- [ ] Server on UPS, wired, static IP, hostname resolves from the floor
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE` = plant timezone
- [ ] HTTPS serving, or a documented decision to run plain HTTP inside the LAN
- [ ] Live updates arrive without a refresh on a tablet **at the machine**
- [ ] Automated daily backup running, and **one restore has been tested**

Configuration

- [ ] Module set matches the signed scope note; nothing enabled "just in case"
- [ ] Plant defaults (production period, overproduction, sequential steps) confirmed with the owner
- [ ] Shifts match the real pattern, including the day boundary
- [ ] Reason codes (scrap, downtime, anomaly) are the plant's own words

Master data

- [ ] Every line going live has workstations, products, process templates
- [ ] BOMs entered for the materials the plant actually tracks
- [ ] Lot sequences configured where lots are tracked
- [ ] Opening stock loaded and reconciled (if warehousing is in scope)

People

- [ ] Admin password changed and stored in the plant's password manager
- [ ] Operators assigned to their lines; every operator has logged in once
- [ ] Access matrix reviewed per role
- [ ] Operators trained at the station; supervisors trained on planner and reports
- [ ] Admin trained on backup and restore

Floor

- [ ] Every station tested on the plant network with the production URL
- [ ] Scanners read the plant's real labels
- [ ] Label printing verified end to end
- [ ] Fallback procedure written: what the crew does if the server is down

Integrations

- [ ] Each integration watched for one full shift, alone
- [ ] API keys scoped to the minimum, with an IP allowlist where the source is fixed
- [ ] Machine counting vs operator counting decided per order, not per plant

Acceptance

- [ ] Pilot exit numbers agreed (quantity, scrap, downtime, lead time)
- [ ] Plant owner can produce and explain a report unaided
- [ ] Support boundary written down
- [ ] Paper retirement date agreed (one week after the last correction)

---

## Appendix B — Rollback and recovery

**Backup**

```bash
docker compose exec -T postgres pg_dump -U openmmes_user openmmes > backup.sql
cp .env .env.backup            # the app key lives here; without it, encrypted data is unreadable
```

**Restore**

```bash
docker compose down                    # no app connections, or DROP DATABASE is refused
docker compose up -d postgres

# Separate -c flags on purpose: two statements in one -c run inside a transaction
# block, and DROP DATABASE cannot.
docker compose exec -T postgres psql -U openmmes_user -d postgres \
  -c "DROP DATABASE openmmes;" \
  -c "CREATE DATABASE openmmes OWNER openmmes_user;"

# ON_ERROR_STOP turns a half-restored database into a visible failure.
docker compose exec -T postgres psql -U openmmes_user -d openmmes -v ON_ERROR_STOP=1 < backup.sql

docker compose up -d
```

Verify the restore before letting anyone back in — row counts against what you
expect, and the migration count:

```bash
docker compose exec -T postgres psql -U openmmes_user -d openmmes -c \
  "select (select count(*) from users) users, (select count(*) from work_orders) orders,
          (select count(*) from migrations) migrations;"
```

**Rehearse into a scratch database first**, so a rehearsal cannot damage production:

```bash
docker compose exec -T postgres psql -U openmmes_user -d postgres \
  -c "CREATE DATABASE openmmes_restore_test OWNER openmmes_user;"
docker compose exec -T postgres psql -U openmmes_user -d openmmes_restore_test -v ON_ERROR_STOP=1 < backup.sql
# compare counts, then:
docker compose exec -T postgres psql -U openmmes_user -d postgres \
  -c "DROP DATABASE openmmes_restore_test;"
```

**Rehearse this once during the pilot.** An untested restore is a hope, not a plan.

**Rolling back a version**

```bash
git checkout <previous tag>
docker compose up -d --build
docker compose exec backend php artisan optimize:clear
```

Code rolls back cleanly; **migrations do not roll themselves back**. If a release
added schema and you must undo it, restore the database backup taken before the
upgrade — which is why step 1 of every upgrade is that backup.

**Abandoning the go-live**

Paper is still running (Phase 9, step 7), so the fallback is: tell the crew to keep
using paper, leave the system up and read-only in practice, fix the cause, and pick
the next shift boundary. Do not delete data — the pilot's history is what shows what
went wrong.

---

## Appendix C — Common failure modes

| Symptom | Real cause | Fix |
|---|---|---|
| "Operators are not using it" | Station is too far from the machine, or the process template has too many steps | Move the station; cut steps to what is genuinely tracked |
| Lists appear empty although data exists | Live-sync path blocked by a proxy that strips WebSocket upgrades | Fix the proxy; check the browser console for a failed snapshot |
| Operator sees no orders | Not assigned to the line, or the order is not accepted yet | Admin → Users → lines; check the order status |
| Numbers disagree with paper | Double counting: machine and operator both counting one order | Decide one counting source per order |
| Every downtime is "other" | Reason codes are not the plant's words | Rewrite them with the crew, delete the unused ones |
| Timestamps look shifted | `APP_TIMEZONE` is not the plant's timezone | Set it, `config:cache`, restart |
| Reports are empty for yesterday | Scheduler not running (a bare-metal install without the container entrypoint) | Ensure `schedule:run` runs every minute |
| Update "broke" the site | Octane still serving the old boot | `optimize:clear` then `octane:reload` |
| Attachments fail to upload | Disk full or the storage volume was recreated | Check disk; verify the volume mount |
| Nobody knows who changed a setting | Shared admin account | Named accounts; the audit log records who |
