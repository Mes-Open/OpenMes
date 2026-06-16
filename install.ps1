#requires -version 5.1
<#
  OpenMES — Installer (Windows / PowerShell)

  Behaves like install.sh: generates .env with secure defaults, picks free host
  ports, and starts the stack for real (production build, frontend baked in).

    .\install.ps1                 # production, unattended (just works)
    .\install.ps1 -Interactive    # prompt for domain / admin user / email
    .\install.ps1 -Dev            # vite-watch dev overlay (bind-mounted source)

  Run from the repository root (where docker-compose.yml lives), in a shell with
  Docker Desktop available. Requires Docker + the Docker Compose plugin.
#>
param(
    [Alias('i')][switch]$Interactive,
    [Alias('y')][switch]$Yes,        # kept for parity with --yes (unattended is the default)
    [switch]$Dev,
    [Alias('h')][switch]$Help
)

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch {}

function Write-Ok   ($m) { Write-Host "OK  $m"  -ForegroundColor Green }
function Write-Warn ($m) { Write-Host "!   $m"  -ForegroundColor Yellow }
function Write-Info ($m) { Write-Host "->  $m"  -ForegroundColor Cyan }
function Fail       ($m) { Write-Host "X   $m"  -ForegroundColor Red; exit 1 }

if ($Help) {
    Write-Host "Usage: .\install.ps1 [-Interactive] [-Dev]"
    Write-Host "  (default)       unattended - sensible defaults, just works"
    Write-Host "  -Interactive    prompt for domain / admin user / email"
    Write-Host "  -Dev            run the vite-watch dev overlay instead of production"
    exit 0
}

# ── Helpers ───────────────────────────────────────────────────────────────────

function Read-Default([string]$Prompt, [string]$Default) {
    if (-not $Interactive) { return $Default }
    $reply = Read-Host "  $Prompt [$Default]"
    if ([string]::IsNullOrWhiteSpace($reply)) { return $Default }
    return $reply
}

function Confirm-Proceed([string]$Prompt) {
    if (-not $Interactive) { return $true }   # unattended = proceed
    $reply = Read-Host "  $Prompt [Y/n]"
    if ($reply -match '^(n|no)$') { return $false }
    return $true
}

function New-Password {
    # 24 alphanumeric chars from a CSPRNG (~143 bits). No symbols: the value is
    # written to .env and passed through docker compose; alphanumeric avoids '#'
    # comments, '$' interpolation and quoting hazards.
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    $bytes = New-Object 'System.Byte[]' 24
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    -join ($bytes | ForEach-Object { $chars[$_ % $chars.Length] })
}

function Test-PortInUse([int]$Port) {
    # A successful TCP connect to loopback means something is already listening.
    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $iar = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if ($iar.AsyncWaitHandle.WaitOne(300) -and $client.Connected) {
            $client.EndConnect($iar); return $true
        }
        return $false
    } catch { return $false } finally { $client.Close() }
}

function Get-FreePort([int]$Preferred, [int]$FallbackStart) {
    if (-not (Test-PortInUse $Preferred)) { return $Preferred }
    $p = $FallbackStart
    while (Test-PortInUse $p) { $p++ }
    return $p
}

function Get-EnvValue([string]$Key) {
    if (-not (Test-Path '.env')) { return '' }
    foreach ($line in (Get-Content '.env')) {
        if ($line -match "^$([regex]::Escape($Key))=(.*)$") { return $Matches[1] }
    }
    return ''
}

# ── Banner ────────────────────────────────────────────────────────────────────

Write-Host ""
Write-Host "  OpenMES" -ForegroundColor Cyan
Write-Host "  Manufacturing Execution System"
Write-Host "  --------------------------------------------------------------"
Write-Host ""

# ── Prerequisites ─────────────────────────────────────────────────────────────

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Fail "Docker is not installed. See: https://docs.docker.com/get-docker/ (Docker Desktop)"
}
docker compose version *> $null
if ($LASTEXITCODE -ne 0) { Fail "Docker Compose plugin not found. Install Docker Desktop." }
Write-Ok ("Docker " + (((docker --version) -split ' ')[2].TrimEnd(',')))
Write-Ok ("Docker Compose " + (docker compose version --short))
Write-Host ""

if (-not (Test-Path 'docker-compose.yml')) {
    Fail "docker-compose.yml not found - run this from the repository root."
}

# ── Existing installation check (reuse passwords so they match the DB volume) ──

$reuseEnv = $false
if (Test-Path '.env') {
    Write-Warn ".env already exists - keeping existing passwords (data is preserved)."
    if (-not (Confirm-Proceed "Re-run setup? (existing data is preserved)")) { Write-Host "Aborted."; exit 0 }
    $reuseEnv = $true
    Write-Host ""
}

# ── Collect configuration ─────────────────────────────────────────────────────

if ($Interactive) { Write-Info "Configure your installation (Enter accepts defaults):"; Write-Host "" }
else { Write-Info "Unattended setup (run with -Interactive to customise)." }

$domain        = Read-Default "Domain (e.g. demo.example.com)" "localhost"
$adminUsername = Read-Default "Admin username" "admin"
$adminEmail    = Read-Default "Admin email" "admin@example.com"

# ── Pick free host ports (80/443 preferred, auto-fallback if taken) ───────────

Write-Info "Selecting host ports..."
$httpPort  = Get-FreePort 80 8080
$httpsPort = Get-FreePort 443 8443
if ($httpPort  -eq 80)  { Write-Ok "HTTP  port 80" }  else { Write-Warn "HTTP  port 80 busy -> using $httpPort" }
if ($httpsPort -eq 443) { Write-Ok "HTTPS port 443" } else { Write-Warn "HTTPS port 443 busy -> using $httpsPort" }

if ($domain -eq 'localhost') {
    $appUrl  = if ($httpPort -eq 80) { 'http://localhost' } else { "http://localhost:$httpPort" }
    $sanctum = "localhost,localhost:$httpPort,localhost:$httpsPort"
} else {
    $appUrl  = "https://$domain"
    $sanctum = $domain
}

# ── Passwords + container-name prefix (reuse on re-run) ───────────────────────

$dbPassword = ''; $adminPassword = ''; $namePrefix = ''
if ($reuseEnv) {
    $dbPassword    = Get-EnvValue 'POSTGRES_PASSWORD'
    $adminPassword = Get-EnvValue 'ADMIN_PASSWORD'
    $namePrefix    = Get-EnvValue 'OPENMES_NAME_PREFIX'
}
if (-not $dbPassword)    { $dbPassword    = New-Password }
if (-not $adminPassword) { $adminPassword = New-Password }

if (-not $namePrefix) {
    $namePrefix = ((Split-Path -Leaf (Get-Location)).ToLower() -replace '[^a-z0-9_.-]','-') -replace '^[^a-z0-9]+','' -replace '-+$',''
    if (-not $namePrefix) { $namePrefix = 'openmmes' }
}
Write-Ok "Container name prefix: $namePrefix (containers: $namePrefix-backend, ...)"

Write-Host ""
Write-Host "  Credentials (saved in .env - keep them safe):"
Write-Host "    Admin login:    $adminUsername"
Write-Host "    Admin password: $adminPassword"
Write-Host "    DB password:    $dbPassword"
Write-Host ""

if (-not (Confirm-Proceed "Proceed with installation?")) { Write-Host "Aborted."; exit 0 }
Write-Host ""

# ── Write .env (LF, no BOM — CRLF would append \r to values and break auth) ───

$envText = @"
# OpenMES - Docker Compose configuration
# Generated by install.ps1 on $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
# DO NOT commit this file to version control.

# Domain / URL
DOMAIN=$domain
APP_URL=$appUrl

# Host ports (auto-selected; 80/443 preferred)
HTTP_PORT=$httpPort
HTTPS_PORT=$httpsPort

# Container-name prefix - unique per install dir so multiple local instances
# don't clash on container names (default is "openmmes" when unset).
OPENMES_NAME_PREFIX=$namePrefix

# Mode
APP_ENV=production
APP_DEBUG=false

# SPA stateful hosts (must cover the host:port the app is served on).
SANCTUM_STATEFUL_DOMAINS=$sanctum

# Database
POSTGRES_DB=openmmes
POSTGRES_USER=openmmes_user
POSTGRES_PASSWORD=$dbPassword

# Admin account (created automatically on first run)
ADMIN_USERNAME=$adminUsername
ADMIN_EMAIL=$adminEmail
ADMIN_PASSWORD=$adminPassword
"@ -replace "`r`n", "`n"

[System.IO.File]::WriteAllText((Join-Path (Get-Location) '.env'), $envText, (New-Object System.Text.UTF8Encoding($false)))
Write-Ok ".env created"

# ── Build and start (real production unless -Dev) ─────────────────────────────

if ($Dev) {
    $composeFiles = @('-f','docker-compose.yml','-f','docker-compose.dev.yml')
    Write-Info "Building and starting (DEV: vite-watch overlay)..."
} else {
    $composeFiles = @('-f','docker-compose.yml')
    Write-Info "Building and starting containers (production; first build takes a few minutes)..."
}
Write-Host ""

docker compose @composeFiles up -d --build
if ($LASTEXITCODE -ne 0) { Fail "docker compose failed - check the output above." }

Write-Host ""
Write-Ok "Containers started"
Write-Info "Waiting for the application to come up (first boot builds the DB)..."

# Poll the login page for a real 200 (Octane serving + migrations done + Caddy).
try { [Net.ServicePointManager]::ServerCertificateValidationCallback = { $true } } catch {}
$readyUrl = ($appUrl.TrimEnd('/')) + '/login'
$ready = $false
for ($i = 0; $i -le 90; $i++) {
    try {
        $resp = Invoke-WebRequest -Uri $readyUrl -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
        if ($resp.StatusCode -eq 200) { $ready = $true; break }
    } catch {}
    Write-Host -NoNewline '.'
    Start-Sleep -Seconds 2
}
Write-Host ""

if ($ready) {
    Write-Ok "Application is up - $appUrl is live."
} else {
    Write-Warn "App not serving yet. It may still be building/migrating."
    Write-Warn "Watch it finish with:  docker compose logs -f backend"
}

Write-Host ""
Write-Host "  ==========================================================" -ForegroundColor Green
Write-Host ("  {0}" -f $(if ($ready) { 'OpenMES is ready!' } else { 'OpenMES is starting - give it a moment...' })) -ForegroundColor Green
Write-Host "  URL:      $appUrl"
Write-Host "  Login:    $adminUsername"
Write-Host "  Password: $adminPassword"
Write-Host "  Credentials are saved in .env (do not share this file)"
Write-Host "  ==========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Useful commands:"
Write-Host "    docker compose logs -f backend          # Live logs"
Write-Host "    docker compose down                     # Stop"
Write-Host "    docker compose up -d                     # Start (same ports)"
Write-Host "    docker compose up -d --build             # Rebuild after a git pull"
Write-Host ""
