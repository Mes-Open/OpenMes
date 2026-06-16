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
for arg in "$@"; do
    case "$arg" in
        -i|--interactive) INTERACTIVE=1 ;;
        -y|--yes)         INTERACTIVE=0 ;; # default; kept for compatibility
        --dev)            DEV_MODE=1 ;;
        -h|--help)
            echo "Usage: ./install.sh [--interactive] [--dev]"
            echo "  (default)         unattended — sensible defaults, just works"
            echo "  -i, --interactive prompt for domain / admin user / email"
            echo "  --dev             run the vite-watch dev overlay instead of production"
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
    # 24 chars, alphanumeric + symbols safe for shell/env files
    tr -dc 'A-Za-z0-9@#%^&*_+=' </dev/urandom 2>/dev/null | head -c24 || \
    python3 -c "import secrets,string; print(secrets.token_urlsafe(18))"
}

port_in_use() {
    local p="$1"
    if command -v ss &>/dev/null; then
        ss -ltnH 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${p}\$"
    elif command -v netstat &>/dev/null; then
        netstat -ltn 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${p}\$"
    else
        # bash /dev/tcp: a successful connect means something is listening
        (exec 3<>"/dev/tcp/127.0.0.1/${p}") 2>/dev/null
    fi
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
fi
DB_PASSWORD="${DB_PASSWORD:-$(gen_pass)}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-$(gen_pass)}"

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

# ── Mode ──────────────────────────────────────────────────────────────────────
APP_ENV=production
APP_DEBUG=false

# SPA stateful hosts (must cover the host:port the app is served on, or the
# live-sync /api requests 401 and lists render empty).
SANCTUM_STATEFUL_DOMAINS=${SANCTUM_STATEFUL_DOMAINS}

# ── Database ──────────────────────────────────────────────────────────────────
POSTGRES_DB=openmmes
POSTGRES_USER=openmmes_user
POSTGRES_PASSWORD=${DB_PASSWORD}

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
echo "  Useful commands:"
echo "    docker compose logs -f backend          # Live logs"
echo "    docker compose down                     # Stop"
echo "    docker compose up -d                    # Start (same ports)"
echo "    docker compose up -d --build            # Rebuild after a git pull"
echo ""
