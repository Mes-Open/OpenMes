# Usage reporting (telemetry)

OpenMES reports on **itself** so we can see which features are used, which
environments are in the field, and which releases break — without waiting for
somebody to email us.

It is on by default and takes one click to turn off.

## The boundary

> OpenMES sends information **about the software** — versions, which features
> are switched on, rough size bands, and where errors occur (class, file and
> line). It never sends anything you entered into OpenMES: no material or
> product codes, no lot numbers, no order data, no recipes, no personal data,
> and no error message text.

| Sent | Never sent |
|---|---|
| OpenMES, PHP, PostgreSQL and OS versions | material, product, lot and order codes or names |
| which modules and modes are switched on | order contents, recipes, BOMs, quantities produced |
| **size bands** (`"50-199"`), never exact counts | usernames, names, e-mail addresses, PINs, IP addresses |
| exception class + file + line + a count band | **error message text** and stack traces |
| a random installation UUID | company or customer names, anything typed into a field |

This is enforced by tests, not by good intentions. `TelemetryPayloadTest` seeds a
plant's worth of realistic data, builds the real payload and fails if any value
from the database appears in it. Documentation goes stale; that test does not.

### Why error messages are excluded entirely

Redacting them reliably is not possible in this system. `InsufficientStockException`
produces:

```
Insufficient stock for material "Bolt M8" (BOLT-M8-001): required 5.0000 pcs, available 2.0000 pcs.
```

Strip the quotes and the long digit runs and `BOLT-M8-001` still survives — a
code that tells a reader what the factory makes. `QueryException` is worse: its
message carries the SQL together with its bound values. Rather than filter, the
buffer never reads a message at all, so an exception someone writes next year is
safe by construction.

## Turning it off

Any one of these is enough:

1. **Settings → System → Usage reporting** — the switch.
2. **`OPENMES_TELEMETRY=false`** in `.env` — read before the database, so it
   works on an air-gapped box and in a packaged build that has never started.
3. **Block the host** at your firewall. Nothing breaks; see "No internet" below.

Reporting is also off automatically in `local` and `testing` environments,
during installation, and on the public demo server.

## Seeing exactly what is sent

Two ways, both showing the real payload built by the same code that sends it:

```bash
php artisan telemetry:send --dry-run
```

or **Settings → System → Usage reporting → Show exactly what is sent**.

Both work while reporting is switched off — the question is most often asked by
somebody who has just turned it off.

## Example report

```json
{
  "install_id": "3f2b1a44-...",
  "schema_version": 1,
  "sent_at": "2026-09-20T03:00:00+00:00",
  "app": { "version": "v0.24.0", "locale": "en", "timezone": "Europe/Warsaw",
           "environment": "production", "demo_mode": false, "installed_days": 214 },
  "runtime": { "php": "8.3", "laravel": "12.x", "os_family": "Linux", "arch": "x86_64",
               "docker": true, "octane": true, "queue_driver": "database",
               "cache_driver": "redis", "broadcast_driver": "reverb",
               "db_driver": "pgsql", "db_version": "17.5" },
  "features": { "modules": ["quality", "materials", "maintenance"], "plugins": [],
                "production_flow_mode": "transfer", "workflow_mode": "standard",
                "pin_login_enabled": true, "allow_registration": false },
  "usage": { "work_orders": "1k-5k", "batches": "5k-20k", "lines": "1-9",
             "machine_connections_by_protocol": { "mqtt": "1-9", "modbus": "0", "opcua": "0" } },
  "liveness": { "last_activity_days": 0 },
  "errors": { "window_hours": 48, "overflow_count": "0",
              "items": [ { "fingerprint": "a1b2c3…", "class": "TypeError",
                           "file": "app/Services/Foo.php", "line": 88,
                           "count": "1-9", "first_seen": "…", "last_seen": "…" } ] }
}
```

## Where it goes and how often

`POST https://getopenmes.com/telemetry.php`, once a day, from a queued job.

The hour is derived from the installation's own id rather than fixed, so
installations do not all arrive in the same minute.

## No internet? Nothing breaks

A closed egress firewall is an ordinary condition for a factory, not a fault.
An installation that cannot reach us behaves exactly like one that can:

- no error, no warning in the interface, no effect on any page;
- failures are never retried into `failed_jobs`;
- only the **first** failure in a run is logged, at `debug` level — a year
  offline does not fill the log;
- attempts back off to weekly after 3 failures and monthly after 10.

## The installation ID

A random UUID in `storage/telemetry-id`, created on first use. It is random
rather than derived from your hostname or URL, so it says nothing about you and
you can rotate it. **Settings → Reset installation ID** deletes it — useful
after cloning a production machine to staging, where both copies would otherwise
report as the same installation.

> `storage/` must be a persistent volume. A container that discards it mints a
> new id on every restart. It already must persist for `storage/installed`.

## Data protection

No personal data is in the payload. The source IP address reaching
getopenmes.com is personal data under EU law; it is not retained.

Reports are unauthenticated and therefore forgeable, so installation counts are
an estimate rather than a fact.
