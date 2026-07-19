import { invoke } from "@tauri-apps/api/core";
import { getCurrentWindow } from "@tauri-apps/api/window";
import { listen } from "@tauri-apps/api/event";

// Okno "settings" pokazuje panel od razu; okno "main" pokazuje splash i po
// starcie serwera przełącza się na UI OpenMES - panel widzi tylko przy błędzie.
const isSettingsWindow = getCurrentWindow().label === "settings";

interface ServerConfig {
  php_binary: string;
  backend_dir: string;
  host: string;
  port: number;
  autostart: boolean;
  extra_env: string;
  db_mode: string;
  db_driver: string;
  db_host: string;
  db_port: number;
  db_database: string;
  db_username: string;
  db_password: string;
}

// Aktualny config - readForm() scala pola panelu z resztą (np. ustawieniami
// bazy), żeby zapis z panelu ich nie wyzerował.
let current: ServerConfig | null = null;

interface DetectedEnv {
  php_binary: string | null;
  php_version: string | null;
  backend_dir: string | null;
}

interface ServerStatus {
  running: boolean;
  port_open: boolean;
  pid: number | null;
  url: string;
  lan_urls: string[];
  lan_enabled: boolean;
}

const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;

const badge = $("status-badge");
const message = $<HTMLParagraphElement>("message");
const urls = $("urls");
const btnStart = $<HTMLButtonElement>("btn-start");
const btnStop = $<HTMLButtonElement>("btn-stop");
const btnOpen = $<HTMLButtonElement>("btn-open");
const btnMigrate = $<HTMLButtonElement>("btn-migrate");
const btnLog = $<HTMLButtonElement>("btn-log");
const btnUpdateCheck = $<HTMLButtonElement>("btn-update-check");
const btnUpdateApply = $<HTMLButtonElement>("btn-update-apply");

function showMessage(text: string, isError = false) {
  message.textContent = text;
  message.classList.toggle("error", isError);
}

function readForm(): ServerConfig {
  return {
    ...(current as ServerConfig),
    php_binary: $<HTMLInputElement>("cfg-php").value.trim() || "php",
    backend_dir: $<HTMLInputElement>("cfg-backend").value.trim(),
    host: $<HTMLSelectElement>("cfg-host").value,
    port: Number($<HTMLInputElement>("cfg-port").value) || 8080,
    autostart: $<HTMLInputElement>("cfg-autostart").checked,
    extra_env: $<HTMLTextAreaElement>("cfg-env").value,
  };
}

function fillForm(cfg: ServerConfig) {
  $<HTMLInputElement>("cfg-php").value = cfg.php_binary;
  $<HTMLInputElement>("cfg-backend").value = cfg.backend_dir;
  $<HTMLSelectElement>("cfg-host").value = cfg.host;
  $<HTMLInputElement>("cfg-port").value = String(cfg.port);
  $<HTMLInputElement>("cfg-autostart").checked = cfg.autostart;
  $<HTMLTextAreaElement>("cfg-env").value = cfg.extra_env;
}

async function refreshStatus() {
  const s = await invoke<ServerStatus>("server_status");
  const ready = s.running && s.port_open;

  badge.className = "badge " + (ready ? "running" : s.running ? "starting" : "stopped");
  badge.textContent = ready
    ? "Działa" + (s.pid ? ` (PID ${s.pid})` : "")
    : s.running
      ? "Uruchamianie…"
      : "Zatrzymany";

  btnStart.disabled = s.running;
  btnStop.disabled = !s.running;
  btnOpen.disabled = !ready;

  if (s.running) {
    const items = [`<a href="${s.url}" target="_blank">${s.url}</a> (ten komputer)`];
    if (s.lan_enabled) {
      if (s.lan_urls.length) {
        for (const u of s.lan_urls) {
          items.push(`<a href="${u}" target="_blank">${u}</a> (sieć lokalna - telefony/tablety)`);
        }
      } else {
        items.push("Sieć lokalna włączona, ale nie wykryto adresu IP.");
      }
    }
    urls.innerHTML = items.map((i) => `<div>${i}</div>`).join("");
  } else {
    urls.innerHTML = "";
  }
}

async function refreshLog() {
  $("log").textContent = await invoke<string>("read_server_log");
}

btnStart.addEventListener("click", async () => {
  showMessage("");
  try {
    await invoke("save_config", { config: readForm() });
    await invoke("start_server");
    showMessage("Serwer uruchomiony.");
  } catch (e) {
    showMessage(String(e), true);
  }
  refreshStatus();
});

btnStop.addEventListener("click", async () => {
  await invoke("stop_server");
  showMessage("Serwer zatrzymany.");
  refreshStatus();
});

btnOpen.addEventListener("click", async () => {
  try {
    await invoke("navigate_main");
  } catch (e) {
    showMessage(String(e), true);
  }
});

btnMigrate.addEventListener("click", async () => {
  showMessage("Trwa przygotowanie bazy…");
  btnMigrate.disabled = true;
  try {
    await invoke("save_config", { config: readForm() });
    const out = await invoke<string>("prepare_database");
    showMessage("Baza gotowa.");
    $("log").textContent = out;
  } catch (e) {
    showMessage(String(e), true);
  } finally {
    btnMigrate.disabled = false;
  }
});

btnLog.addEventListener("click", refreshLog);

// ── Aktualizacje ─────────────────────────────────────────────────────────────

type UpdateInfo = {
  available: boolean;
  current: string;
  latest: string;
  name: string;
  changelog: string | null;
  zip_url: string | null;
  sha256: string | null;
};

const updCurrent = $<HTMLParagraphElement>("update-current");
const updMessage = $<HTMLParagraphElement>("update-message");
const updProgressWrap = $("update-progress-wrap");
const updProgressBar = $("update-progress-bar");
const updProgressLabel = $<HTMLParagraphElement>("update-progress-label");

function showUpdateMessage(text: string, isError = false) {
  updMessage.textContent = text;
  updMessage.classList.toggle("error", isError);
}

btnUpdateCheck.addEventListener("click", async () => {
  showUpdateMessage("Sprawdzanie…");
  btnUpdateCheck.disabled = true;
  btnUpdateApply.classList.add("hidden");
  try {
    const info = await invoke<UpdateInfo>("update_check");
    updCurrent.textContent = `Zainstalowana: ${info.current} · Najnowsza: ${info.latest}`;
    if (info.available) {
      showUpdateMessage(`Dostępna aktualizacja: ${info.name}`);
      btnUpdateApply.classList.remove("hidden");
    } else {
      showUpdateMessage("Masz najnowszą wersję.");
    }
  } catch (e) {
    showUpdateMessage(String(e), true);
  } finally {
    btnUpdateCheck.disabled = false;
  }
});

btnUpdateApply.addEventListener("click", async () => {
  if (!confirm("Zainstalować aktualizację? Serwer zostanie na chwilę zatrzymany.")) return;
  btnUpdateApply.disabled = true;
  btnUpdateCheck.disabled = true;
  updProgressWrap.classList.remove("hidden");
  updProgressBar.style.width = "0%";
  try {
    const result = await invoke<string>("update_apply");
    showUpdateMessage(result);
    btnUpdateApply.classList.add("hidden");
  } catch (e) {
    showUpdateMessage(String(e), true);
  } finally {
    btnUpdateApply.disabled = false;
    btnUpdateCheck.disabled = false;
    refreshStatus();
  }
});

// Live progress from the Rust updater (download → verify → … → completed).
listen<{ state: string; message: string; progress: number }>("update-progress", (e) => {
  updProgressWrap.classList.remove("hidden");
  updProgressBar.style.width = `${e.payload.progress}%`;
  updProgressLabel.textContent = e.payload.message;
});

$<HTMLFormElement>("config-form").addEventListener("submit", async (e) => {
  e.preventDefault();
  try {
    await invoke("save_config", { config: readForm() });
    showMessage("Ustawienia zapisane.");
  } catch (err) {
    showMessage(String(err), true);
  }
  refreshStatus();
});

async function autoDetect(): Promise<DetectedEnv> {
  const d = await invoke<DetectedEnv>("detect_environment");
  if (d.php_binary) $<HTMLInputElement>("cfg-php").value = d.php_binary;
  if (d.backend_dir) $<HTMLInputElement>("cfg-backend").value = d.backend_dir;
  if (!d.php_binary) {
    showMessage(
      "Nie znaleziono PHP ≥ 8.2 w systemie. Zainstaluj PHP albo wpisz ścieżkę do binarki ręcznie.",
      true,
    );
  } else if (!d.backend_dir) {
    showMessage(
      `Wykryto PHP ${d.php_version} (${d.php_binary}), ale nie znaleziono katalogu backendu OpenMES - wskaż go ręcznie.`,
      true,
    );
  } else {
    showMessage(`Wykryto PHP ${d.php_version} (${d.php_binary}) i backend: ${d.backend_dir}`);
  }
  return d;
}

$<HTMLButtonElement>("btn-detect").addEventListener("click", async () => {
  showMessage("Wykrywanie środowiska…");
  await autoDetect();
});

function setSplash(text: string) {
  $("splash-status").textContent = text;
}

/// Zamienia surowe błędy techniczne na komunikat zrozumiały dla operatora.
function humanizeError(raw: string): { title: string; msg: string } {
  const r = raw.toLowerCase();
  if (r.includes("connection refused") || r.includes("could not connect") || r.includes("could not translate")) {
    return {
      title: "Brak połączenia z bazą danych",
      msg: "Serwer bazy danych nie odpowiada pod podanym adresem. Sprawdź, czy serwer jest włączony i dostępny w sieci, albo wybierz bazę lokalną na tym komputerze.",
    };
  }
  if (r.includes("password") || r.includes("authentication")) {
    return {
      title: "Błędne dane logowania do bazy",
      msg: "Nazwa użytkownika lub hasło do bazy danych są nieprawidłowe. Popraw je w ustawieniach bazy.",
    };
  }
  if (r.includes("does not exist") || r.includes("unknown database")) {
    return {
      title: "Baza danych nie istnieje",
      msg: "Podana baza danych nie istnieje na serwerze. Sprawdź nazwę bazy w ustawieniach.",
    };
  }
  if (r.includes("nie odpowiada")) {
    return { title: "Serwer nie wystartował", msg: raw };
  }
  return { title: "Nie udało się uruchomić OpenMES", msg: raw };
}

/// Ekran błędu startu z konkretnymi akcjami - nigdy nie zostawiamy użytkownika
/// z martwym komunikatem na białym tle.
function bootError(raw: string) {
  const { title, msg } = humanizeError(raw);
  $("boot-error-title").textContent = title;
  $("boot-error-msg").textContent = msg;
  $("boot-error-details").textContent = raw;
  $("boot-error-details").classList.add("hidden");
  ["splash", "db-choice", "db-remote", "panel"].forEach((id) => show(id, false));
  show("boot-error", true);
}

async function waitUntilReady(timeoutMs: number): Promise<boolean> {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    const s = await invoke<ServerStatus>("server_status");
    if (s.running && s.port_open) return true;
    if (!s.running) return false;
    await new Promise((r) => setTimeout(r, 700));
  }
  return false;
}

function show(id: string, visible: boolean) {
  $(id).classList.toggle("hidden", !visible);
}

function applyFoundServer() {
  const v = $<HTMLSelectElement>("db-found").value;
  const [host, port] = v.split(":");
  $<HTMLInputElement>("db-host").value = host;
  $<HTMLInputElement>("db-port").value = port;
}

async function scanAndFillDbForm() {
  $("db-scan-status").textContent = "Szukam bazy danych w sieci lokalnej…";
  show("db-scan-spinner", true);
  $("db-remote-form").classList.add("hidden");
  const found = await invoke<string[]>("scan_for_database");
  show("db-scan-spinner", false);
  show("db-remote-form", true);
  const sel = $<HTMLSelectElement>("db-found");
  sel.innerHTML = "";
  const selLabel = sel.closest("label")!;
  if (found.length) {
    $("db-scan-status").textContent =
      `Znaleziono serwer(y) bazy danych (${found.length}) - wybierz i podaj dane logowania:`;
    for (const f of found) {
      const o = document.createElement("option");
      o.value = f;
      o.textContent = f;
      sel.appendChild(o);
    }
    selLabel.classList.remove("hidden");
    sel.onchange = applyFoundServer;
    applyFoundServer();
  } else {
    $("db-scan-status").textContent =
      "Nie znaleziono bazy danych w sieci lokalnej. Podaj dane połączenia ręcznie:";
    selLabel.classList.add("hidden");
  }
}

/// Pierwsze uruchomienie: wybór bazy lokalnej (SQLite) lub zdalnej (skan sieci
/// po IP; brak wyniku -> prośba o dane ręcznie). Rozwiązuje się po zapisie.
function ensureDbMode(cfg: ServerConfig): Promise<void> {
  if (cfg.db_mode) return Promise.resolve();
  show("splash", false);
  show("db-choice", true);

  return new Promise((resolve) => {
    $("btn-db-local").addEventListener("click", async () => {
      cfg.db_mode = "local";
      await invoke("save_config", { config: cfg });
      show("db-choice", false);
      show("splash", true);
      resolve();
    });

    $("btn-db-remote").addEventListener("click", () => {
      show("db-choice", false);
      show("db-remote", true);
      scanAndFillDbForm();
    });

    $("btn-db-back").addEventListener("click", () => {
      show("db-remote", false);
      show("db-choice", true);
    });

    $<HTMLFormElement>("db-remote-form").addEventListener("submit", async (e) => {
      e.preventDefault();
      const msg = $("db-message");
      const port = Number($<HTMLInputElement>("db-port").value) || 5432;
      const candidate: ServerConfig = {
        ...cfg,
        db_mode: "remote",
        db_driver: port === 3306 ? "mysql" : "pgsql",
        db_host: $<HTMLInputElement>("db-host").value.trim(),
        db_port: port,
        db_database: $<HTMLInputElement>("db-database").value.trim(),
        db_username: $<HTMLInputElement>("db-username").value.trim(),
        db_password: $<HTMLInputElement>("db-password").value,
      };
      msg.classList.remove("error");
      msg.textContent = "Sprawdzam połączenie…";
      try {
        await invoke("test_db_connection", { config: candidate });
      } catch (err) {
        msg.classList.add("error");
        msg.textContent = `Nie udało się połączyć: ${err}`;
        return;
      }
      Object.assign(cfg, candidate);
      await invoke("save_config", { config: cfg });
      show("db-remote", false);
      show("splash", true);
      resolve();
    });
  });
}

/// Tryb główny: wszystko w tle, użytkownik ma zobaczyć tylko OpenMES.
async function bootFlow() {
  setSplash("Sprawdzanie środowiska…");

  // Samodzielny bootstrap: wbudowany backend + PHP (systemowy albo pobrany).
  // Użytkownik nigdy nie wskazuje ścieżek ręcznie.
  let cfg: ServerConfig;
  try {
    cfg = await invoke<ServerConfig>("ensure_runtime");
  } catch (e) {
    bootError(String(e));
    return;
  }
  current = cfg;
  fillForm(cfg);

  await ensureDbMode(cfg);
  current = cfg;

  const st = await invoke<ServerStatus>("server_status");
  if (!st.running) {
    setSplash("Uruchamianie OpenMES… (pierwszy start przygotowuje bazę i może chwilę potrwać)");
    try {
      await invoke("start_server");
    } catch (e) {
      bootError(String(e));
      return;
    }
  }

  setSplash("Łączenie z serwerem…");
  if (await waitUntilReady(90_000)) {
    show("boot-error", false);
    await invoke("navigate_main");
  } else {
    bootError("Serwer nie odpowiada - uruchomienie przekroczyło limit czasu.");
  }
}

/// Czysty restart startu: zatrzymaj ewentualny serwer i przejdź flow od nowa.
async function retryBoot() {
  ["boot-error", "db-choice", "db-remote", "panel"].forEach((id) => show(id, false));
  document.body.classList.remove("show-panel");
  show("splash", true);
  setSplash("Uruchamianie…");
  try {
    await invoke("stop_server");
  } catch {
    /* serwer mógł nie działać - nieistotne */
  }
  await bootFlow();
}

function wireBootErrorActions() {
  $("be-retry").addEventListener("click", retryBoot);

  // „Zmień ustawienia bazy" - wyczyść wybór i wróć do ekranu wyboru bazy.
  $("be-db").addEventListener("click", async () => {
    if (current) {
      current.db_mode = "";
      await invoke("save_config", { config: current });
    }
    await retryBoot();
  });

  // „Użyj bazy lokalnej" - przełącz na SQLite i startuj od nowa.
  $("be-local").addEventListener("click", async () => {
    if (current) {
      current.db_mode = "local";
      await invoke("save_config", { config: current });
    }
    await retryBoot();
  });

  $("be-details").addEventListener("click", () => {
    $("boot-error-details").classList.toggle("hidden");
  });
}

async function init() {
  if (isSettingsWindow) {
    document.body.classList.add("show-panel", "hide-splash");
    const cfg = await invoke<ServerConfig>("get_config");
    current = cfg;
    fillForm(cfg);
    refreshStatus();
    setInterval(refreshStatus, 2000);
    return;
  }
  wireBootErrorActions();
  // Postęp bootstrapu (kopiowanie backendu, pobieranie PHP) na splashu.
  listen<string>("bootstrap-progress", (e) => setSplash(e.payload));
  setInterval(refreshStatus, 2000);
  await bootFlow();
}

init();
