import { useState, useEffect, useRef } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import { __, formatNumber } from "../../../lib/i18n";
const BORDER = {
  green: "border-om-running",
  amber: "border-amber-400",
  blue: "border-om-accent",
  gray: "border-om-faintest",
  red: "border-om-blocked",
  yellow: "border-yellow-400",
  purple: "border-purple-400",
  orange: "border-orange-400",
  slate: "border-slate-300"
};
const BADGE = {
  green: "bg-om-running-bg text-om-running",
  amber: "bg-om-downtime-bg text-om-downtime",
  blue: "bg-om-chip text-om-accent",
  gray: "bg-om-chip text-om-muted",
  red: "bg-om-blocked-bg text-om-blocked",
  yellow: "bg-yellow-100 text-yellow-700",
  purple: "bg-purple-100 text-purple-700",
  orange: "bg-orange-100 text-orange-700",
  slate: "bg-slate-100 text-slate-600"
};
const STATE_LABELS = {
  RUNNING: "Running",
  IDLE: "Idle",
  STOPPED: "Stopped",
  FAULT: "Fault",
  SETUP: "Setup",
  WAITING: "Waiting",
  CLEANING: "Cleaning",
  MAINTENANCE: "Maintenance"
};
function csrf() {
  const m = typeof document !== "undefined" ? document.querySelector('meta[name="csrf-token"]') : null;
  return m ? m.content : "";
}
const POLL_MS = 3e3;
function timeInState(sinceIso, now) {
  if (!sinceIso) return null;
  const start = new Date(sinceIso).getTime();
  if (Number.isNaN(start)) return null;
  const sec = Math.max(0, Math.floor((now - start) / 1e3));
  if (sec < 60) return `${sec}s`;
  if (sec < 3600) return `${Math.floor(sec / 60)}m`;
  return `${Math.floor(sec / 3600)}h ${Math.floor(sec % 3600 / 60)}m`;
}
function MachineMonitorIndex() {
  const { tiles: initialTiles = [], checkUrl, states = [] } = usePage().props;
  const [tiles, setTiles] = useState(initialTiles);
  const [live, setLive] = useState(true);
  const [now, setNow] = useState(() => Date.now());
  const liveRef = useRef(live);
  liveRef.current = live;
  const setState = async (workstationId, state) => {
    try {
      const res = await fetch(`/admin/machine-monitor/${workstationId}/state`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrf(),
          "X-Requested-With": "XMLHttpRequest"
        },
        credentials: "same-origin",
        body: JSON.stringify({ state })
      });
      if (res.ok) {
        const json = await res.json();
        if (Array.isArray(json.data)) setTiles(json.data);
      }
    } catch (_) {
    }
  };
  useEffect(() => {
    if (!checkUrl) return void 0;
    const interval = setInterval(async () => {
      if (!liveRef.current) return;
      try {
        const res = await fetch(checkUrl, {
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
        });
        if (!res.ok) return;
        const json = await res.json();
        if (Array.isArray(json.data)) setTiles(json.data);
      } catch (_) {
      }
    }, POLL_MS);
    return () => clearInterval(interval);
  }, [checkUrl]);
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1e3);
    return () => clearInterval(t);
  }, []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Machine Monitor") }), /* @__PURE__ */ React.createElement("div", { className: "p-6 space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-2xl font-bold text-om-ink" }, __("Machine Monitor")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Live workstation states from connected machines."))), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setLive((l) => !l),
      className: "flex items-center gap-2 text-sm text-om-muted hover:text-om-ink",
      title: live ? __("Pause live updates") : __("Resume live updates")
    },
    /* @__PURE__ */ React.createElement("span", { className: `w-2 h-2 rounded-full ${live ? "bg-om-running animate-pulse" : "bg-slate-400"}` }),
    live ? __("Live") : __("Paused")
  )), tiles.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "text-center py-16 text-om-faint" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm" }, __("No workstations are wired to a machine connection yet.")), /* @__PURE__ */ React.createElement(Link, { href: "/admin/connectivity/modbus", className: "mt-2 inline-block text-om-accent hover:underline text-sm" }, __("Configure a Modbus connection \u2192"))) : /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" }, tiles.map((t) => /* @__PURE__ */ React.createElement(Tile, { key: t.id, t, now, states, onSetState: setState })))));
}
MachineMonitorIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function Tile({ t, now, states = [], onSetState }) {
  const border = BORDER[t.color] ?? BORDER.slate;
  const badge = BADGE[t.color] ?? BADGE.slate;
  const elapsed = timeInState(t.since, now);
  const metadata = t.metadata && typeof t.metadata === "object" ? Object.entries(t.metadata) : [];
  return /* @__PURE__ */ React.createElement("div", { className: `bg-om-card rounded-om border border-om-line2 border-l-4 ${border} shadow-sm p-5` }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "min-w-0" }, /* @__PURE__ */ React.createElement("h3", { className: "font-bold text-om-ink truncate" }, t.name), t.line && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, t.line)), /* @__PURE__ */ React.createElement("span", { className: `shrink-0 px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wide ${badge}` }, t.state)), elapsed && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-faint" }, __("in state for"), " ", elapsed), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-2 text-center mt-4" }, /* @__PURE__ */ React.createElement(Metric, { label: __("Availability"), value: t.availability != null ? `${t.availability}%` : "\u2014" }), /* @__PURE__ */ React.createElement(Metric, { label: __("Good"), value: formatNumber(Number(t.good ?? 0)), tone: "text-om-running" }), /* @__PURE__ */ React.createElement(Metric, { label: __("Reject"), value: formatNumber(Number(t.reject ?? 0)), tone: "text-om-blocked" })), t.quality != null && /* @__PURE__ */ React.createElement("p", { className: "mt-3 text-xs text-om-faint text-center" }, __("Quality"), " ", t.quality, "%"), metadata.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "mt-3 pt-2 border-t border-om-line2 flex flex-wrap gap-1.5" }, metadata.map(([k, v]) => /* @__PURE__ */ React.createElement("span", { key: k, className: "text-[11px] bg-om-panel border border-om-line2 rounded px-1.5 py-0.5 text-om-muted" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, k, ":"), " ", String(v)))), states.length > 0 && onSetState && /* @__PURE__ */ React.createElement("div", { className: "mt-3 pt-2 border-t border-om-line2 flex items-center gap-2" }, /* @__PURE__ */ React.createElement("label", { className: "text-[10px] uppercase text-om-faint shrink-0" }, __("Set state")), /* @__PURE__ */ React.createElement(
    "select",
    {
      value: t.state,
      onChange: (e) => onSetState(t.id, e.target.value),
      className: "form-input text-xs py-1 flex-1",
      "aria-label": __("Set state")
    },
    !states.includes(t.state) && /* @__PURE__ */ React.createElement("option", { value: t.state }, t.state),
    states.map((s) => /* @__PURE__ */ React.createElement("option", { key: s, value: s }, __(STATE_LABELS[s] ?? s)))
  )));
}
function Metric({ label, value, tone = "text-om-ink" }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-[10px] uppercase text-om-faint" }, label), /* @__PURE__ */ React.createElement("p", { className: `text-lg font-bold ${tone}` }, value));
}
export {
  MachineMonitorIndex as default
};
