#!/bin/bash
# OpenMES — Installer
# Generates .env with secure defaults, picks free host ports, and starts the
# stack for real (production build, frontend baked into the image).
#
#   ./install.sh            # production, interactive
#   ./install.sh --yes      # production, accept all defaults (non-interactive)
#   ./install.sh --dev      # add the vite-watch dev overlay (bind-mounted source)

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

ok()   { echo -e "${GREEN}✓ $*${NC}"; }
warn() { echo -e "${YELLOW}! $*${NC}"; }
err()  { echo -e "${RED}✗ $*${NC}"; exit 1; }
info() { echo -e "${CYAN}→ $*${NC}"; }

# ── Flags ─────────────────────────────────────────────────────────────────────
# Unattended by default: `./install.sh` just works (sensible defaults, secure
# random passwords, no questions). Pass --interactive to customise domain/admin.
DEV_MODE=0
INTERACTIVE=0
TELEMETRY=""
case "$(printf '%s' "${OPENMES_TELEMETRY:-}" | tr '[:upper:]' '[:lower:]')" in
    "") ;;
    1 | true | yes | on) TELEMETRY=true ;;
    *)                   TELEMETRY=false ;;
esac
for arg in "$@"; do
    case "$arg" in
        -i|--interactive) INTERACTIVE=1 ;;
        -y|--yes)         INTERACTIVE=0 ;; # default; kept for compatibility
        --dev)            DEV_MODE=1 ;;
        --no-telemetry)   TELEMETRY=false ;;
        -h|--help)
            echo "Usage: ./install.sh [--interactive] [--dev] [--no-telemetry]"
            echo "  (default)         unattended — sensible defaults, just works"
            echo "  -i, --interactive prompt for domain / admin user / email"
            echo "  --dev             run the vite-watch dev overlay instead of production"
            echo "  --no-telemetry    do not send anonymous usage reports (default: on)"
            exit 0 ;;
        *) err "Unknown option: $arg" ;;
    esac
done

# Can't prompt without a real terminal — fall back to unattended.
[ "$INTERACTIVE" = "1" ] && [ ! -t 0 ] && INTERACTIVE=0

ask() { # ask <prompt> <default> <var>
    local prompt="$1" default="$2" __var="$3" reply=""
    if [ "$INTERACTIVE" = "1" ]; then
        read -rp "  ${prompt} [${default}]: " reply || reply=""
    fi
    printf -v "$__var" '%s' "${reply:-$default}"
}

confirm() { # confirm <prompt>; default = proceed (Enter / y / yes). Only "n"/"no" aborts.
    local reply=""
    [ "$INTERACTIVE" = "1" ] || return 0
    read -rp "  $1 [Y/n]: " reply || reply=""
    case "${reply:-y}" in [Nn] | [Nn][Oo]) return 1 ;; *) return 0 ;; esac
}

echo ""
echo "  ██████╗ ██████╗ ███████╗███╗   ██╗███╗   ███╗███████╗███████╗"
echo "  ██╔═══██╗██╔══██╗██╔════╝████╗  ██║████╗ ████║██╔════╝██╔════╝"
echo "  ██║   ██║██████╔╝█████╗  ██╔██╗ ██║██╔████╔██║█████╗  ███████╗"
echo "  ██║   ██║██╔═══╝ ██╔══╝  ██║╚██╗██║██║╚██╔╝██║██╔══╝  ╚════██║"
echo "  ╚██████╔╝██║     ███████╗██║ ╚████║██║ ╚═╝ ██║███████╗███████║"
echo "   ╚═════╝ ╚═╝     ╚══════╝╚═╝  ╚═══╝╚═╝     ╚═╝╚══════╝╚══════╝"
echo ""
echo "  Manufacturing Execution System"
echo "  ──────────────────────────────────────────────────────────────"
echo ""

# ── Prerequisites ─────────────────────────────────────────────────────────────

if ! command -v docker &>/dev/null; then
    err "Docker is not installed. See: https://docs.docker.com/get-docker/"
fi

if ! docker compose version &>/dev/null; then
    err "Docker Compose plugin not found. Install docker-compose-plugin."
fi

ok "Docker $(docker --version | awk '{print $3}' | tr -d ',')"
ok "Docker Compose $(docker compose version --short)"
echo ""

# ── Existing installation check ───────────────────────────────────────────────
# Re-running keeps the existing DB password (regenerating it would no longer
# match the already-initialised postgres volume → auth failures).

REUSE_ENV=0
if [ -f ".env" ]; then
    warn ".env already exists — keeping existing passwords (data is preserved)."
    confirm "Re-run setup? (existing data is preserved)" || { echo "Aborted."; exit 0; }
    REUSE_ENV=1
    echo ""
fi

# ── Helpers ───────────────────────────────────────────────────────────────────

gen_pass() {
    # 24 alphanumeric chars from the kernel CSPRNG (~143 bits). Intentionally no
    # symbols: the value is written unquoted to .env and passed through compose +
    # the entrypoint's sed env-sync, where '#' starts a comment, '$' interpolates,
    # '&' is sed's match-replacement, etc. Alphanumeric sidesteps all of that.
    # LC_ALL=C so BSD/macOS `tr` doesn't bail on the raw bytes ("Illegal byte sequence").
    LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c24
}

gen_app_key() {
    # Laravel key format (what `artisan key:generate` writes): base64 of 32 random
    # bytes. Generated here, not by the container entrypoint, because the
    # entrypoint writes its key into the container's own .env — every service
    # (backend, reverb, queue…) ends up with a different key, a recreated
    # container gets a new one, and compose still passes an empty APP_KEY that
    # overrides it. One key in this .env is shared by all services and survives
    # rebuilds.
    echo "base64:$(head -c32 /dev/urandom | base64 | tr -d '\n')"
}

port_in_use() {
    # Portable check (macOS + Linux, no external tools, no privilege needed): a
    # successful bash /dev/tcp connect to loopback means something is already
    # listening on the port. Runs in a subshell so the fd is closed for us.
    ( exec 3<>"/dev/tcp/127.0.0.1/$1" ) 2>/dev/null
}

pick_port() { # pick_port <preferred> <fallback-start>
    local p="$1"
    if ! port_in_use "$p"; then echo "$p"; return; fi
    p="$2"
    while port_in_use "$p"; do p=$((p + 1)); done
    echo "$p"
}

env_get() { grep -E "^$1=" .env 2>/dev/null | head -n1 | cut -d= -f2-; }

# ── Collect configuration ─────────────────────────────────────────────────────

if [ "$INTERACTIVE" = "1" ]; then
    info "Configure your installation (Enter accepts defaults):"
    echo ""
else
    info "Unattended setup (run with --interactive to customise)."
fi

ask "Domain (e.g. demo.example.com)" "localhost" DOMAIN
ask "Admin username" "admin" ADMIN_USERNAME
ask "Admin email" "admin@example.com" ADMIN_EMAIL

# Anonymous usage reports are on by default for a new installation. Switch
# them off up front with --no-telemetry (or OPENMES_TELEMETRY=false in the
# environment), or later in Settings -> System -> Usage reporting. The summary
# at the end says which way this install went.
TELEMETRY="${TELEMETRY:-true}"

# ── Pick free host ports (80/443 preferred, auto-fallback if taken) ───────────

info "Selecting host ports..."
HTTP_PORT="$(pick_port 80 8080)"
HTTPS_PORT="$(pick_port 443 8443)"
[ "$HTTP_PORT" = "80" ]  && ok "HTTP  port 80"  || warn "HTTP  port 80 busy → using ${HTTP_PORT}"
[ "$HTTPS_PORT" = "443" ] && ok "HTTPS port 443" || warn "HTTPS port 443 busy → using ${HTTPS_PORT}"

# APP_URL / Sanctum stateful hosts reflect the chosen ports.
if [ "$DOMAIN" = "localhost" ]; then
    if [ "$HTTP_PORT" = "80" ]; then APP_URL="http://localhost"; else APP_URL="http://localhost:${HTTP_PORT}"; fi
    SANCTUM_STATEFUL_DOMAINS="localhost,localhost:${HTTP_PORT},localhost:${HTTPS_PORT}"
else
    APP_URL="https://${DOMAIN}"
    SANCTUM_STATEFUL_DOMAINS="${DOMAIN}"
fi

# ── Passwords (reuse on re-run so they keep matching the postgres volume) ─────

if [ "$REUSE_ENV" = "1" ]; then
    DB_PASSWORD="$(env_get POSTGRES_PASSWORD)"
    ADMIN_PASSWORD="$(env_get ADMIN_PASSWORD)"
    NAME_PREFIX="$(env_get OPENMES_NAME_PREFIX)"
    APP_KEY="$(env_get APP_KEY)"
    PROJECT_NAME="$(env_get COMPOSE_PROJECT_NAME)"
fi
DB_PASSWORD="${DB_PASSWORD:-$(gen_pass)}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-$(gen_pass)}"
# Keep any existing key, prefixed or not (Laravel accepts both): rotating it
# logs everyone out and makes anything already encrypted with it unreadable.
# An install older than this script writing APP_KEY has no key here, but its
# running backend has one, and everything encrypted so far (webhook secrets,
# integration credentials) is readable only with that key. Adopt it instead of
# minting a new one.
if [ -z "$APP_KEY" ] && [ "$REUSE_ENV" = "1" ]; then
    APP_KEY="$(docker compose exec -T backend sh -c 'grep "^APP_KEY=base64:" .env | head -n1 | cut -d= -f2-' 2>/dev/null | tr -d '\r')"
    [ -n "$APP_KEY" ] && ok "Kept the encryption key of the running installation"
fi
if [ -z "$APP_KEY" ]; then APP_KEY="$(gen_app_key)"; fi

# Unique compose project + container-name prefix per install, so several local
# instances can run at once. The project name is what compose names volumes
# after: without a unique one, two checkouts in folders with the same name share
# one postgres volume, and the second install's fresh DB password is rejected by
# the first install's database. A fresh install therefore gets the folder name
# plus a short random suffix (e.g. "openmes-k3x"); re-runs keep what .env has.
# An older .env without COMPOSE_PROJECT_NAME keeps the folder-name default so
# its existing volumes stay attached.
dir_slug() {
    basename "$PWD" | LC_ALL=C tr '[:upper:]' '[:lower:]' | LC_ALL=C tr -c 'a-z0-9_-' '-' | sed 's/^[^a-z0-9]*//; s/-*$//'
}
if [ "$REUSE_ENV" != "1" ] && [ -z "$PROJECT_NAME" ]; then
    BASE_NAME="$(dir_slug)"
    [ -z "$BASE_NAME" ] && BASE_NAME="openmmes"
    while :; do
        PROJECT_NAME="${BASE_NAME}-$(LC_ALL=C tr -dc 'a-z0-9' </dev/urandom 2>/dev/null | head -c3)"
        docker volume inspect "${PROJECT_NAME}_postgres_data" &>/dev/null || break
    done
    NAME_PREFIX="$PROJECT_NAME"
fi
if [ -z "$NAME_PREFIX" ]; then
    NAME_PREFIX="$(dir_slug)"
    [ -z "$NAME_PREFIX" ] && NAME_PREFIX="openmmes"
fi
ok "Container name prefix: ${NAME_PREFIX} (containers: ${NAME_PREFIX}-backend, …)"

# Always bring up the dev overlay: it adds the vite build --watch frontend
# container and bind-mounts source, so .jsx edits on the server are rebuilt
# automatically with no image rebuild.
COMPOSE_FILES="-f docker-compose.yml -f docker-compose.dev.yml"

echo ""
echo "  Credentials (saved in .env — keep them safe):"
echo "  ┌─────────────────────────────────────────┐"
echo "  │  Admin login:    ${ADMIN_USERNAME}"
echo "  │  Admin password: ${ADMIN_PASSWORD}"
echo "  │  DB password:    ${DB_PASSWORD}"
echo "  └─────────────────────────────────────────┘"
echo ""

confirm "Proceed with installation?" || { echo "Aborted."; exit 0; }
echo ""

# ── Write .env ────────────────────────────────────────────────────────────────

cat > .env << EOF
# OpenMES — Docker Compose configuration
# Generated by install.sh on $(date '+%Y-%m-%d %H:%M:%S')
# DO NOT commit this file to version control.

# ── Domain / URL ──────────────────────────────────────────────────────────────
DOMAIN=${DOMAIN}
APP_URL=${APP_URL}

# ── Host ports (auto-selected; 80/443 preferred) ─────────────────────────────
HTTP_PORT=${HTTP_PORT}
HTTPS_PORT=${HTTPS_PORT}

# Container-name prefix — unique per install dir so multiple local instances
# don't clash on container names (default is "openmmes" when unset).
OPENMES_NAME_PREFIX=${NAME_PREFIX}
${PROJECT_NAME:+
# Compose project name — volumes are named after it, so changing it detaches
# this install from its database.
COMPOSE_PROJECT_NAME=${PROJECT_NAME}
}
# ── Mode ──────────────────────────────────────────────────────────────────────
APP_ENV=production
APP_DEBUG=false

# Encryption key for sessions/cookies — shared by every service. Changing it
# logs everyone out.
APP_KEY=${APP_KEY}

# SPA stateful hosts (must cover the host:port the app is served on, or the
# live-sync /api requests 401 and lists render empty).
SANCTUM_STATEFUL_DOMAINS=${SANCTUM_STATEFUL_DOMAINS}

# ── Database ──────────────────────────────────────────────────────────────────
POSTGRES_DB=openmmes
POSTGRES_USER=openmmes_user
POSTGRES_PASSWORD=${DB_PASSWORD}

# ── Anonymous usage reports ───────────────────────────────────────────────────
# Read before the database exists, and passed to the container by
# docker-compose.yml. The entrypoint records it as the administrator's answer on
# first start; after that, Settings -> System -> Usage reporting decides.
OPENMES_TELEMETRY=${TELEMETRY}

# ── Admin account (created automatically on first run) ────────────────────────
ADMIN_USERNAME=${ADMIN_USERNAME}
ADMIN_EMAIL=${ADMIN_EMAIL}
ADMIN_PASSWORD=${ADMIN_PASSWORD}
EOF

ok ".env created"

# ── Build and start (real production unless --dev) ────────────────────────────

if [ "$DEV_MODE" = "1" ]; then
    COMPOSE_FILES="-f docker-compose.yml -f docker-compose.dev.yml"
    info "Building and starting (DEV: vite-watch overlay)..."
else
    COMPOSE_FILES="-f docker-compose.yml"
    info "Building and starting containers (production; first build takes a few minutes)..."
fi
echo ""

docker compose $COMPOSE_FILES up -d --build

echo ""
ok "Containers started"
info "Waiting for the application to come up (first boot builds the DB)..."

# Poll the login page for a real 200 — that means Octane is serving, the
# entrypoint finished migrating/seeding, and Caddy is proxying. Only then is the
# link actually clickable. Up to ~3 minutes for a cold first boot.
READY_URL="${APP_URL%/}/login"
READY=0
CODE=""
ATTEMPTS=0
while [ $ATTEMPTS -le 90 ]; do
    CODE="$(curl -sk -o /dev/null -w '%{http_code}' "$READY_URL" 2>/dev/null || true)"
    [ "$CODE" = "200" ] && { READY=1; break; }
    ATTEMPTS=$((ATTEMPTS + 1))
    printf '.'
    sleep 2
done
echo ""

if [ "$READY" = "1" ]; then
    ok "Application is up — ${APP_URL} is live."
else
    warn "App not serving yet (last status: ${CODE:-none}). It may still be building/migrating."
    warn "Watch it finish with:  docker compose logs -f backend"
fi
echo ""
echo "  ╔══════════════════════════════════════════════════════════╗"
printf  "  ║  %-56s║\n" "$([ "$READY" = "1" ] && echo 'OpenMES is ready!' || echo 'OpenMES is starting — give it a moment…')"
echo "  ║                                                          ║"
printf  "  ║  URL:      %-46s║\n" "${APP_URL}"
printf  "  ║  Login:    %-46s║\n" "${ADMIN_USERNAME}"
printf  "  ║  Password: %-46s║\n" "${ADMIN_PASSWORD}"
echo "  ║                                                          ║"
echo "  ║  Credentials are saved in .env (do not share this file) ║"
echo "  ╚══════════════════════════════════════════════════════════╝"
echo ""
if [ "$TELEMETRY" = "true" ]; then
    echo "  Anonymous usage reports are ON."
    echo "  OpenMES sends information about the software only: version, which"
    echo "  features are switched on, rough size bands, and where errors occur."
    echo "  Nothing entered into the system is sent: no order, product, customer"
    echo "  or personal data. To switch it off: Settings -> System -> Usage"
    echo "  reporting (or reinstall with --no-telemetry)."
else
    echo "  Anonymous usage reports are OFF. Turn them on in"
    echo "  Settings -> System -> Usage reporting if you want to help."
fi
echo ""
echo "  Useful commands:"
echo "    docker compose logs -f backend          # Live logs"
echo "    docker compose down                     # Stop"
echo "    docker compose up -d                    # Start (same ports)"
echo "    docker compose up -d --build            # Rebuild after a git pull"
echo ""
