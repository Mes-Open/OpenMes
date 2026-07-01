import { Head, router, usePage } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
import AppLayout from "../../../../layouts/AppLayout";
import { Tacho, EmployeeTabs } from "./Day";
import { __, formatDate } from "../../../../lib/i18n";
function toMin(t) {
  if (!t) return 0;
  const [h, m] = (t || "00:00").split(":").map(Number);
  return h * 60 + m;
}
function fmtMins(m) {
  return `${String(Math.floor(m / 60)).padStart(2, "0")}:${String(m % 60).padStart(2, "0")}`;
}
function buildStrip(dayStr, monthByWorker) {
  const rows = monthByWorker[dayStr] ?? [];
  const segments = [];
  let cursor = 0;
  const endOfDay = 24 * 60;
  rows.forEach((r) => {
    const startMin = toMin(r.starts_at_time);
    const endMin = toMin(r.ends_at_time);
    if (startMin > cursor) {
      segments.push({ type: "off", from: fmtMins(cursor), to: fmtMins(startMin) });
    }
    segments.push({ type: r.type, from: r.starts_at_time, to: r.ends_at_time });
    cursor = endMin;
  });
  if (cursor < endOfDay) {
    segments.push({ type: "off", from: fmtMins(cursor), to: "24:00" });
  }
  return segments;
}
function EmployeeMonth() {
  const {
    view,
    date,
    workers = [],
    selectedWorker,
    selectedWorkerId,
    monthStart,
    monthEnd,
    monthByWorker = {},
    selectedDayActivities = [],
    typeMeta = {}
  } = usePage().props;
  const navTo = (params) => router.get("/admin/schedule/employees", params, { preserveState: false });
  const cells = [];
  if (monthStart && monthEnd) {
    const cur = new Date(monthStart);
    const end = new Date(monthEnd);
    while (cur <= end) {
      cells.push(cur.toISOString().slice(0, 10));
      cur.setDate(cur.getDate() + 1);
    }
  }
  const isToday = date === (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
  const selSums = {};
  selectedDayActivities.forEach((a) => {
    selSums[a.type] = (selSums[a.type] ?? 0) + (a.duration ?? 0);
  });
  const selTotalWork = (selSums.work ?? 0) + (selSums.setup ?? 0) + (selSums.qc ?? 0);
  const selOnDuty = selTotalWork + (selSums.maint ?? 0) + (selSums.meeting ?? 0) + (selSums.training ?? 0);
  const selBreaks = (selSums.break ?? 0) + (selSums.rest ?? 0);
  const nowMin = isToday ? (/* @__PURE__ */ new Date()).getHours() * 60 + (/* @__PURE__ */ new Date()).getMinutes() : null;
  const dateMonth = date ? new Date(date).getMonth() : -1;
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Employee Month Overview") }), /* @__PURE__ */ React.createElement(EmployeeTabs, { view, date, selectedWorkerId, selectedWorker, workers }), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap items-center gap-2 mb-3" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10.5px] tracking-wider text-om-muted uppercase" }, __("Worker:")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: workers.map((w) => ({ value: String(w.id), label: `${w.name} (${w.code})` })),
      value: selectedWorkerId == null ? "" : String(selectedWorkerId),
      onChange: (v) => navTo({ view: "month", date, worker_id: v }),
      className: "min-w-[180px]"
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1 ml-auto" }, /* @__PURE__ */ React.createElement("button", { onClick: () => {
    const d = new Date(date);
    d.setMonth(d.getMonth() - 1);
    navTo({ view: "month", date: d.toISOString().slice(0, 10), worker_id: selectedWorkerId });
  }, className: "w-9 h-9 flex items-center justify-center rounded-om-sm bg-om-card border border-om-line2 hover:bg-om-bg" }, "\u2190"), /* @__PURE__ */ React.createElement("button", { onClick: () => {
    const d = new Date(date);
    d.setMonth(d.getMonth() + 1);
    navTo({ view: "month", date: d.toISOString().slice(0, 10), worker_id: selectedWorkerId });
  }, className: "w-9 h-9 flex items-center justify-center rounded-om-sm bg-om-card border border-om-line2 hover:bg-om-bg" }, "\u2192"))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-4" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line2 rounded-2xl p-3 md:p-4" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-7 gap-1.5 mb-2" }, ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].map((wd) => /* @__PURE__ */ React.createElement("div", { key: wd, className: "font-mono text-[10px] tracking-wider text-om-muted uppercase text-center" }, __(wd)))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-7 gap-1.5" }, cells.map((cellDate) => {
    const cellObj = new Date(cellDate);
    const inMonth = cellObj.getMonth() === dateMonth;
    const isSelected = cellDate === date;
    const isTodayCell = cellDate === (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
    const strip = buildStrip(cellDate, monthByWorker);
    const hasActivity = strip.some((s) => s.type !== "off");
    const nowFrac = isTodayCell ? ((/* @__PURE__ */ new Date()).getHours() * 60 + (/* @__PURE__ */ new Date()).getMinutes()) / 1440 * 100 : null;
    return /* @__PURE__ */ React.createElement(
      "button",
      {
        key: cellDate,
        onClick: () => navTo({ view: "month", date: cellDate, worker_id: selectedWorkerId }),
        className: `min-h-[88px] flex flex-col p-1.5 rounded-om-sm border transition-colors text-left ${isSelected ? "border-amber-500 ring-2 ring-amber-300" : "border-om-line2 hover:border-om-line"} ${!inMonth ? "opacity-40" : ""} ${isTodayCell && !isSelected ? "bg-om-downtime-bg/50" : "bg-om-card"}`
      },
      /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between" }, /* @__PURE__ */ React.createElement("span", { className: `font-mono text-xs font-bold ${isSelected ? "text-om-downtime" : "text-om-ink"}` }, cellObj.getDate()), hasActivity && /* @__PURE__ */ React.createElement("span", { className: "w-1.5 h-1.5 rounded-full bg-emerald-500" })),
      /* @__PURE__ */ React.createElement("div", { className: "flex-1" }),
      strip.length > 0 && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "relative h-3 rounded-sm overflow-hidden bg-om-chip" }, strip.map((seg, si) => {
        const left = toMin(seg.from) / 1440 * 100;
        const endMin = seg.to === "24:00" ? 1440 : toMin(seg.to);
        const width = seg.to === "24:00" ? 100 - left : (endMin - toMin(seg.from)) / 1440 * 100;
        const color = typeMeta[seg.type]?.color ?? "#94a3b8";
        return /* @__PURE__ */ React.createElement("div", { key: si, className: "absolute top-0 bottom-0", style: { left: `${left}%`, width: `${width}%`, background: color } });
      }), nowFrac !== null && /* @__PURE__ */ React.createElement("div", { className: "absolute top-0 bottom-0 w-px bg-om-card", style: { left: `${nowFrac}%` } })), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between mt-0.5 font-mono text-[7.5px] tracking-wider text-om-faint" }, /* @__PURE__ */ React.createElement("span", null, "00"), /* @__PURE__ */ React.createElement("span", null, "12"), /* @__PURE__ */ React.createElement("span", null, "24")))
    );
  })), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-3 mt-3 px-1 font-mono text-[9.5px] tracking-wider text-om-muted uppercase" }, ["work", "break", "rest", "maint", "meeting", "off"].map((k) => {
    const def = typeMeta[k];
    if (!def) return null;
    return /* @__PURE__ */ React.createElement("span", { key: k, className: "flex items-center gap-1.5" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-2 rounded-sm", style: { background: def.color } }), def.label);
  }))), /* @__PURE__ */ React.createElement("aside", { className: "bg-om-card border border-om-line2 rounded-2xl p-4 flex flex-col gap-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10.5px] tracking-wider font-bold uppercase text-om-downtime" }, __("Selected"), " \xB7 ", date ? formatDate(new Date(date), { weekday: "short", day: "numeric", month: "short" }) : ""), /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink mt-1" }, __(":count activities", { count: selectedDayActivities.length })), selectedWorker && /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10.5px] text-om-muted mt-1" }, selectedWorker.name, " \xB7 ", selectedWorker.code)), /* @__PURE__ */ React.createElement("div", { className: "rounded-om bg-om-panel p-3" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] tracking-wider text-om-muted uppercase mb-2" }, __("24h activity")), /* @__PURE__ */ React.createElement(Tacho, { activities: selectedDayActivities, typeMeta, height: 40, isToday, nowMinutes: nowMin })), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "p-2.5 rounded-om-sm bg-om-panel" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase" }, __("On duty")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-lg font-bold mt-1 text-emerald-600" }, fmtMins(selOnDuty))), /* @__PURE__ */ React.createElement("div", { className: "p-2.5 rounded-om-sm bg-om-panel" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase" }, __("Productive")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-lg font-bold mt-1 text-om-ink" }, fmtMins(selTotalWork))), /* @__PURE__ */ React.createElement("div", { className: "p-2.5 rounded-om-sm bg-om-panel" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase" }, __("Breaks")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-lg font-bold mt-1 text-om-downtime" }, fmtMins(selBreaks))), /* @__PURE__ */ React.createElement("div", { className: "p-2.5 rounded-om-sm bg-om-panel" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase" }, __("Maint")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-lg font-bold mt-1 text-rose-600" }, fmtMins(selSums.maint ?? 0)))), /* @__PURE__ */ React.createElement("div", { className: "flex-1" }), /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => navTo({ view: "day", date, worker_id: selectedWorkerId }),
      className: "h-11 rounded-om bg-om-downtime hover:brightness-95 text-white font-mono text-xs font-bold tracking-wider uppercase flex items-center justify-center"
    },
    __("Open day plan")
  ))));
}
EmployeeMonth.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  EmployeeMonth as default
};
