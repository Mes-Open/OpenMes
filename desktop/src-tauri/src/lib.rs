use serde::{Deserialize, Serialize};
use std::fs;
use std::net::TcpStream;
use std::path::{Path, PathBuf};
use std::process::{Child, Command, Stdio};
use std::sync::Mutex;
use std::time::Duration;
use tauri::menu::{Menu, MenuItem};
use tauri::tray::TrayIconBuilder;
use tauri::{AppHandle, Emitter, Manager, RunEvent, State, WindowEvent};

// ── Konfiguracja ─────────────────────────────────────────────────────────────

#[derive(Serialize, Deserialize, Clone)]
#[serde(default)]
pub struct ServerConfig {
    /// Ścieżka do binarki PHP (docelowo: statyczny PHP dołączony do aplikacji).
    pub php_binary: String,
    /// Katalog backendu Laravela (OpenMes/backend).
    pub backend_dir: String,
    /// 127.0.0.1 = tylko ten komputer, 0.0.0.0 = cała sieć lokalna.
    pub host: String,
    pub port: u16,
    /// Uruchom serwer automatycznie przy starcie aplikacji.
    pub autostart: bool,
    /// Dodatkowe zmienne środowiskowe, linie KEY=VALUE.
    pub extra_env: String,
    /// "" (jeszcze nie wybrano) | "local" (SQLite) | "remote" (zdalny Postgres/MySQL).
    pub db_mode: String,
    pub db_driver: String,
    pub db_host: String,
    pub db_port: u16,
    pub db_database: String,
    pub db_username: String,
    pub db_password: String,
}

impl Default for ServerConfig {
    fn default() -> Self {
        Self {
            php_binary: "php".into(),
            backend_dir: String::new(),
            host: "127.0.0.1".into(),
            port: 8080,
            autostart: false,
            extra_env: String::new(),
            db_mode: String::new(),
            db_driver: "pgsql".into(),
            db_host: String::new(),
            db_port: 5432,
            db_database: "openmmes".into(),
            db_username: String::new(),
            db_password: String::new(),
        }
    }
}

fn config_path(app: &AppHandle) -> PathBuf {
    app.path()
        .app_config_dir()
        .expect("no app config dir")
        .join("config.json")
}

fn data_dir(app: &AppHandle) -> PathBuf {
    app.path().app_data_dir().expect("no app data dir")
}

/// Usuwa prefiks extended-length (`\\?\`) z windowsowej ścieżki. `resource_dir()`
/// zwraca ścieżki z tym prefiksem, a niektóre programy (np. initdb) nie radzą
/// sobie z nim przy szukaniu binarek "obok siebie".
fn deverbatim(p: PathBuf) -> PathBuf {
    let s = p.to_string_lossy();
    if let Some(rest) = s.strip_prefix(r"\\?\UNC\") {
        return PathBuf::from(format!(r"\\{rest}"));
    }
    if let Some(rest) = s.strip_prefix(r"\\?\") {
        return PathBuf::from(rest);
    }
    p
}

fn load_config(app: &AppHandle) -> ServerConfig {
    fs::read_to_string(config_path(app))
        .ok()
        .and_then(|s| serde_json::from_str(&s).ok())
        .unwrap_or_default()
}

// ── Stan serwera ─────────────────────────────────────────────────────────────

pub struct ServerState {
    child: Mutex<Option<Child>>,
    /// Port, na którym faktycznie wystartował serwer (preferowany bywa zajęty).
    port: Mutex<Option<u16>>,
    /// Wbudowany Postgres (sidecar) — proces i wybrany port.
    pg_child: Mutex<Option<Child>>,
    pg_port: Mutex<Option<u16>>,
}

#[derive(Serialize)]
pub struct ServerStatus {
    running: bool,
    port_open: bool,
    pid: Option<u32>,
    url: String,
    lan_urls: Vec<String>,
    lan_enabled: bool,
}

// ── Pomocnicze ───────────────────────────────────────────────────────────────

/// Na Unixie startujemy serwer w osobnej sesji (grupie procesów), żeby przy
/// zatrzymaniu zabić też dzieci (`php artisan serve` spawnuje `php -S`).
fn spawn_detached(mut cmd: Command) -> std::io::Result<Child> {
    #[cfg(unix)]
    {
        use std::os::unix::process::CommandExt;
        unsafe {
            cmd.pre_exec(|| {
                libc::setsid();
                Ok(())
            });
        }
    }
    cmd.spawn()
}

fn terminate(child: &mut Child) {
    #[cfg(unix)]
    {
        let pid = child.id() as i32;
        unsafe {
            libc::kill(-pid, libc::SIGTERM);
        }
        for _ in 0..20 {
            if matches!(child.try_wait(), Ok(Some(_))) {
                return;
            }
            std::thread::sleep(Duration::from_millis(250));
        }
        unsafe {
            libc::kill(-pid, libc::SIGKILL);
        }
        let _ = child.wait();
    }
    #[cfg(not(unix))]
    {
        let _ = child.kill();
        let _ = child.wait();
    }
}

fn parse_extra_env(raw: &str) -> Vec<(String, String)> {
    raw.lines()
        .filter_map(|l| {
            let l = l.trim();
            if l.is_empty() || l.starts_with('#') {
                return None;
            }
            l.split_once('=')
                .map(|(k, v)| (k.trim().to_string(), v.trim().to_string()))
        })
        .collect()
}

/// Port 8080 bywa zajęty (Docker itp.) — szukamy pierwszego wolnego od preferowanego.
fn find_free_port(host: &str, preferred: u16) -> Result<u16, String> {
    let bind_host = if host == "0.0.0.0" { "0.0.0.0" } else { "127.0.0.1" };
    (preferred..preferred.saturating_add(50))
        .find(|p| std::net::TcpListener::bind((bind_host, *p)).is_ok())
        .ok_or_else(|| format!("Brak wolnego portu w zakresie {preferred}–{}", preferred + 49))
}

fn lan_ips() -> Vec<String> {
    let out = Command::new("hostname").arg("-I").output();
    match out {
        Ok(o) => String::from_utf8_lossy(&o.stdout)
            .split_whitespace()
            .filter(|ip| ip.contains('.'))
            .map(|s| s.to_string())
            .collect(),
        Err(_) => vec![],
    }
}

// ── Auto-detekcja środowiska ─────────────────────────────────────────────────

#[derive(Serialize)]
pub struct DetectedEnv {
    php_binary: Option<String>,
    php_version: Option<String>,
    backend_dir: Option<String>,
}

/// Zwraca wersję PHP, jeśli binarka działa i ma >= 8.2.
fn php_version_ok(path: &str) -> Option<String> {
    let out = Command::new(path)
        .args(["-r", "echo PHP_VERSION;"])
        .output()
        .ok()?;
    if !out.status.success() {
        return None;
    }
    let v = String::from_utf8_lossy(&out.stdout).trim().to_string();
    let mut parts = v.split('.').filter_map(|p| p.parse::<u32>().ok());
    let major = parts.next()?;
    let minor = parts.next().unwrap_or(0);
    if major > 8 || (major == 8 && minor >= 2) {
        Some(v)
    } else {
        None
    }
}

fn find_php() -> Option<(String, String)> {
    let mut candidates: Vec<String> = vec!["php".into()];
    #[cfg(unix)]
    candidates.extend(
        [
            "/usr/bin/php",
            "/usr/local/bin/php",
            "/opt/homebrew/bin/php",
            "/usr/bin/php8.4",
            "/usr/bin/php8.3",
            "/usr/bin/php8.2",
        ]
        .iter()
        .map(|s| s.to_string()),
    );
    #[cfg(windows)]
    candidates.extend(
        [
            "C:\\php\\php.exe",
            "C:\\tools\\php\\php.exe",
            "C:\\xampp\\php\\php.exe",
        ]
        .iter()
        .map(|s| s.to_string()),
    );
    candidates
        .into_iter()
        .find_map(|c| php_version_ok(&c).map(|v| (c, v)))
}

fn is_openmes_backend(dir: &Path) -> bool {
    dir.join("artisan").is_file()
        && fs::read_to_string(dir.join("composer.json"))
            .map(|s| s.contains("mes-open/openmes"))
            .unwrap_or(false)
}

fn scan_for_backend(root: &Path, depth: u32, found: &mut Option<PathBuf>) {
    if found.is_some() || depth == 0 {
        return;
    }
    if is_openmes_backend(root) {
        *found = Some(root.to_path_buf());
        return;
    }
    let Ok(entries) = fs::read_dir(root) else {
        return;
    };
    for entry in entries.flatten() {
        let path = entry.path();
        let name = entry.file_name();
        let name = name.to_string_lossy();
        if !path.is_dir()
            || name.starts_with('.')
            || matches!(name.as_ref(), "node_modules" | "vendor" | "target" | "storage")
        {
            continue;
        }
        scan_for_backend(&path, depth - 1, found);
        if found.is_some() {
            return;
        }
    }
}

fn find_backend(app: &AppHandle) -> Option<String> {
    let mut roots: Vec<PathBuf> = vec![];
    if let Ok(home) = app.path().home_dir() {
        roots.push(home.join("Documents"));
        roots.push(home);
    }
    #[cfg(unix)]
    roots.push(PathBuf::from("/opt"));
    let mut found = None;
    for root in roots {
        scan_for_backend(&root, 4, &mut found);
        if found.is_some() {
            break;
        }
    }
    found.map(|p| p.to_string_lossy().into_owned())
}

// ── Wbudowany PostgreSQL (sidecar) ───────────────────────────────────────────

const PG_USER: &str = "openmes";
// Domyślna baza tworzona przez initdb — używamy jej wprost, bo minimalny
// embedded Postgres (zonky) nie zawiera createdb/psql.
const PG_DB: &str = "postgres";

/// Katalog z binarkami Postgresa (bin/initdb, postgres, createdb…) z zasobów.
fn pg_bin_dir(app: &AppHandle) -> Option<PathBuf> {
    let res = deverbatim(app.path().resource_dir().ok()?);
    let bin = res.join("pgsql").join("bin");
    if bin.join(pg_exe("initdb")).is_file() {
        Some(bin)
    } else {
        None
    }
}

fn pg_exe(name: &str) -> String {
    if cfg!(windows) {
        format!("{name}.exe")
    } else {
        name.to_string()
    }
}

/// Uruchamia wbudowany Postgres (initdb przy pierwszym razie), tworzy bazę,
/// zwraca port. Idempotentne. Wymaga binarek Postgresa w zasobach.
fn ensure_postgres(app: &AppHandle, state: &ServerState) -> Result<u16, String> {
    // Już działa?
    if let Some(p) = *state.pg_port.lock().unwrap() {
        if state
            .pg_child
            .lock()
            .unwrap()
            .as_mut()
            .map(|c| matches!(c.try_wait(), Ok(None)))
            .unwrap_or(false)
        {
            return Ok(p);
        }
    }

    let bin = pg_bin_dir(app).ok_or_else(|| {
        "Brak wbudowanego PostgreSQL (zasób pgsql/bin).".to_string()
    })?;
    let data = data_dir(app);
    let pgdata = data.join("pgdata");
    let logs = data.join("storage").join("logs");
    fs::create_dir_all(&logs).map_err(|e| e.to_string())?;
    let pg_log = logs.join("postgres.log");

    // initdb przy pierwszym uruchomieniu (trust na localhost — baza nie jest
    // wystawiana poza 127.0.0.1; do sieci idzie tylko serwer HTTP).
    if !pgdata.join("PG_VERSION").is_file() {
        emit_progress(app, "Przygotowywanie bazy danych PostgreSQL (pierwsze uruchomienie)…");
        // Po nieudanym initdb katalog bywa niepusty — initdb by odmówił. Czyścimy.
        let _ = fs::remove_dir_all(&pgdata);
        fs::create_dir_all(&pgdata).map_err(|e| e.to_string())?;
        let pwfile = data.join(".pgpw");
        let _ = fs::write(&pwfile, "openmes");
        let out = Command::new(bin.join(pg_exe("initdb")))
            .arg("-D").arg(&pgdata)
            .arg("-U").arg(PG_USER)
            .arg("--auth=trust")
            .arg("--encoding=UTF8")
            .arg("--no-locale")
            .output()
            .map_err(|e| format!("initdb: {e}"))?;
        let _ = fs::remove_file(&pwfile);
        if !out.status.success() {
            return Err(format!(
                "initdb nie powiódł się:\n{}",
                String::from_utf8_lossy(&out.stderr)
            ));
        }
    }

    let port = find_free_port("127.0.0.1", 5433)?;
    emit_progress(app, "Uruchamianie bazy danych…");
    let mut cmd = Command::new(bin.join(pg_exe("postgres")));
    cmd.arg("-D").arg(&pgdata)
        .arg("-p").arg(port.to_string())
        .arg("-c").arg("listen_addresses=127.0.0.1")
        .stdout(Stdio::from(
            fs::OpenOptions::new().create(true).append(true).open(&pg_log).map_err(|e| e.to_string())?,
        ))
        .stderr(Stdio::from(
            fs::OpenOptions::new().create(true).append(true).open(&pg_log).map_err(|e| e.to_string())?,
        ));
    let child = spawn_detached(cmd).map_err(|e| format!("Nie udało się uruchomić Postgresa: {e}"))?;
    *state.pg_child.lock().unwrap() = Some(child);

    // Czekaj aż przyjmuje połączenia.
    let mut ready = false;
    for _ in 0..60 {
        if TcpStream::connect_timeout(
            &format!("127.0.0.1:{port}").parse().unwrap(),
            Duration::from_millis(300),
        )
        .is_ok()
        {
            ready = true;
            break;
        }
        std::thread::sleep(Duration::from_millis(500));
    }
    if !ready {
        return Err("PostgreSQL nie wystartował (timeout).".into());
    }

    // Baza `postgres` istnieje od initdb — nie trzeba jej tworzyć.
    *state.pg_port.lock().unwrap() = Some(port);
    Ok(port)
}

fn stop_postgres(app: &AppHandle, state: &ServerState) {
    // Czysty shutdown przez pg_ctl, jeśli dostępny; inaczej zabij proces.
    if let Some(bin) = pg_bin_dir(app) {
        let pgdata = data_dir(app).join("pgdata");
        let _ = Command::new(bin.join(pg_exe("pg_ctl")))
            .arg("stop").arg("-D").arg(&pgdata).arg("-m").arg("fast")
            .output();
    }
    if let Some(mut child) = state.pg_child.lock().unwrap().take() {
        terminate(&mut child);
    }
    *state.pg_port.lock().unwrap() = None;
}

fn base_command(app: &AppHandle, cfg: &ServerConfig, pg_port: Option<u16>) -> Result<Command, String> {
    let backend = PathBuf::from(&cfg.backend_dir);
    if !backend.join("artisan").is_file() {
        return Err(format!(
            "Nie znaleziono pliku artisan w {} — wskaż katalog backendu Laravela.",
            cfg.backend_dir
        ));
    }

    let data = data_dir(app);
    fs::create_dir_all(&data).map_err(|e| e.to_string())?;
    let db_file = data.join("openmes.sqlite");
    if !db_file.exists() {
        fs::write(&db_file, []).map_err(|e| e.to_string())?;
    }

    // Własny storage/ poza repo backendu — logi, sesje, cache widoków i marker
    // "installed" lądują w danych aplikacji (Laravel: LARAVEL_STORAGE_PATH).
    let storage = data.join("storage");
    for sub in [
        "app/public",
        "framework/cache/data",
        "framework/sessions",
        "framework/views",
        "logs",
    ] {
        fs::create_dir_all(storage.join(sub)).map_err(|e| e.to_string())?;
    }

    let mut cmd = Command::new(&cfg.php_binary);
    cmd.current_dir(&backend)
        .env("LARAVEL_STORAGE_PATH", storage.as_os_str());

    // Windows: oficjalny build php.exe ma rozszerzenia jako DLL, domyślnie
    // wyłączone. Generujemy php.ini z ABSOLUTNYM extension_dir (cwd to backend,
    // więc względny "ext" by nie zadziałał) i wskazujemy go przez PHPRC.
    #[cfg(windows)]
    {
        let php_dir = Path::new(&cfg.php_binary).parent().map(|p| p.to_path_buf());
        if let Some(php_dir) = php_dir {
            let ext_dir = php_dir.join("ext");
            if ext_dir.is_dir() {
                let exts = [
                    "pdo_sqlite", "sqlite3", "pdo_pgsql", "pgsql", "pdo_mysql", "mysqli",
                    "gd", "zip", "mbstring", "intl", "sodium", "openssl", "curl",
                    "fileinfo", "exif", "bz2", "xsl",
                ];
                let mut ini = format!("extension_dir=\"{}\"\n", ext_dir.display());
                for e in exts {
                    ini.push_str(&format!("extension={e}\n"));
                }
                let ini_dir = data.join("runtime");
                let _ = fs::create_dir_all(&ini_dir);
                if fs::write(ini_dir.join("php.ini"), ini).is_ok() {
                    cmd.env("PHPRC", ini_dir.as_os_str());
                }
            }
        }
    }

    // Instalator OpenMES pomija kroki environment+database (INSTALLER_PRESET)
    // — user widzi tylko rejestrację admina, a przy bazie zdalnej z kontami
    // od razu ekran logowania.
    if cfg.db_mode == "remote" {
        cmd.env("DB_CONNECTION", &cfg.db_driver)
            .env("DB_HOST", &cfg.db_host)
            .env("DB_PORT", cfg.db_port.to_string())
            .env("DB_DATABASE", &cfg.db_database)
            .env("DB_USERNAME", &cfg.db_username)
            .env("DB_PASSWORD", &cfg.db_password)
            .env("INSTALLER_PRESET", &cfg.db_driver);
    } else if let Some(pgp) = pg_port {
        // Tryb lokalny = wbudowany PostgreSQL (localhost). Serwer HTTP wystawia
        // to do sieci; sama baza słucha tylko na 127.0.0.1.
        cmd.env("DB_CONNECTION", "pgsql")
            .env("DB_HOST", "127.0.0.1")
            .env("DB_PORT", pgp.to_string())
            .env("DB_DATABASE", PG_DB)
            .env("DB_USERNAME", PG_USER)
            .env("DB_PASSWORD", "")
            .env("INSTALLER_PRESET", "pgsql");
    } else {
        // Fallback (np. Linux bez wbudowanego Postgresa): lokalny SQLite.
        cmd.env("DB_CONNECTION", "sqlite")
            .env("DB_DATABASE", db_file.as_os_str())
            .env("INSTALLER_PRESET", "sqlite");
    }

    cmd.env("CACHE_STORE", "file")
        .env("SESSION_DRIVER", "file")
        .env("QUEUE_CONNECTION", "sync")
        .env("BROADCAST_CONNECTION", "log")
        .env("MAIL_MAILER", "log")
        // Marks the app as running inside the desktop shell so the Laravel side
        // can delegate/hide its own PHP self-updater in favour of the Rust
        // staged-swap updater (see update_apply below).
        .env("OPENMES_DESKTOP", "1");
    for (k, v) in parse_extra_env(&cfg.extra_env) {
        cmd.env(k, v);
    }
    Ok(cmd)
}

fn log_file(app: &AppHandle) -> Result<fs::File, String> {
    let data = data_dir(app);
    fs::create_dir_all(&data).map_err(|e| e.to_string())?;
    fs::OpenOptions::new()
        .create(true)
        .append(true)
        .open(data.join("server.log"))
        .map_err(|e| e.to_string())
}

fn do_start(app: &AppHandle, state: &ServerState) -> Result<(), String> {
    let mut guard = state.child.lock().unwrap();
    if let Some(child) = guard.as_mut() {
        if child.try_wait().map_err(|e| e.to_string())?.is_none() {
            return Err("Serwer już działa.".into());
        }
    }

    let cfg = load_config(app);

    // Tryb lokalny: uruchom wbudowany PostgreSQL (jeśli dostępny w zasobach).
    // Migracje robi instalator OpenMES (INSTALLER_PRESET) przy wejściu na /install.
    let pg_port = if cfg.db_mode != "remote" && pg_bin_dir(app).is_some() {
        Some(ensure_postgres(app, state)?)
    } else {
        None
    };

    let port = find_free_port(&cfg.host, cfg.port)?;
    let mut cmd = base_command(app, &cfg, pg_port)?;
    let log = log_file(app)?;
    let log_err = log.try_clone().map_err(|e| e.to_string())?;
    cmd.arg("artisan")
        .arg("serve")
        .arg(format!("--host={}", cfg.host))
        .arg(format!("--port={port}"))
        .stdout(Stdio::from(log))
        .stderr(Stdio::from(log_err));

    let child = spawn_detached(cmd).map_err(|e| format!("Nie udało się uruchomić PHP: {e}"))?;
    *guard = Some(child);
    *state.port.lock().unwrap() = Some(port);
    Ok(())
}

fn do_stop(app: &AppHandle, state: &ServerState) {
    {
        let mut guard = state.child.lock().unwrap();
        if let Some(mut child) = guard.take() {
            terminate(&mut child);
        }
    }
    stop_postgres(app, state);
}

// ── Komendy (IPC) ────────────────────────────────────────────────────────────

#[tauri::command]
fn get_config(app: AppHandle) -> ServerConfig {
    load_config(&app)
}

/// Skanuje sieć lokalną (/24 z każdego interfejsu) w poszukiwaniu serwera bazy
/// danych — Postgres :5432 i MySQL/MariaDB :3306. Zwraca listę "host:port".
///
/// `async` jest tu krytyczne: skan trwa kilka sekund, a synchroniczna komenda
/// Tauri blokowałaby główny wątek (pętlę zdarzeń WebKitGTK) i wieszała okno.
#[tauri::command]
async fn scan_for_database() -> Vec<String> {
    let mut prefixes: Vec<String> = lan_ips()
        .iter()
        .filter_map(|ip| ip.rsplit_once('.').map(|(p, _)| p.to_string()))
        .collect();
    prefixes.sort();
    prefixes.dedup();

    // Localhost też — baza może stać na tej samej maszynie (np. Docker).
    let mut targets: Vec<String> = [5432u16, 3306]
        .iter()
        .map(|port| format!("127.0.0.1:{port}"))
        .collect();
    targets.extend(prefixes.iter().flat_map(|p| {
        (1..=254u16)
            .flat_map(move |h| [5432u16, 3306].map(|port| format!("{p}.{h}:{port}")))
    }));

    // Sieci z wieloma mostkami (Docker/VM) dają tysiące celów — wysoka
    // równoległość trzyma czas skanu w okolicach kilku sekund.
    let found = std::sync::Arc::new(Mutex::new(Vec::new()));
    let chunk = targets.len().div_ceil(256).max(1);
    let mut handles = vec![];
    for batch in targets.chunks(chunk) {
        let batch = batch.to_vec();
        let found = found.clone();
        handles.push(std::thread::spawn(move || {
            for addr in batch {
                if let Ok(sock) = addr.parse() {
                    if TcpStream::connect_timeout(&sock, Duration::from_millis(250)).is_ok() {
                        found.lock().unwrap().push(addr);
                    }
                }
            }
        }));
    }
    for h in handles {
        let _ = h.join();
    }
    let mut result = found.lock().unwrap().clone();
    result.sort();
    result
}

/// Weryfikuje dane logowania do zdalnej bazy przez PDO (PHP jest już wymagany).
/// `async` — uruchamia zewnętrzny proces PHP, nie może blokować głównego wątku.
#[tauri::command]
async fn test_db_connection(app: AppHandle, config: ServerConfig) -> Result<String, String> {
    let cfg_php = if config.php_binary.is_empty() {
        load_config(&app).php_binary
    } else {
        config.php_binary.clone()
    };
    let proto = if config.db_driver == "mysql" || config.db_driver == "mariadb" {
        "mysql"
    } else {
        "pgsql"
    };
    let dsn = format!(
        "{proto}:host={};port={};dbname={}",
        config.db_host, config.db_port, config.db_database
    );
    let code = r#"try { new PDO($argv[1], $argv[2], $argv[3], [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); echo "OK"; } catch (Throwable $e) { fwrite(STDERR, $e->getMessage()); exit(1); }"#;
    let out = Command::new(&cfg_php)
        .args(["-r", code, "--", &dsn, &config.db_username, &config.db_password])
        .output()
        .map_err(|e| format!("Nie udało się uruchomić PHP: {e}"))?;
    if out.status.success() {
        Ok("Połączenie działa.".into())
    } else {
        Err(String::from_utf8_lossy(&out.stderr).trim().to_string())
    }
}

// ── Bootstrap: samodzielne przygotowanie środowiska ──────────────────────────

fn emit_progress(app: &AppHandle, msg: &str) {
    let _ = app.emit("bootstrap-progress", msg);
}

/// Rekurencyjne kopiowanie katalogu (bez zewnętrznych zależności).
fn copy_dir(src: &Path, dst: &Path) -> std::io::Result<()> {
    fs::create_dir_all(dst)?;
    for entry in fs::read_dir(src)? {
        let entry = entry?;
        let from = entry.path();
        let to = dst.join(entry.file_name());
        if from.is_dir() {
            copy_dir(&from, &to)?;
        } else {
            fs::copy(&from, &to)?;
        }
    }
    Ok(())
}

/// Backend jest wbudowany w aplikację jako zasób. Przy pierwszym starcie
/// kopiujemy go do (zapisywalnego) katalogu danych — repo na maszynie
/// użytkownika nie jest potrzebne.
fn resolve_backend(app: &AppHandle) -> Result<String, String> {
    let target = data_dir(app).join("backend");
    if target.join("artisan").is_file() {
        return Ok(target.to_string_lossy().into_owned());
    }

    // Wbudowany zasób: <resources>/backend.
    if let Ok(res) = app.path().resource_dir().map(deverbatim) {
        let bundled = res.join("backend");
        if bundled.join("artisan").is_file() {
            emit_progress(app, "Rozpakowywanie OpenMES (pierwsze uruchomienie)…");
            copy_dir(&bundled, &target).map_err(|e| format!("Kopiowanie backendu: {e}"))?;
            return Ok(target.to_string_lossy().into_owned());
        }
    }

    // Tryb deweloperski: backend obok aplikacji lub w Dokumentach.
    let cfg = load_config(app);
    if !cfg.backend_dir.is_empty() && Path::new(&cfg.backend_dir).join("artisan").is_file() {
        return Ok(cfg.backend_dir);
    }
    find_backend(app).ok_or_else(|| {
        "Nie znaleziono backendu OpenMES (brak wbudowanego zasobu).".to_string()
    })
}

/// PHP wbudowany w aplikację jako zasób (<resources>/php/php[.exe]).
fn bundled_php(app: &AppHandle) -> Option<String> {
    let res = deverbatim(app.path().resource_dir().ok()?);
    let candidate = if cfg!(windows) {
        res.join("php").join("php.exe")
    } else {
        res.join("php").join("php")
    };
    if candidate.is_file() {
        #[cfg(unix)]
        {
            use std::os::unix::fs::PermissionsExt;
            let _ = fs::set_permissions(&candidate, fs::Permissions::from_mode(0o755));
        }
        Some(candidate.to_string_lossy().into_owned())
    } else {
        None
    }
}

/// URL statycznego builda PHP dla bieżącej platformy.
fn php_download_url() -> Result<(&'static str, &'static str), String> {
    // (url, format) — format: "tar.gz" rozpakowuje binarkę `php`, "zip" → php.exe
    #[cfg(all(target_os = "linux", target_arch = "x86_64"))]
    {
        return Ok((
            "https://dl.static-php.dev/static-php-cli/common/php-8.3-cli-linux-x86_64.tar.gz",
            "tar.gz",
        ));
    }
    #[cfg(all(target_os = "linux", target_arch = "aarch64"))]
    {
        return Ok((
            "https://dl.static-php.dev/static-php-cli/common/php-8.3-cli-linux-aarch64.tar.gz",
            "tar.gz",
        ));
    }
    #[cfg(target_os = "windows")]
    {
        return Ok((
            "https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip",
            "zip",
        ));
    }
    #[cfg(target_os = "macos")]
    {
        return Ok((
            "https://dl.static-php.dev/static-php-cli/common/php-8.3-cli-macos-x86_64.tar.gz",
            "tar.gz",
        ));
    }
    #[allow(unreachable_code)]
    Err("Brak statycznego builda PHP dla tej platformy.".to_string())
}

/// Pobiera i rozpakowuje przenośny PHP do katalogu danych aplikacji.
fn download_php(app: &AppHandle) -> Result<String, String> {
    let (url, format) = php_download_url()?;
    let runtime = data_dir(app).join("runtime").join("php");
    fs::create_dir_all(&runtime).map_err(|e| e.to_string())?;

    let php_path = if cfg!(windows) {
        runtime.join("php.exe")
    } else {
        runtime.join("php")
    };
    if php_path.is_file() {
        return Ok(php_path.to_string_lossy().into_owned());
    }

    emit_progress(app, "Pobieranie PHP (jednorazowo, ~30 MB)…");
    let archive = runtime.join(if format == "zip" { "php.zip" } else { "php.tgz" });

    // Pobieranie systemowym narzędziem (bez ciężkich zależności HTTP).
    #[cfg(windows)]
    let dl = Command::new("powershell")
        .args([
            "-NoProfile",
            "-Command",
            &format!(
                "Invoke-WebRequest -Uri '{}' -OutFile '{}'",
                url,
                archive.display()
            ),
        ])
        .status();
    #[cfg(not(windows))]
    let dl = Command::new("curl")
        .args(["-fL", "--retry", "2", url, "-o"])
        .arg(&archive)
        .status();

    match dl {
        Ok(s) if s.success() => {}
        Ok(_) => return Err("Pobieranie PHP nie powiodło się.".into()),
        Err(e) => return Err(format!("Pobieranie PHP: {e}")),
    }

    emit_progress(app, "Instalowanie PHP…");
    if format == "zip" {
        #[cfg(windows)]
        {
            let st = Command::new("powershell")
                .args([
                    "-NoProfile",
                    "-Command",
                    &format!(
                        "Expand-Archive -Force -Path '{}' -DestinationPath '{}'",
                        archive.display(),
                        runtime.display()
                    ),
                ])
                .status()
                .map_err(|e| e.to_string())?;
            if !st.success() {
                return Err("Rozpakowanie PHP nie powiodło się.".into());
            }
            // php.ini z włączonymi rozszerzeniami wymaganymi przez OpenMES.
            let ini = "extension_dir=ext\n\
                extension=gd\nextension=zip\nextension=sqlite3\nextension=pdo_sqlite\n\
                extension=pdo_pgsql\nextension=pdo_mysql\nextension=mbstring\n\
                extension=openssl\nextension=sodium\nextension=intl\nextension=fileinfo\n\
                extension=curl\nextension=bcmath\nextension=exif\n";
            let _ = fs::write(runtime.join("php.ini"), ini);
        }
    } else {
        let st = Command::new("tar")
            .arg("xzf")
            .arg(&archive)
            .arg("-C")
            .arg(&runtime)
            .status()
            .map_err(|e| e.to_string())?;
        if !st.success() {
            return Err("Rozpakowanie PHP nie powiodło się.".into());
        }
        #[cfg(unix)]
        {
            use std::os::unix::fs::PermissionsExt;
            let _ = fs::set_permissions(&php_path, fs::Permissions::from_mode(0o755));
        }
    }
    let _ = fs::remove_file(&archive);

    if php_path.is_file() {
        Ok(php_path.to_string_lossy().into_owned())
    } else {
        Err("PHP pobrane, ale nie znaleziono binarki po rozpakowaniu.".into())
    }
}

/// Gwarantuje gotowe środowisko: backend (wbudowany) + działający PHP
/// (systemowy albo pobrany). Zapisuje wynik do configu. Nigdy nie wymaga
/// ręcznego wskazywania ścieżek przez użytkownika.
#[tauri::command]
async fn ensure_runtime(app: AppHandle) -> Result<ServerConfig, String> {
    emit_progress(&app, "Sprawdzanie środowiska…");
    let mut cfg = load_config(&app);

    cfg.backend_dir = resolve_backend(&app)?;

    // Kolejność: PHP wbudowany w aplikację (zasób) → systemowy (dev) →
    // awaryjne pobranie. Na maszynie użytkownika zadziała wbudowany.
    let php = match bundled_php(&app) {
        Some(p) => p,
        None => match find_php() {
            Some((p, _)) => p,
            None => download_php(&app)?,
        },
    };
    if php_version_ok(&php).is_none() {
        return Err("PHP nie działa lub jest w wersji < 8.2.".into());
    }
    cfg.php_binary = php;

    let path = config_path(&app);
    fs::create_dir_all(path.parent().unwrap()).map_err(|e| e.to_string())?;
    fs::write(&path, serde_json::to_string_pretty(&cfg).unwrap()).map_err(|e| e.to_string())?;
    Ok(cfg)
}

/// Namierza PHP i katalog backendu bez udziału użytkownika.
#[tauri::command]
fn detect_environment(app: AppHandle) -> DetectedEnv {
    let php = find_php();
    DetectedEnv {
        php_binary: php.as_ref().map(|(p, _)| p.clone()),
        php_version: php.map(|(_, v)| v),
        backend_dir: find_backend(&app),
    }
}

#[tauri::command]
fn save_config(app: AppHandle, config: ServerConfig) -> Result<(), String> {
    let path = config_path(&app);
    fs::create_dir_all(path.parent().unwrap()).map_err(|e| e.to_string())?;
    fs::write(&path, serde_json::to_string_pretty(&config).unwrap()).map_err(|e| e.to_string())
}

#[tauri::command]
fn start_server(app: AppHandle, state: State<ServerState>) -> Result<(), String> {
    do_start(&app, &state)
}

#[tauri::command]
fn stop_server(app: AppHandle, state: State<ServerState>) {
    do_stop(&app, &state);
}

#[tauri::command]
fn server_status(app: AppHandle, state: State<ServerState>) -> ServerStatus {
    let cfg = load_config(&app);
    let mut guard = state.child.lock().unwrap();
    let running = match guard.as_mut() {
        Some(child) => match child.try_wait() {
            Ok(None) => true,
            _ => {
                *guard = None;
                false
            }
        },
        None => false,
    };
    let pid = guard.as_ref().map(|c| c.id());
    // Port faktyczny (po auto-wyborze), nie preferowany — na 8080 może siedzieć
    // cudzy serwis i fałszywie raportować gotowość.
    let port = state.port.lock().unwrap().unwrap_or(cfg.port);
    let port_open = running
        && TcpStream::connect_timeout(
            &format!("127.0.0.1:{port}").parse().unwrap(),
            Duration::from_millis(300),
        )
        .is_ok();
    let lan_enabled = cfg.host == "0.0.0.0";
    let lan_urls = if lan_enabled && running {
        lan_ips()
            .into_iter()
            .map(|ip| format!("http://{ip}:{port}"))
            .collect()
    } else {
        vec![]
    };
    ServerStatus {
        running,
        port_open,
        pid,
        url: format!("http://127.0.0.1:{port}"),
        lan_urls,
        lan_enabled,
    }
}

/// `php artisan migrate --force` na skonfigurowanej bazie (ręczny przycisk panelu).
#[tauri::command]
fn prepare_database(app: AppHandle, state: State<ServerState>) -> Result<String, String> {
    let cfg = load_config(&app);
    let pg_port = if cfg.db_mode != "remote" && pg_bin_dir(&app).is_some() {
        Some(ensure_postgres(&app, &state)?)
    } else {
        None
    };
    let mut cmd = base_command(&app, &cfg, pg_port)?;
    cmd.arg("artisan").arg("migrate").arg("--force");
    let out = cmd.output().map_err(|e| e.to_string())?;
    let text = format!(
        "{}{}",
        String::from_utf8_lossy(&out.stdout),
        String::from_utf8_lossy(&out.stderr)
    );
    if out.status.success() {
        Ok(text)
    } else {
        Err(text)
    }
}

#[tauri::command]
fn read_server_log(app: AppHandle) -> String {
    let path = data_dir(&app).join("server.log");
    match fs::read_to_string(path) {
        Ok(s) => {
            let lines: Vec<&str> = s.lines().collect();
            let start = lines.len().saturating_sub(200);
            lines[start..].join("\n")
        }
        Err(_) => String::new(),
    }
}

/// Przełącza okno główne na UI OpenMES (serwer musi działać).
#[tauri::command]
fn navigate_main(app: AppHandle, state: State<ServerState>) -> Result<(), String> {
    let port = state
        .port
        .lock()
        .unwrap()
        .ok_or("Serwer nie działa.".to_string())?;
    let url: tauri::Url = format!("http://127.0.0.1:{port}")
        .parse()
        .map_err(|e| format!("{e}"))?;
    let win = app
        .get_webview_window("main")
        .ok_or("Brak okna głównego.".to_string())?;
    win.navigate(url).map_err(|e| e.to_string())?;
    let _ = win.show();
    let _ = win.set_focus();
    Ok(())
}

fn open_settings_window(app: &AppHandle) {
    if let Some(win) = app.get_webview_window("settings") {
        let _ = win.show();
        let _ = win.set_focus();
        return;
    }
    let _ = tauri::WebviewWindowBuilder::new(
        app,
        "settings",
        tauri::WebviewUrl::App("index.html".into()),
    )
    .title("OpenMES Desktop — ustawienia")
    .inner_size(940.0, 700.0)
    .build();
}

// ── Aktualizacje (staged swap, desktop-safe) ─────────────────────────────────
//
// The desktop updater replaces only the Laravel code directory, which is
// effectively stateless: the SQLite DB, storage/ and runtime env live in the
// app-data root OUTSIDE `backend/` (see base_command), so the code dir can be
// swapped wholesale without touching user data. The self-contained release ZIP
// already bundles vendor/ and the built frontend, so NO composer/npm is needed
// on the user's machine. Download/verify/extract reuse the bundled PHP runtime
// (curl/openssl/zip) — zero extra Rust dependencies.

const UPDATE_CHECK_URL: &str = "https://getopenmes.com/current-version.php";

/// Release-feed URL. Overridable via `OPENMES_UPDATE_URL` (used to point at a
/// staging/local feed for testing without touching the production endpoint).
fn update_check_url() -> String {
    std::env::var("OPENMES_UPDATE_URL").unwrap_or_else(|_| UPDATE_CHECK_URL.to_string())
}

#[derive(Serialize, Clone)]
struct UpdateInfo {
    available: bool,
    current: String,
    latest: String,
    name: String,
    changelog: Option<String>,
    zip_url: Option<String>,
    sha256: Option<String>,
}

#[derive(Serialize, Clone)]
struct UpdateProgress {
    state: String,
    message: String,
    progress: u8,
}

fn emit_update(app: &AppHandle, state: &str, message: &str, progress: u8) {
    let _ = app.emit(
        "update-progress",
        UpdateProgress {
            state: state.into(),
            message: message.into(),
            progress,
        },
    );
}

/// PHP binary to drive HTTP/zip work — prefers the configured one, falls back to
/// autodetection (bundled or system PHP).
fn resolve_php(app: &AppHandle) -> Result<String, String> {
    let cfg = load_config(app);
    if Path::new(&cfg.php_binary).is_file() {
        return Ok(cfg.php_binary);
    }
    find_php()
        .map(|(p, _)| p)
        .ok_or_else(|| "Nie znaleziono PHP.".to_string())
}

/// Pulls the `'current' => 'vX.Y.Z'` literal out of a Laravel `config/version.php`.
fn parse_version_current(php_src: &str) -> Option<String> {
    let after = &php_src[php_src.find("'current'")?..];
    let after = &after[after.find("=>")?..];
    let after = &after[after.find('\'')? + 1..];
    Some(after[..after.find('\'')?].to_string())
}

fn installed_version(app: &AppHandle) -> String {
    resolve_backend(app)
        .ok()
        .map(PathBuf::from)
        .and_then(|b| fs::read_to_string(b.join("config").join("version.php")).ok())
        .and_then(|s| parse_version_current(&s))
        .unwrap_or_else(|| "v0.0.0".into())
}

fn semver_tuple(v: &str) -> (u64, u64, u64) {
    let mut it = v
        .trim()
        .trim_start_matches('v')
        .split(|c: char| c == '.' || c == '-' || c == '+');
    let n = |o: Option<&str>| o.and_then(|x| x.parse().ok()).unwrap_or(0);
    (n(it.next()), n(it.next()), n(it.next()))
}

fn version_gt(a: &str, b: &str) -> bool {
    semver_tuple(a) > semver_tuple(b)
}

/// HTTP GET via the bundled PHP + curl. Returns the response body.
fn php_http_get(php: &str, url: &str) -> Result<String, String> {
    let code = r#"$c=curl_init($argv[1]);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_FAILONERROR=>true,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'OpenMES-Desktop']);$r=curl_exec($c);if($r===false){fwrite(STDERR,curl_error($c));exit(1);}echo $r;"#;
    let out = Command::new(php)
        .args(["-r", code, "--", url])
        .output()
        .map_err(|e| format!("Nie udało się uruchomić PHP: {e}"))?;
    if out.status.success() {
        Ok(String::from_utf8_lossy(&out.stdout).to_string())
    } else {
        Err(String::from_utf8_lossy(&out.stderr).trim().to_string())
    }
}

/// Downloads `url` to `dest` and returns the file's sha256 (computed in the same
/// PHP invocation, so we hash exactly what landed on disk).
fn php_download(php: &str, url: &str, dest: &Path) -> Result<String, String> {
    let code = r#"$f=fopen($argv[2],'w');if(!$f){fwrite(STDERR,'cannot open destination');exit(1);}$c=curl_init($argv[1]);curl_setopt_array($c,[CURLOPT_FILE=>$f,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_FAILONERROR=>true,CURLOPT_TIMEOUT=>600]);if(curl_exec($c)===false){fwrite(STDERR,curl_error($c));exit(1);}curl_close($c);fclose($f);echo hash_file('sha256',$argv[2]);"#;
    let out = Command::new(php)
        .args(["-r", code, "--", url, &dest.to_string_lossy()])
        .output()
        .map_err(|e| format!("Nie udało się uruchomić PHP: {e}"))?;
    if out.status.success() {
        Ok(String::from_utf8_lossy(&out.stdout).trim().to_string())
    } else {
        Err(String::from_utf8_lossy(&out.stderr).trim().to_string())
    }
}

/// Extracts `zip` into `dest` via PHP's ZipArchive.
fn php_extract(php: &str, zip: &Path, dest: &Path) -> Result<(), String> {
    let code = r#"$z=new ZipArchive();if($z->open($argv[1])!==true){fwrite(STDERR,'cannot open zip');exit(1);}if(!$z->extractTo($argv[2])){fwrite(STDERR,'extract failed');exit(1);}$z->close();echo 'OK';"#;
    let out = Command::new(php)
        .args([
            "-r",
            code,
            "--",
            &zip.to_string_lossy(),
            &dest.to_string_lossy(),
        ])
        .output()
        .map_err(|e| format!("Nie udało się uruchomić PHP: {e}"))?;
    if out.status.success() {
        Ok(())
    } else {
        Err(String::from_utf8_lossy(&out.stderr).trim().to_string())
    }
}

/// Finds the directory holding `artisan` inside a freshly extracted release
/// (the self-contained ZIP nests it under `openmmes-vX.Y.Z/backend`).
fn find_artisan_dir(root: &Path, depth: u32) -> Option<PathBuf> {
    if root.join("artisan").is_file() {
        return Some(root.to_path_buf());
    }
    if depth == 0 {
        return None;
    }
    for entry in fs::read_dir(root).ok()?.flatten() {
        let p = entry.path();
        if p.is_dir() {
            if let Some(found) = find_artisan_dir(&p, depth - 1) {
                return Some(found);
            }
        }
    }
    None
}

/// `php artisan migrate --force` against a specific backend directory, using the
/// same DB/storage env the running server uses (the DB is external to the code
/// dir, so migrating the staged copy migrates the live database).
fn migrate_on(app: &AppHandle, state: &ServerState, backend_dir: &Path) -> Result<String, String> {
    let mut cfg = load_config(app);
    cfg.backend_dir = backend_dir.to_string_lossy().into_owned();
    let pg_port = if cfg.db_mode != "remote" && pg_bin_dir(app).is_some() {
        Some(ensure_postgres(app, state)?)
    } else {
        None
    };
    let mut cmd = base_command(app, &cfg, pg_port)?;
    cmd.arg("artisan").arg("migrate").arg("--force");
    let out = cmd.output().map_err(|e| e.to_string())?;
    let text = format!(
        "{}{}",
        String::from_utf8_lossy(&out.stdout),
        String::from_utf8_lossy(&out.stderr)
    );
    if out.status.success() {
        Ok(text)
    } else {
        Err(text)
    }
}

/// Atomically replace `current` with `next`, moving the old dir to `backup`.
/// Rename-based, so there is never a half-copied app and no Windows file-lock
/// hazard from overwriting a running install in place.
fn swap_in_place(current: &Path, next: &Path, backup: &Path) -> std::io::Result<()> {
    if current.exists() {
        fs::rename(current, backup)?;
    }
    match fs::rename(next, current) {
        Ok(()) => Ok(()),
        Err(e) => {
            // Restore the original so a failed swap never loses the install.
            if backup.exists() && !current.exists() {
                let _ = fs::rename(backup, current);
            }
            Err(e)
        }
    }
}

/// Undo a completed swap: discard the (bad) new install and restore the backup.
fn rollback_swap(current: &Path, backup: &Path) -> std::io::Result<()> {
    if current.exists() {
        fs::remove_dir_all(current)?;
    }
    fs::rename(backup, current)
}

/// Keep only the newest `keep` backup directories.
fn prune_backups(dir: &Path, keep: usize) {
    let mut dirs: Vec<PathBuf> = match fs::read_dir(dir) {
        Ok(rd) => rd.flatten().map(|e| e.path()).filter(|p| p.is_dir()).collect(),
        Err(_) => return,
    };
    dirs.sort_by_key(|p| fs::metadata(p).and_then(|m| m.modified()).ok());
    if dirs.len() > keep {
        for p in &dirs[..dirs.len() - keep] {
            let _ = fs::remove_dir_all(p);
        }
    }
}

/// Resolves the self-contained package URL from the release feed. The live feed
/// (getopenmes.com) mirrors the GitHub release API: the downloadable `.zip` is an
/// entry in `assets[]` (`download_url`), not a top-level field. A top-level
/// `zip_url` is still honoured first for forward-compatibility.
fn pick_zip_url(json: &serde_json::Value) -> Option<String> {
    if let Some(u) = json.get("zip_url").and_then(|v| v.as_str()) {
        return Some(u.to_string());
    }
    let assets = json.get("assets").and_then(|v| v.as_array())?;
    let is_self_contained = |name: &str| {
        let n = name.to_ascii_lowercase();
        n.starts_with("openmmes-") && n.ends_with(".zip")
    };
    let url_of = |a: &serde_json::Value| {
        a.get("download_url")
            .or_else(|| a.get("browser_download_url"))
            .and_then(|v| v.as_str())
            .map(str::to_string)
    };
    assets
        .iter()
        .find(|a| {
            a.get("name")
                .and_then(|v| v.as_str())
                .map(is_self_contained)
                .unwrap_or(false)
        })
        .and_then(&url_of)
        .or_else(|| assets.first().and_then(&url_of))
}

/// Optional integrity hash from the feed (top-level or on the matched asset).
/// The current feed omits it, so verification is best-effort.
fn pick_sha256(json: &serde_json::Value) -> Option<String> {
    json.get("sha256")
        .and_then(|v| v.as_str())
        .map(|s| s.to_lowercase())
}

/// Check the release feed and compare against the installed version.
#[tauri::command]
async fn update_check(app: AppHandle) -> Result<UpdateInfo, String> {
    let php = resolve_php(&app)?;
    let body = php_http_get(&php, &update_check_url())?;
    let json: serde_json::Value = serde_json::from_str(&body)
        .map_err(|e| format!("Nieprawidłowa odpowiedź serwera aktualizacji: {e}"))?;
    let current = installed_version(&app);
    let latest = json
        .get("version")
        .and_then(|v| v.as_str())
        .unwrap_or("v0.0.0")
        .to_string();
    Ok(UpdateInfo {
        available: version_gt(&latest, &current),
        name: json
            .get("name")
            .and_then(|v| v.as_str())
            .unwrap_or(&latest)
            .to_string(),
        changelog: json
            .get("changelog")
            .and_then(|v| v.as_str())
            .map(str::to_string),
        zip_url: pick_zip_url(&json),
        sha256: pick_sha256(&json),
        current,
        latest,
    })
}

/// Download → verify → extract → stop → migrate → atomic swap → start, with
/// rollback on failure. Progress is streamed via the `update-progress` event.
#[tauri::command]
async fn update_apply(app: AppHandle, state: State<'_, ServerState>) -> Result<String, String> {
    run_update_apply(app.clone(), &state)
}

/// Sync core of update_apply. All steps are blocking (php/fs), so no async is
/// needed; the IPC command is a thin async wrapper around this.
fn run_update_apply(app: AppHandle, state: &ServerState) -> Result<String, String> {
    let php = resolve_php(&app)?;

    emit_update(&app, "checking", "Sprawdzanie aktualizacji…", 2);
    let body = php_http_get(&php, &update_check_url())?;
    let json: serde_json::Value = serde_json::from_str(&body)
        .map_err(|e| format!("Nieprawidłowa odpowiedź serwera aktualizacji: {e}"))?;
    let latest = json
        .get("version")
        .and_then(|v| v.as_str())
        .ok_or("Brak numeru wersji w odpowiedzi serwera.")?
        .to_string();
    let current = installed_version(&app);
    if !version_gt(&latest, &current) {
        emit_update(&app, "uptodate", "Masz już najnowszą wersję.", 100);
        return Ok(format!("Już aktualne ({current})."));
    }
    let zip_url = pick_zip_url(&json)
        .ok_or("Serwer aktualizacji nie wskazał paczki self-contained (openmmes-*.zip).")?;
    let expected_sha = pick_sha256(&json);

    let data = data_dir(&app);
    let target = data.join("backend");

    // The in-app updater only manages its own copy at <app-data>/backend. If the
    // desktop is serving some other backend (e.g. a dev checkout / git repo),
    // refuse — swapping a self-contained release over a working tree would be
    // wrong and destructive. Production always serves the managed copy.
    let served = load_config(&app).backend_dir;
    if served != target.to_string_lossy() {
        emit_update(&app, "failed", "Aktualizacja pominięta (nie-zarządzany backend).", 100);
        return Err(format!(
            "Aktualizacja w aplikacji dotyczy tylko zarządzanej kopii backendu ({}). \
             Serwowany backend to „{served}” — zaktualizuj go ręcznie.",
            target.display()
        ));
    }

    let tmp = data.join("update-tmp");
    let _ = fs::remove_dir_all(&tmp);
    fs::create_dir_all(&tmp).map_err(|e| e.to_string())?;
    let zip_path = tmp.join("update.zip");

    emit_update(&app, "downloading", &format!("Pobieranie {latest}…"), 15);
    let got_sha = php_download(&php, &zip_url, &zip_path).map_err(|e| {
        let _ = fs::remove_dir_all(&tmp);
        format!("Pobieranie nie powiodło się: {e}")
    })?;

    if let Some(exp) = &expected_sha {
        emit_update(&app, "verifying", "Weryfikacja sumy kontrolnej…", 40);
        if &got_sha.to_lowercase() != exp {
            let _ = fs::remove_dir_all(&tmp);
            emit_update(&app, "failed", "Suma kontrolna się nie zgadza.", 100);
            return Err("Suma kontrolna pobranej paczki się nie zgadza — aktualizację przerwano.".into());
        }
    }

    emit_update(&app, "extracting", "Rozpakowywanie…", 55);
    let extract_dir = tmp.join("extract");
    fs::create_dir_all(&extract_dir).map_err(|e| e.to_string())?;
    php_extract(&php, &zip_path, &extract_dir).map_err(|e| {
        let _ = fs::remove_dir_all(&tmp);
        format!("Rozpakowywanie nie powiodło się: {e}")
    })?;
    let staged = find_artisan_dir(&extract_dir, 4).ok_or_else(|| {
        let _ = fs::remove_dir_all(&tmp);
        "W paczce aktualizacji nie znaleziono backendu (artisan).".to_string()
    })?;

    // Stop the server before touching the code directory.
    emit_update(&app, "stopping", "Zatrzymywanie serwera…", 65);
    do_stop(&app, &state);

    // Snapshot the SQLite DB before migrating: the migration runs on the live
    // (external) database before the code swap, so if a later step fails and we
    // roll the code back, restoring this snapshot keeps the old code from
    // running against a newer schema. Remote / bundled-Postgres installs are not
    // snapshot here and keep the forward-only behaviour.
    let db_file = data.join("openmes.sqlite");
    let is_sqlite = load_config(&app).db_mode != "remote" && pg_bin_dir(&app).is_none();
    let db_snapshot = if is_sqlite && db_file.exists() {
        let bak = data.join("openmes.sqlite.pre-update");
        fs::copy(&db_file, &bak).ok().map(|_| bak)
    } else {
        None
    };
    let restore_db = |snap: &Option<PathBuf>| {
        if let Some(s) = snap {
            let _ = fs::copy(s, &db_file);
        }
    };
    let drop_snapshot = |snap: &Option<PathBuf>| {
        if let Some(s) = snap {
            let _ = fs::remove_file(s);
        }
    };

    // Migrate the (external) DB with the NEW code before swapping. If it fails,
    // nothing has been swapped yet — restore the DB snapshot and bring the old
    // server back up.
    emit_update(&app, "migrating", "Migracja bazy danych…", 75);
    if let Err(e) = migrate_on(&app, &state, &staged) {
        restore_db(&db_snapshot);
        let _ = do_start(&app, &state);
        drop_snapshot(&db_snapshot);
        let _ = fs::remove_dir_all(&tmp);
        emit_update(&app, "failed", "Migracja nie powiodła się — cofnięto.", 100);
        return Err(format!("Migracja nie powiodła się, aktualizację cofnięto:\n{e}"));
    }

    // Atomic swap: current backend -> backup, staged -> current backend.
    emit_update(&app, "installing", "Instalowanie nowej wersji…", 88);
    let backups = data.join("update-backups");
    let _ = fs::create_dir_all(&backups);
    let backup = backups.join(format!("backend-{current}-{}", std::process::id()));
    if let Err(e) = swap_in_place(&target, &staged, &backup) {
        restore_db(&db_snapshot);
        let _ = do_start(&app, &state);
        drop_snapshot(&db_snapshot);
        let _ = fs::remove_dir_all(&tmp);
        emit_update(&app, "failed", "Instalacja nie powiodła się — cofnięto.", 100);
        return Err(format!("Podmiana katalogu nie powiodła się, cofnięto:\n{e}"));
    }

    // Start the new version; roll back code AND the DB snapshot on a failed boot
    // so the restored old code never runs against the migrated schema.
    emit_update(&app, "starting", "Uruchamianie nowej wersji…", 95);
    if let Err(e) = do_start(&app, &state) {
        let _ = rollback_swap(&target, &backup);
        restore_db(&db_snapshot);
        let _ = do_start(&app, &state);
        drop_snapshot(&db_snapshot);
        let _ = fs::remove_dir_all(&tmp);
        emit_update(
            &app,
            "failed",
            "Nowa wersja nie wstała — przywrócono poprzednią.",
            100,
        );
        return Err(format!(
            "Nowa wersja nie uruchomiła się, przywrócono poprzednią:\n{e}"
        ));
    }

    drop_snapshot(&db_snapshot);
    let _ = fs::remove_dir_all(&tmp);
    prune_backups(&backups, 3);
    emit_update(&app, "completed", &format!("Zaktualizowano do {latest}."), 100);
    Ok(format!("Zaktualizowano do {latest}."))
}

// ── Aplikacja ────────────────────────────────────────────────────────────────

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let app = tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .manage(ServerState {
            child: Mutex::new(None),
            port: Mutex::new(None),
            pg_child: Mutex::new(None),
            pg_port: Mutex::new(None),
        })
        .invoke_handler(tauri::generate_handler![
            get_config,
            detect_environment,
            ensure_runtime,
            scan_for_database,
            test_db_connection,
            save_config,
            start_server,
            stop_server,
            server_status,
            prepare_database,
            read_server_log,
            navigate_main,
            update_check,
            update_apply
        ])
        .setup(|app| {
            // Tray: jedyne miejsce, z którego naprawdę wyłącza się aplikację
            // (i serwer). Zamknięcie okna tylko chowa panel.
            let show = MenuItem::with_id(app, "show", "Pokaż OpenMES", true, None::<&str>)?;
            let settings = MenuItem::with_id(app, "settings", "Ustawienia", true, None::<&str>)?;
            let quit = MenuItem::with_id(
                app,
                "quit",
                "Zakończ (zatrzymuje serwer)",
                true,
                None::<&str>,
            )?;
            let menu = Menu::with_items(app, &[&show, &settings, &quit])?;
            TrayIconBuilder::with_id("main-tray")
                .icon(app.default_window_icon().unwrap().clone())
                .tooltip("OpenMES Desktop")
                .menu(&menu)
                .show_menu_on_left_click(true)
                .on_menu_event(|app, event| match event.id.as_ref() {
                    "show" => {
                        if let Some(win) = app.get_webview_window("main") {
                            let _ = win.show();
                            let _ = win.set_focus();
                        }
                    }
                    "settings" => open_settings_window(app),
                    "quit" => {
                        do_stop(app, &app.state::<ServerState>());
                        app.exit(0);
                    }
                    _ => {}
                })
                .build(app)?;

            let cfg = load_config(app.handle());
            if cfg.autostart {
                // W tle — pierwszy start może robić migracje i nie może blokować UI.
                let handle = app.handle().clone();
                std::thread::spawn(move || {
                    let _ = do_start(&handle, &handle.state::<ServerState>());
                });
            }

            Ok(())
        })
        .on_window_event(|window, event| {
            if let WindowEvent::CloseRequested { api, .. } = event {
                if window.label() == "main" {
                    // Zamknięcie okna NIE wyłącza serwera — panel idzie do traya.
                    let _ = window.hide();
                    api.prevent_close();
                }
            }
        })
        .build(tauri::generate_context!())
        .expect("error while building tauri application");

    app.run(|app_handle, event| {
        if let RunEvent::Exit = event {
            // Ostatnia deska ratunku — nigdy nie zostawiamy osieroconego PHP/Postgresa.
            do_stop(app_handle, &app_handle.state::<ServerState>());
        }
    });
}

// ── Testy jednostkowe (logika updatera, bez GUI) ─────────────────────────────

#[cfg(test)]
mod update_tests {
    use super::*;

    fn tmp(name: &str) -> PathBuf {
        let dir = std::env::temp_dir().join(format!(
            "openmes-update-test-{}-{}-{name}",
            std::process::id(),
            line!()
        ));
        let _ = fs::remove_dir_all(&dir);
        fs::create_dir_all(&dir).unwrap();
        dir
    }

    fn write(dir: &Path, content: &str) {
        fs::create_dir_all(dir).unwrap();
        fs::write(dir.join("marker.txt"), content).unwrap();
    }

    fn read(dir: &Path) -> String {
        fs::read_to_string(dir.join("marker.txt")).unwrap()
    }

    #[test]
    fn parses_current_version_literal() {
        assert_eq!(
            parse_version_current("    'current' => 'v0.16.1',\n"),
            Some("v0.16.1".to_string())
        );
        assert_eq!(parse_version_current("no version here"), None);
    }

    #[test]
    fn version_comparison_is_semver_aware() {
        assert!(version_gt("v0.16.2", "v0.16.1"));
        assert!(version_gt("v0.17.0", "v0.16.9"));
        assert!(!version_gt("v0.16.1", "v0.16.1"));
        // Lexical comparison would wrongly rank "9" > "16"; semver must not.
        assert!(!version_gt("v0.9.9", "v0.16.0"));
    }

    #[test]
    fn picks_self_contained_zip_from_github_style_feed() {
        // Shape returned by the live getopenmes.com feed (GitHub release passthrough).
        let feed = serde_json::json!({
            "version": "v0.16.1",
            "assets": [
                { "name": "openmmes-v0.16.1.zip",
                  "download_url": "https://github.com/Mes-Open/OpenMes/releases/download/v0.16.1/openmmes-v0.16.1.zip",
                  "size_bytes": 36379819 }
            ]
        });
        assert_eq!(
            pick_zip_url(&feed).as_deref(),
            Some("https://github.com/Mes-Open/OpenMes/releases/download/v0.16.1/openmmes-v0.16.1.zip")
        );
        assert_eq!(pick_sha256(&feed), None, "live feed omits sha256");
    }

    #[test]
    fn top_level_zip_url_takes_precedence() {
        let feed = serde_json::json!({
            "version": "v1.0.0",
            "zip_url": "https://example.com/pkg.zip",
            "sha256": "ABCDEF",
            "assets": [ { "name": "openmmes-v1.0.0.zip", "download_url": "https://gh/other.zip" } ]
        });
        assert_eq!(pick_zip_url(&feed).as_deref(), Some("https://example.com/pkg.zip"));
        assert_eq!(pick_sha256(&feed).as_deref(), Some("abcdef"), "sha256 lower-cased");
    }

    #[test]
    fn no_package_url_when_feed_has_none() {
        let feed = serde_json::json!({ "version": "v1.0.0" });
        assert_eq!(pick_zip_url(&feed), None);
    }

    #[test]
    fn swap_replaces_current_and_keeps_backup() {
        let root = tmp("swap");
        let current = root.join("backend");
        let next = root.join("backend-next");
        let backup = root.join("backend-backup");
        write(&current, "old");
        write(&next, "new");

        swap_in_place(&current, &next, &backup).unwrap();

        assert_eq!(read(&current), "new", "current should hold the new install");
        assert_eq!(read(&backup), "old", "old install preserved as backup");
        assert!(!next.exists(), "staged dir was moved into place");
        let _ = fs::remove_dir_all(&root);
    }

    #[test]
    fn rollback_restores_previous_install() {
        let root = tmp("rollback");
        let current = root.join("backend");
        let next = root.join("backend-next");
        let backup = root.join("backend-backup");
        write(&current, "old");
        write(&next, "new");

        swap_in_place(&current, &next, &backup).unwrap();
        rollback_swap(&current, &backup).unwrap();

        assert_eq!(read(&current), "old", "rollback restores the old install");
        assert!(!backup.exists(), "backup consumed by rollback");
        let _ = fs::remove_dir_all(&root);
    }

    #[test]
    fn swap_works_on_first_install_with_no_current() {
        let root = tmp("first");
        let current = root.join("backend");
        let next = root.join("backend-next");
        let backup = root.join("backend-backup");
        write(&next, "new");
        // current does not exist yet (fresh machine).

        swap_in_place(&current, &next, &backup).unwrap();

        assert_eq!(read(&current), "new");
        assert!(!backup.exists(), "no backup created when there was nothing to back up");
        let _ = fs::remove_dir_all(&root);
    }

    /// End-to-end transport test: real bundled PHP performs the actual
    /// curl download + sha256 + ZipArchive extract we use in update_apply,
    /// then the staged backend is atomically swapped into place. Uses a
    /// `file://` URL so no network/HTTP server is needed.
    #[test]
    fn real_download_verify_extract_and_swap() {
        let php = match find_php() {
            Some((p, _)) => p,
            None => return, // no PHP in this environment — skip
        };
        let root = tmp("e2e");

        // Build a fake self-contained release: openmmes-vtest/backend/artisan
        let src_backend = root.join("openmmes-vtest").join("backend");
        fs::create_dir_all(src_backend.join("config")).unwrap();
        fs::write(src_backend.join("artisan"), "#!/usr/bin/env php\n").unwrap();
        fs::write(
            src_backend.join("config").join("version.php"),
            "<?php return ['current' => 'v9.9.9'];",
        )
        .unwrap();

        // Zip it with the same PHP ZipArchive the updater relies on.
        let pkg = root.join("pkg.zip");
        let zip_php = format!(
            r#"$z=new ZipArchive();$z->open('{}',ZipArchive::CREATE);$base='{}';$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));foreach($it as $f){{$z->addFile($f->getPathname(),substr($f->getPathname(),strlen(dirname($base))+1));}}$z->close();echo 'OK';"#,
            pkg.display(),
            root.join("openmmes-vtest").display()
        );
        let out = Command::new(&php).args(["-r", &zip_php]).output().unwrap();
        assert!(out.status.success(), "zip build failed: {}", String::from_utf8_lossy(&out.stderr));

        // 1. Download via curl (file://) + hash — the real php_download.
        let url = format!("file://{}", pkg.display());
        let dl = root.join("dl.zip");
        let sha = php_download(&php, &url, &dl).expect("download+hash");
        assert_eq!(sha.len(), 64, "sha256 hex expected, got {sha:?}");
        assert!(dl.is_file(), "downloaded file exists");

        // 2. Extract via ZipArchive — the real php_extract.
        let extract = root.join("extract");
        fs::create_dir_all(&extract).unwrap();
        php_extract(&php, &dl, &extract).expect("extract");

        // 3. Locate the backend inside the extracted tree.
        let staged = find_artisan_dir(&extract, 4).expect("staged backend found");
        assert!(staged.join("artisan").is_file());
        assert_eq!(
            parse_version_current(
                &fs::read_to_string(staged.join("config").join("version.php")).unwrap()
            )
            .as_deref(),
            Some("v9.9.9")
        );

        // 4. Atomic swap into a target location.
        let target = root.join("installed-backend");
        write(&target, "old");
        let backup = root.join("backup");
        swap_in_place(&target, &staged, &backup).expect("swap");
        assert!(target.join("artisan").is_file(), "new backend installed at target");
        assert_eq!(read(&backup), "old", "previous install backed up");

        let _ = fs::remove_dir_all(&root);
    }

    #[test]
    fn prune_keeps_only_newest_backups() {
        let root = tmp("prune");
        for i in 0..5 {
            let d = root.join(format!("backend-{i}"));
            fs::create_dir_all(&d).unwrap();
            // Space out modified times so pruning order is deterministic.
            std::thread::sleep(std::time::Duration::from_millis(15));
        }
        prune_backups(&root, 3);
        let remaining = fs::read_dir(&root).unwrap().count();
        assert_eq!(remaining, 3, "only the 3 newest backups should remain");
        let _ = fs::remove_dir_all(&root);
    }
}
