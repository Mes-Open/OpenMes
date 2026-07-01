import { useState } from "react";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Button, Checkbox, Dropdown, Switch, Tabs } from "@openmes/ui";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
const CURRENCIES = [
  ["PLN", "Polish Z\u0142oty"],
  ["EUR", "Euro"],
  ["USD", "US Dollar"],
  ["GBP", "British Pound"],
  ["CHF", "Swiss Franc"],
  ["CZK", "Czech Koruna"],
  ["SEK", "Swedish Krona"],
  ["NOK", "Norwegian Krone"],
  ["DKK", "Danish Krone"],
  ["HUF", "Hungarian Forint"],
  ["RON", "Romanian Leu"],
  ["UAH", "Ukrainian Hryvnia"],
  ["VND", "Vietnamese \u0110\u1ED3ng"]
];
const CARD_CLASS = "bg-om-card border border-om-line rounded-om p-6";
const LABEL_CLASS = "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]";
const HELP_CLASS = "text-om-muted text-[12.5px]";
const ERROR_CLASS = "text-[11.5px] text-om-blocked mt-1";
const INPUT_BASE = "bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]";
function SelectCard({ value, current, onChange, label, desc, disabled }) {
  const isSelected = value === current;
  return /* @__PURE__ */ React.createElement(
    "div",
    {
      onClick: () => !disabled && onChange(value),
      className: `flex flex-col gap-1 border rounded-om-sm p-3 transition-colors
                ${disabled ? "opacity-50 cursor-not-allowed" : "cursor-pointer"}
                ${isSelected ? "border-om-accent bg-[rgba(234,90,43,.06)]" : "border-om-line hover:border-om-faint"}`
    },
    /* @__PURE__ */ React.createElement("span", { className: "font-medium text-[13px] text-om-ink" }, label),
    desc && /* @__PURE__ */ React.createElement("span", { className: "text-[11.5px] text-om-muted" }, desc)
  );
}
function System() {
  const { settings, availableLocales, appUrl, modules = [], backups } = usePage().props;
  const [tab, setTab] = useState("general");
  const [sampleConfirm, setSampleConfirm] = useState(false);
  const [resetConfirm, setResetConfirm] = useState(false);
  const [resetText, setResetText] = useState("");
  const [isResetting, setIsResetting] = useState(false);
  const [resetCountdown, setResetCountdown] = useState(null);
  const [isRestoring, setIsRestoring] = useState(false);
  const [restoreCountdown, setRestoreCountdown] = useState(null);
  const [statusMessage, setStatusMessage] = useState("");
  const { csrf_token } = usePage().props;
  const { data, setData, post, processing, errors } = useForm({
    production_period: settings.production_period ?? "none",
    allow_overproduction: settings.allow_overproduction ?? false,
    force_sequential_steps: settings.force_sequential_steps ?? true,
    workstation_routing_enabled: settings.workstation_routing_enabled ?? false,
    scanner_mode: settings.scanner_mode ?? "hid",
    workflow_mode: settings.workflow_mode ?? "status",
    pin_login_enabled: settings.pin_login_enabled ?? false,
    language: settings.language ?? "en",
    schedule_view_mode: settings.schedule_view_mode ?? "weekly",
    schedule_shifts_per_day: settings.schedule_shifts_per_day ?? 1,
    schedule_horizon_weeks: settings.schedule_horizon_weeks ?? 6,
    schedule_show_weekends: settings.schedule_show_weekends ?? true,
    realtime_mode: settings.realtime_mode ?? "polling",
    production_tracking_mode: settings.production_tracking_mode ?? "per_operation",
    cors_allowed_origins: settings.cors_allowed_origins ?? "",
    cors_allowed_methods: settings.cors_allowed_methods ?? "GET, POST",
    cors_max_age: settings.cors_max_age ?? 0,
    production_qty_edit_policy: settings.production_qty_edit_policy ?? "none",
    production_qty_edit_window_minutes: settings.production_qty_edit_window_minutes ?? 1,
    standard_weekly_hours: settings.standard_weekly_hours ?? 40,
    default_currency: settings.default_currency ?? "PLN",
    default_pay_type: settings.default_pay_type ?? "hourly",
    default_pay_rate: settings.default_pay_rate ?? null,
    enabled_modules: modules.filter((m) => m.enabled).map((m) => m.key)
  });
  const toggleModule = (key, on) => setData("enabled_modules", on ? [...data.enabled_modules, key] : data.enabled_modules.filter((k) => k !== key));
  function handleSubmit(e) {
    e.preventDefault();
    const languageChanged = data.language !== settings.language;
    post("/settings/system", {
      onSuccess: () => {
        if (languageChanged) window.location.reload();
      }
    });
  }
  const handleResetSubmit = async (e) => {
    e.preventDefault();
    if (resetText !== "RESET") return;
    setIsResetting(true);
    setStatusMessage(__("Resetting the system... Please wait..."));
    try {
      const response = await fetch("/settings/reset", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrf_token,
          "Accept": "application/json"
        },
        body: JSON.stringify({
          confirm_text: resetText
        })
      });
      const result = await response.json();
      if (response.ok && result.success) {
        let count = 5;
        setResetCountdown(count);
        setStatusMessage(__("System reset successfully."));
        const interval = setInterval(() => {
          count -= 1;
          setResetCountdown(count);
          if (count <= 0) {
            clearInterval(interval);
            window.location.href = "/";
          }
        }, 1e3);
      } else {
        setIsResetting(false);
        alert(result.message || __("An error occurred while resetting the system."));
      }
    } catch (err) {
      setIsResetting(false);
      alert(__("Connection error: ") + err.message);
    }
  };
  const handleRestoreSubmit = async (e, filename) => {
    e.preventDefault();
    setIsRestoring(true);
    setStatusMessage(__("Restoring system from backup... Please wait..."));
    try {
      const response = await fetch(`/settings/backups/restore/${filename}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrf_token,
          "Accept": "application/json"
        }
      });
      const result = await response.json();
      if (response.ok && result.success) {
        let count = 5;
        setRestoreCountdown(count);
        setStatusMessage(__("System restored successfully."));
        const interval = setInterval(() => {
          count -= 1;
          setRestoreCountdown(count);
          if (count <= 0) {
            clearInterval(interval);
            window.location.href = "/";
          }
        }, 1e3);
      } else {
        setIsRestoring(false);
        alert(result.message || __("An error occurred while restoring the system."));
      }
    } catch (err) {
      setIsRestoring(false);
      alert(__("Connection error: ") + err.message);
    }
  };
  const handleUploadRestoreSubmit = async (e) => {
    e.preventDefault();
    if (!confirm(__("Are you sure you want to restore the system from this uploaded backup? Current data will be overwritten."))) {
      return;
    }
    const fileInput = e.target.elements.backup_file;
    if (!fileInput || !fileInput.files[0]) {
      alert(__("Please select a backup file."));
      return;
    }
    setIsRestoring(true);
    setStatusMessage(__("Uploading backup file..."));
    try {
      const formData = new FormData();
      formData.append("backup_file", fileInput.files[0]);
      const uploadResponse = await fetch("/settings/backups/upload", {
        method: "POST",
        headers: {
          "X-CSRF-TOKEN": csrf_token,
          "Accept": "application/json"
        },
        body: formData
      });
      const uploadResult = await uploadResponse.json();
      if (!uploadResponse.ok || !uploadResult.success) {
        setIsRestoring(false);
        alert(uploadResult.message || __("An error occurred while uploading the backup file."));
        return;
      }
      const filename = uploadResult.filename;
      setStatusMessage(__("Restoring system from backup... Please wait..."));
      const restoreResponse = await fetch(`/settings/backups/restore/${filename}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrf_token,
          "Accept": "application/json"
        }
      });
      const restoreResult = await restoreResponse.json();
      if (restoreResponse.ok && restoreResult.success) {
        let count = 5;
        setRestoreCountdown(count);
        setStatusMessage(__("System restored successfully."));
        const interval = setInterval(() => {
          count -= 1;
          setRestoreCountdown(count);
          if (count <= 0) {
            clearInterval(interval);
            window.location.href = "/";
          }
        }, 1e3);
      } else {
        setIsRestoring(false);
        alert(restoreResult.message || __("An error occurred while restoring the system."));
      }
    } catch (err) {
      setIsRestoring(false);
      alert(__("Connection error: ") + err.message);
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-3xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("System Settings") }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 mb-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/settings", className: "text-om-muted hover:text-om-ink transition-colors" }, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" }))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("System Settings")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-[12.5px] mt-0.5" }, __("Global application configuration")))), /* @__PURE__ */ React.createElement("div", { className: "mb-6 overflow-x-auto" }, /* @__PURE__ */ React.createElement(
    Tabs,
    {
      tabs: [
        { value: "general", label: __("General") },
        { value: "production", label: __("Production") },
        { value: "schedule", label: __("Schedule") },
        { value: "security", label: __("Security") },
        { value: "modules", label: __("Modules") },
        { value: "data", label: __("Data") }
      ],
      value: tab,
      onChange: setTab
    }
  )), /* @__PURE__ */ React.createElement("form", { onSubmit: handleSubmit, className: "space-y-6" }, tab === "modules" && /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Modules")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Enable only the feature areas your team uses. A disabled module is hidden from the menu and its pages return 404. Core areas (Dashboard, Orders, Production, Admin) are always on.")), /* @__PURE__ */ React.createElement("div", { className: "space-y-3" }, modules.map((m) => /* @__PURE__ */ React.createElement("div", { key: m.key, className: "flex items-start gap-3 border border-om-line rounded-om-sm p-3" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: data.enabled_modules.includes(m.key),
      onChange: (next) => toggleModule(m.key, next),
      label: __(m.label)
    }
  ), /* @__PURE__ */ React.createElement("span", { className: "text-[12.5px] text-om-muted" }, __(m.description)))))), tab === "general" && /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-4" }, __("Language")), /* @__PURE__ */ React.createElement("div", { className: "mb-2" }, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, __("Select language")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: Object.entries(availableLocales ?? { en: "English" }).map(([code, name]) => ({ value: String(code), label: name })),
      value: data.language == null ? "" : String(data.language),
      onChange: (v) => setData("language", v),
      className: "w-full max-w-xs"
    }
  ), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mt-2` }, __("Want to add a new language? Create a JSON file in"), " ", /* @__PURE__ */ React.createElement("code", { className: "bg-om-chip px-1 rounded font-mono text-[12px] text-om-ink" }, "lang/"), " ", __("directory."), " ", __("See"), " ", /* @__PURE__ */ React.createElement("code", { className: "bg-om-chip px-1 rounded font-mono text-[12px] text-om-ink" }, "lang/en.json"), " ", __("as reference."))), /* @__PURE__ */ React.createElement("div", { className: "border-t border-om-line pt-4 mt-2" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Currency")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("System-wide currency used across cost reports, pay rates and additional costs.")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [
        ...!CURRENCIES.some(([code]) => code === data.default_currency) && data.default_currency ? [{ value: String(data.default_currency), label: data.default_currency }] : [],
        ...CURRENCIES.map(([code, name]) => ({ value: String(code), label: `${code} - ${__(name)}` }))
      ],
      value: data.default_currency == null ? "" : String(data.default_currency),
      onChange: (v) => setData("default_currency", v),
      className: "w-64 max-w-full"
    }
  ), errors.default_currency && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.default_currency))), tab === "production" && /* @__PURE__ */ React.createElement("div", { className: "space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-4" }, __("Production Planning")), /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("span", { className: LABEL_CLASS }, __("Production Period Split")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("Determines how work orders are grouped for planning.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-3" }, [
    { value: "none", label: __("None"), desc: __("No period grouping") },
    { value: "weekly", label: __("Weekly"), desc: __("Group by ISO week (1-53)") },
    { value: "monthly", label: __("Monthly"), desc: __("Group by month (1-12)") }
  ].map((opt) => /* @__PURE__ */ React.createElement(
    SelectCard,
    {
      key: opt.value,
      value: opt.value,
      current: data.production_period,
      onChange: (v) => setData("production_period", v),
      label: opt.label,
      desc: opt.desc
    }
  ))), errors.production_period && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.production_period))), /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Workflow Mode")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Defines how work order completion is tracked.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-2 gap-3" }, [
    { value: "status", label: __("Status"), desc: __("Work order status is changed manually. Board statuses are visual labels.") },
    { value: "board_status", label: __("Board Status"), desc: __("Moving to a Done status automatically closes the work order.") }
  ].map((opt) => /* @__PURE__ */ React.createElement(
    SelectCard,
    {
      key: opt.value,
      value: opt.value,
      current: data.workflow_mode,
      onChange: (v) => setData("workflow_mode", v),
      label: opt.label,
      desc: opt.desc
    }
  ))), errors.workflow_mode && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.workflow_mode)), /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-4" }, __("Production Rules")), /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start gap-3" }, /* @__PURE__ */ React.createElement(
    Switch,
    {
      checked: data.allow_overproduction,
      onChange: (v) => setData("allow_overproduction", v)
    }
  ), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-[13px] font-medium text-om-ink" }, __("Allow overproduction")), /* @__PURE__ */ React.createElement("p", { className: HELP_CLASS }, __("Allow operators to record more units than the planned quantity.")))), /* @__PURE__ */ React.createElement("div", { className: "flex items-start gap-3" }, /* @__PURE__ */ React.createElement(
    Switch,
    {
      checked: data.force_sequential_steps,
      onChange: (v) => setData("force_sequential_steps", v)
    }
  ), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-[13px] font-medium text-om-ink" }, __("Force sequential steps")), /* @__PURE__ */ React.createElement("p", { className: HELP_CLASS }, __("Require production steps to be completed in defined order.")))), /* @__PURE__ */ React.createElement("div", { className: "flex items-start gap-3" }, /* @__PURE__ */ React.createElement(
    Switch,
    {
      checked: data.workstation_routing_enabled,
      onChange: (v) => setData("workstation_routing_enabled", v)
    }
  ), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-[13px] font-medium text-om-ink" }, __("Workstation routing")), /* @__PURE__ */ React.createElement("p", { className: HELP_CLASS }, __("When enabled, an operator assigned to a workstation can only start or complete steps assigned to that workstation.")))))), /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Barcode Scanner")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("How the workstation receives input from a barcode scanner.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-2 gap-3" }, [
    { value: "hid", label: __("HID / Keyboard wedge"), desc: __("Scanner acts as a keyboard. Codes are captured automatically on the workstation, no input field required.") },
    { value: "manual", label: __("Manual entry"), desc: __("Operator typed the code into a visible field and confirms with Enter. Use when no scanner is available.") }
  ].map((opt) => /* @__PURE__ */ React.createElement(
    SelectCard,
    {
      key: opt.value,
      value: opt.value,
      current: data.scanner_mode,
      onChange: (v) => setData("scanner_mode", v),
      label: opt.label,
      desc: opt.desc
    }
  ))), errors.scanner_mode && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.scanner_mode)), /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Production Tracking Mode")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("How operators register production progress on the shop floor.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-3 gap-3" }, [
    { value: "per_operation", label: __("Per Operation"), desc: __("Operator clicks Start/Complete on each step at each workstation. Full traceability.") },
    { value: "cumulative", label: __("Cumulative"), desc: __("Operator enters total produced quantity at the end. No step tracking.") },
    { value: "hybrid", label: __("Hybrid"), desc: __("Key steps tracked per-operation, quantity entry also available. Best of both.") }
  ].map((opt) => /* @__PURE__ */ React.createElement(
    SelectCard,
    {
      key: opt.value,
      value: opt.value,
      current: data.production_tracking_mode,
      onChange: (v) => setData("production_tracking_mode", v),
      label: opt.label,
      desc: opt.desc
    }
  ))), errors.production_tracking_mode && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.production_tracking_mode)), /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Production Quantity Corrections")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Defines whether and when operators can correct previously reported quantities.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-3 gap-3" }, [
    { value: "none", label: __("No corrections"), desc: __("Operators cannot edit reported quantities. All entries are final.") },
    { value: "timed", label: __("Timed window"), desc: __("Operators can correct quantities within a configurable time window after submission.") },
    { value: "full", label: __("Full edit"), desc: __("Operators can edit reported quantities at any time.") }
  ].map((opt) => /* @__PURE__ */ React.createElement(
    SelectCard,
    {
      key: opt.value,
      value: opt.value,
      current: data.production_qty_edit_policy,
      onChange: (v) => setData("production_qty_edit_policy", v),
      label: opt.label,
      desc: opt.desc
    }
  ))), errors.production_qty_edit_policy && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.production_qty_edit_policy), data.production_qty_edit_policy === "timed" && /* @__PURE__ */ React.createElement("div", { className: "mt-4" }, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS, htmlFor: "production_qty_edit_window_minutes" }, __("Correction time window")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("How many minutes after submission an operator can still correct the quantity.")), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      id: "production_qty_edit_window_minutes",
      value: data.production_qty_edit_window_minutes,
      onChange: (e) => setData("production_qty_edit_window_minutes", parseInt(e.target.value, 10) || 1),
      className: `${INPUT_BASE} w-24`,
      min: 1,
      max: 60
    }
  ), /* @__PURE__ */ React.createElement("span", { className: "text-[13px] text-om-muted" }, __("minutes"))), errors.production_qty_edit_window_minutes && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.production_qty_edit_window_minutes))), /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Labor costing")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Defaults used by the Production Cost report when a worker has no compensation of their own.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-3 gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS, htmlFor: "default_pay_type" }, __("Default pay type")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("Fallback mode for workers with no pay type set.")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [
        { value: "hourly", label: __("Hourly") },
        { value: "weekly", label: __("Weekly") },
        { value: "piece_rate", label: __("Piece rate") }
      ],
      value: data.default_pay_type == null ? "" : String(data.default_pay_type),
      onChange: (v) => setData("default_pay_type", v),
      className: "w-full"
    }
  ), errors.default_pay_type && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.default_pay_type)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS, htmlFor: "standard_weekly_hours" }, __("Standard weekly hours")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("Converts a weekly salary into an hourly cost (salary / hours).")), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      id: "standard_weekly_hours",
      value: data.standard_weekly_hours,
      onChange: (e) => setData("standard_weekly_hours", parseFloat(e.target.value) || 0),
      className: `${INPUT_BASE} w-28`,
      min: 1,
      max: 168,
      step: "0.5"
    }
  ), /* @__PURE__ */ React.createElement("span", { className: "text-[13px] text-om-muted" }, __("hours/week"))), errors.standard_weekly_hours && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.standard_weekly_hours)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS, htmlFor: "default_pay_rate" }, __("Default pay rate")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("Fallback rate used when a worker has no rate of their own (applied per the worker's pay type). Leave blank for none.")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      id: "default_pay_rate",
      value: data.default_pay_rate ?? "",
      onChange: (e) => setData("default_pay_rate", e.target.value === "" ? null : parseFloat(e.target.value)),
      className: `${INPUT_BASE} w-32`,
      min: 0,
      step: "0.0001"
    }
  ), errors.default_pay_rate && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.default_pay_rate))))), tab === "schedule" && /* @__PURE__ */ React.createElement("div", { className: `${CARD_CLASS} space-y-6` }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Schedule / Planner")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Configure how the production schedule planner displays data."))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, __("View mode")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("Default time scale for the schedule view.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-3" }, [
    { value: "weekly", label: __("Weekly"), desc: __("Plan by week") },
    { value: "daily", label: __("Daily"), desc: __("Plan by day") },
    { value: "monthly", label: __("Monthly"), desc: __("Plan by month") }
  ].map((opt) => /* @__PURE__ */ React.createElement(
    SelectCard,
    {
      key: opt.value,
      value: opt.value,
      current: data.schedule_view_mode,
      onChange: (v) => setData("schedule_view_mode", v),
      label: opt.label,
      desc: opt.desc
    }
  ))), errors.schedule_view_mode && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.schedule_view_mode)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, __("Shifts per day")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("Number of production shifts in a 24-hour period.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-4 gap-3" }, [1, 2, 3, 4].map((n) => /* @__PURE__ */ React.createElement(
    "div",
    {
      key: n,
      onClick: () => setData("schedule_shifts_per_day", n),
      className: `flex flex-col items-center gap-1 border rounded-om-sm p-3 cursor-pointer transition-colors
                                            ${data.schedule_shifts_per_day === n ? "border-om-accent bg-[rgba(234,90,43,.06)]" : "border-om-line hover:border-om-faint"}`
    },
    /* @__PURE__ */ React.createElement("span", { className: "font-medium text-[13px] text-om-ink" }, n),
    /* @__PURE__ */ React.createElement("span", { className: "text-[11.5px] text-om-muted" }, __(":hours h", { hours: Math.floor(24 / n) }))
  ))), errors.schedule_shifts_per_day && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.schedule_shifts_per_day), /* @__PURE__ */ React.createElement(Link, { href: "/admin/shifts", className: "inline-flex items-center gap-1.5 mt-3 text-[13px] text-om-accent hover:underline font-medium" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" })), __("Manage Shifts"), " \u2192")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS, htmlFor: "schedule_horizon_weeks" }, __("Planning horizon")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("How many weeks ahead the planner displays.")), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      id: "schedule_horizon_weeks",
      value: data.schedule_horizon_weeks,
      onChange: (e) => setData("schedule_horizon_weeks", parseInt(e.target.value, 10) || 1),
      className: `${INPUT_BASE} w-24`,
      min: 1,
      max: 52
    }
  ), /* @__PURE__ */ React.createElement("span", { className: "text-[13px] text-om-muted" }, __("weeks"))), errors.schedule_horizon_weeks && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.schedule_horizon_weeks)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-start gap-3" }, /* @__PURE__ */ React.createElement(
    Switch,
    {
      checked: data.schedule_show_weekends,
      onChange: (v) => setData("schedule_show_weekends", v)
    }
  ), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-[13px] font-medium text-om-ink" }, __("Show weekends")), /* @__PURE__ */ React.createElement("p", { className: HELP_CLASS }, __("Display Saturday and Sunday columns in the schedule view."))))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, __("Realtime updates")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-2` }, __("How the planner receives live updates from other users.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-3" }, /* @__PURE__ */ React.createElement(
    SelectCard,
    {
      value: "polling",
      current: data.realtime_mode,
      onChange: (v) => setData("realtime_mode", v),
      label: __("Polling"),
      desc: __("Checks for changes every few seconds (default)")
    }
  ), /* @__PURE__ */ React.createElement(
    SelectCard,
    {
      value: "off",
      current: data.realtime_mode,
      onChange: (v) => setData("realtime_mode", v),
      label: __("Off"),
      desc: __("No automatic refresh \u2014 reload the page to see changes")
    }
  )), errors.realtime_mode && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.realtime_mode))), tab === "security" && /* @__PURE__ */ React.createElement("div", { className: "space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Authentication")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Additional login methods for operators.")), /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start gap-3" }, /* @__PURE__ */ React.createElement(
    Switch,
    {
      checked: data.pin_login_enabled,
      onChange: (v) => setData("pin_login_enabled", v)
    }
  ), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-[13px] font-medium text-om-ink" }, __("Enable PIN login")), /* @__PURE__ */ React.createElement("p", { className: HELP_CLASS }, __("Allow users to set a 4\u20136 digit numeric PIN for quick sign-in. Each user must first configure their PIN in Settings (requires current password). PIN login does not replace password login \u2014 it is an alternative method.")))))), /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("CORS (Cross-Origin Requests)")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Control which external domains can make API requests to this application. Leave empty to block all cross-origin requests (most secure).")), /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS, htmlFor: "cors_allowed_origins" }, __("Allowed Origins")), /* @__PURE__ */ React.createElement(
    "textarea",
    {
      id: "cors_allowed_origins",
      rows: 3,
      value: data.cors_allowed_origins,
      onChange: (e) => setData("cors_allowed_origins", e.target.value),
      className: `${INPUT_BASE} w-full`,
      placeholder: __("https://erp.yourcompany.com")
    }
  ), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mt-1` }, __("Comma-separated list of allowed origins. Only HTTPS URLs recommended. Leave empty to block all cross-origin requests.")), errors.cors_allowed_origins && /* @__PURE__ */ React.createElement("p", { className: ERROR_CLASS }, errors.cors_allowed_origins)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS, htmlFor: "cors_allowed_methods" }, __("Allowed Methods")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      id: "cors_allowed_methods",
      value: data.cors_allowed_methods,
      onChange: (e) => setData("cors_allowed_methods", e.target.value),
      className: `${INPUT_BASE} w-full`,
      placeholder: "GET, POST"
    }
  ), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mt-1` }, __("HTTP methods allowed for cross-origin requests. Default: GET, POST (minimal)."))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS, htmlFor: "cors_max_age" }, __("Preflight Cache (seconds)")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      id: "cors_max_age",
      value: data.cors_max_age,
      onChange: (e) => setData("cors_max_age", parseInt(e.target.value, 10) || 0),
      className: `${INPUT_BASE} w-32`,
      min: 0,
      max: 86400,
      placeholder: "0"
    }
  ), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mt-1` }, __("How long browsers cache preflight responses. 0 = no caching (strictest).")))))), tab !== "data" && /* @__PURE__ */ React.createElement("div", { className: "flex justify-end" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", loading: processing }, __("Save")))), tab === "data" && /* @__PURE__ */ React.createElement("div", { className: "space-y-8" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-downtime-bg border border-om-line rounded-om p-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Sample Data")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Load a pre-built demo dataset: lines, workstations, products, templates and work orders. Safe to run multiple times.")), /* @__PURE__ */ React.createElement("form", { method: "POST", action: "/settings/sample-data" }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrf_token }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-4" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: sampleConfirm,
      onChange: setSampleConfirm,
      label: __("I understand this will add demo data to the system")
    }
  ), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "secondary", disabled: !sampleConfirm }, __("Load Sample Data"))))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Export Settings")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Download complete system configuration as a JSON file. Includes lines, workstations, product types, templates, materials, shifts, and all settings. No production data or user accounts are exported.")), /* @__PURE__ */ React.createElement(
    "a",
    {
      href: "/settings/export",
      className: "inline-flex items-center gap-2 rounded-om-sm border border-om-line bg-om-card px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" })),
    __("Export Settings (JSON)")
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1" }, __("Import Settings")), /* @__PURE__ */ React.createElement("p", { className: `${HELP_CLASS} mb-4` }, __("Upload a previously exported configuration file. This will overwrite current configuration including lines, products, templates, materials, and settings. Production data (work orders, batches, issues) is never affected. Database credentials are never imported.")), /* @__PURE__ */ React.createElement("form", { method: "POST", action: "/settings/import", encType: "multipart/form-data", className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrf_token }), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "file",
      name: "settings_file",
      accept: ".json,.txt",
      required: true,
      className: "text-[13px] text-om-muted file:mr-3 file:py-2 file:px-4 file:rounded-om-sm file:border-0 file:text-[13px] file:font-semibold file:bg-om-chip file:text-om-ink hover:file:bg-om-line2 file:transition-colors file:cursor-pointer"
    }
  ), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", className: "inline-flex items-center gap-2" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" })), __("Import Settings")))), /* @__PURE__ */ React.createElement("div", { className: "border-t border-gray-200 dark:border-gray-700 pt-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-gray-800 dark:text-gray-100 mb-1" }, __("Backup & Recovery")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-gray-500 dark:text-gray-400 mb-4" }, __("Create and manage backups of the database and uploaded files. Full backups include all uploaded attachments, while data-only backups only contain database records.")), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-3 mb-6" }, /* @__PURE__ */ React.createElement("form", { method: "POST", action: "/settings/backups/full" }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrf_token }), /* @__PURE__ */ React.createElement("button", { type: "submit", className: "btn-touch bg-blue-600 text-white hover:bg-blue-700 inline-flex items-center gap-2" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" })), __("Create Full Backup"))), /* @__PURE__ */ React.createElement("form", { method: "POST", action: "/settings/backups/data" }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrf_token }), /* @__PURE__ */ React.createElement("button", { type: "submit", className: "btn-touch bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 inline-flex items-center gap-2" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" })), __("Create Data Backup")))), /* @__PURE__ */ React.createElement("div", { className: "bg-gray-50 dark:bg-gray-800/40 p-4 rounded-lg border border-gray-200 dark:border-gray-700 mb-6" }, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2" }, __("Upload Backup File")), /* @__PURE__ */ React.createElement("form", { onSubmit: handleUploadRestoreSubmit, className: "flex flex-wrap items-center gap-3" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "file",
      name: "backup_file",
      accept: ".zip",
      required: true,
      className: "text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200 dark:hover:file:bg-gray-600"
    }
  ), /* @__PURE__ */ React.createElement("button", { type: "submit", className: "btn-touch bg-amber-600 text-white hover:bg-amber-700 px-4 py-2 text-sm font-medium" }, __("Restore")))), /* @__PURE__ */ React.createElement("div", { className: "overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg" }, /* @__PURE__ */ React.createElement("table", { className: "min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm" }, /* @__PURE__ */ React.createElement("thead", { className: "bg-gray-50 dark:bg-gray-800" }, /* @__PURE__ */ React.createElement("tr", null, /* @__PURE__ */ React.createElement("th", { className: "px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300" }, __("Filename")), /* @__PURE__ */ React.createElement("th", { className: "px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300" }, __("Size")), /* @__PURE__ */ React.createElement("th", { className: "px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300" }, __("Created At")), /* @__PURE__ */ React.createElement("th", { className: "px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300" }, __("Actions")))), /* @__PURE__ */ React.createElement("tbody", { className: "divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900/20" }, !backups || backups.length === 0 ? /* @__PURE__ */ React.createElement("tr", null, /* @__PURE__ */ React.createElement("td", { colSpan: "4", className: "px-4 py-8 text-center text-gray-500 dark:text-gray-400" }, __("No backups found."))) : backups.map((backup) => /* @__PURE__ */ React.createElement("tr", { key: backup.filename, className: "hover:bg-gray-50/50 dark:hover:bg-gray-800/10" }, /* @__PURE__ */ React.createElement("td", { className: "px-4 py-3 font-medium text-gray-900 dark:text-gray-100" }, backup.filename), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-3 text-gray-600 dark:text-gray-300" }, (backup.size_bytes / (1024 * 1024)).toFixed(2), " MB"), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-3 text-gray-600 dark:text-gray-300" }, new Date(backup.created_at).toLocaleString()), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-3 text-right" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-end gap-3" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/settings/backups/download/${backup.filename}`,
      className: "text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium",
      title: __("Download")
    },
    __("Download")
  ), /* @__PURE__ */ React.createElement("form", { onSubmit: (e) => {
    e.preventDefault();
    if (confirm(__("Are you sure you want to restore the system from this backup? Current data will be overwritten."))) {
      handleRestoreSubmit(e, backup.filename);
    }
  } }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "submit",
      className: "text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 font-medium"
    },
    __("Restore")
  )), /* @__PURE__ */ React.createElement("form", { method: "POST", action: `/settings/backups/${backup.filename}` }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrf_token }), /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_method", value: "DELETE" }), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "submit",
      onClick: (e) => {
        if (!confirm(__("Are you sure you want to delete this backup?"))) {
          e.preventDefault();
        }
      },
      className: "text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium"
    },
    __("Delete")
  )))))))))), /* @__PURE__ */ React.createElement("div", { className: "border-t border-gray-200 dark:border-gray-700 pt-6" }, /* @__PURE__ */ React.createElement("div", { className: "card border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/20" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-red-800 dark:text-red-400 mb-1" }, __("Reset System")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-red-700 dark:text-red-300 mb-4" }, __("Wipe all database records (production data, settings, configurations) and delete all uploaded attachments. The system will be restored to its initial state, and you will be logged out.")), /* @__PURE__ */ React.createElement("form", { onSubmit: handleResetSubmit, className: "space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-2" }, /* @__PURE__ */ React.createElement("label", { className: "flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200 cursor-pointer" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "checkbox",
      checked: resetConfirm,
      onChange: (e) => setResetConfirm(e.target.checked),
      className: "rounded border-gray-300 dark:border-gray-600 text-red-600 focus:ring-red-500"
    }
  ), __("I understand that this action is irreversible and all data will be permanently lost."))), resetConfirm && /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      name: "confirm_text",
      value: resetText,
      onChange: (e) => setResetText(e.target.value),
      placeholder: __("Type RESET to confirm"),
      className: "px-3 py-2 border border-red-300 dark:border-red-700 bg-white dark:bg-gray-800 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 text-gray-900 dark:text-gray-100",
      required: true
    }
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "submit",
      disabled: resetText !== "RESET",
      className: "btn-touch px-4 py-2 text-sm font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
    },
    __("Reset System")
  )))))), (isResetting || isRestoring) && /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4" }, /* @__PURE__ */ React.createElement("div", { className: "bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-8 max-w-md w-full text-center space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-center" }, resetCountdown !== null || restoreCountdown !== null ? /* @__PURE__ */ React.createElement("div", { className: "w-20 h-20 rounded-full border-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-3xl font-bold text-blue-600 dark:text-blue-400 animate-pulse" }, resetCountdown !== null ? resetCountdown : restoreCountdown) : /* @__PURE__ */ React.createElement("div", { className: "w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin" })), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h3", { className: "text-xl font-bold text-gray-900 dark:text-gray-100" }, resetCountdown !== null || restoreCountdown !== null ? __("Redirecting...") : isResetting ? __("Resetting the system") : __("Restoring data")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-gray-500 dark:text-gray-400 mt-2" }, statusMessage), (resetCountdown !== null || restoreCountdown !== null) && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-blue-500 mt-2" }, __("Redirecting to the login page in"), " ", resetCountdown !== null ? resetCountdown : restoreCountdown, " ", __("seconds..."))))));
}
System.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  System as default
};
