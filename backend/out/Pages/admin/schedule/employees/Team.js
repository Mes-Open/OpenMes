import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../../layouts/AppLayout";
import { Tacho, EmployeeTabs } from "./Day";
import { __ } from "../../../../lib/i18n";
function fmtMins(m) {
  return `${String(Math.floor(m / 60)).padStart(2, "0")}:${String(m % 60).padStart(2, "0")}`;
}
const ON_DUTY_TYPES = ["work", "setup", "qc", "maint", "training", "meeting"];
function EmployeeTeam() {
  const {
    view,
    date,
    workers = [],
    selectedWorker,
    selectedWorkerId,
    teamActivities = {},
    customTypes = [],
    typeMeta = {}
  } = usePage().props;
  const isToday = date === (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
  const nowMin = isToday ? (/* @__PURE__ */ new Date()).getHours() * 60 + (/* @__PURE__ */ new Date()).getMinutes() : null;
  const onDutyByWorker = {};
  Object.entries(teamActivities).forEach(([wId, acts]) => {
    onDutyByWorker[wId] = acts.filter((a) => ON_DUTY_TYPES.includes(a.type)).reduce((s, a) => s + (a.duration ?? 0), 0);
  });
  const navTo = (params) => router.get("/admin/schedule/employees", params, { preserveState: false });
  const hourLabels = [0, 3, 6, 9, 12, 15, 18, 21];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Team Day") }), /* @__PURE__ */ React.createElement(EmployeeTabs, { view, date, selectedWorkerId, selectedWorker, workers }), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line2 rounded-om p-3.5" }, /* @__PURE__ */ React.createElement("div", { className: "grid gap-3.5 items-center", style: { gridTemplateColumns: "160px 1fr 80px" } }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] tracking-wider text-om-muted uppercase" }, __("Worker")), /* @__PURE__ */ React.createElement("div", { className: "relative h-4" }, hourLabels.map((h) => /* @__PURE__ */ React.createElement(
    "div",
    {
      key: h,
      className: "absolute font-mono text-[9px] font-bold tracking-wider text-om-muted",
      style: { left: `${h / 24 * 100}%` }
    },
    String(h).padStart(2, "0"),
    ":00"
  ))), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] tracking-wider text-om-muted uppercase text-right" }, __("On duty")))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-2" }, workers.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "p-8 text-center text-sm text-om-faint border border-dashed border-om-line2 rounded-om" }, __("No workers configured.")) : workers.map((w) => {
    const acts = teamActivities[w.id] ?? [];
    const onDuty = onDutyByWorker[w.id] ?? 0;
    const primary = w.id === selectedWorkerId;
    const parts = (w.name ?? "").trim().split(" ");
    const initials = ((parts[0]?.[0] ?? "") + (parts[parts.length - 1]?.[0] ?? "")).toUpperCase();
    return /* @__PURE__ */ React.createElement(
      "button",
      {
        key: w.id,
        onClick: () => navTo({ view: "day", date, worker_id: w.id }),
        className: `grid gap-3.5 items-center p-3 rounded-om border transition-colors text-left ${primary ? "bg-om-downtime-bg border-amber-400" : "bg-om-card border-om-line2 hover:bg-om-bg"}`,
        style: { gridTemplateColumns: "160px 1fr 80px" }
      },
      /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2.5 min-w-0" }, /* @__PURE__ */ React.createElement("div", { className: `w-9 h-9 rounded-om-sm font-mono text-[11px] font-bold flex items-center justify-center flex-shrink-0 ${primary ? "bg-om-downtime text-white" : "bg-om-line2 text-om-muted"}` }, initials), /* @__PURE__ */ React.createElement("div", { className: "min-w-0" }, /* @__PURE__ */ React.createElement("div", { className: "text-sm font-bold text-om-ink truncate" }, w.name), /* @__PURE__ */ React.createElement("div", { className: `font-mono text-[9px] mt-0.5 truncate ${primary ? "text-om-downtime" : "text-om-muted"}` }, w.code, w.personnel_class_code ? ` \xB7 ${w.personnel_class_code}` : ""))),
      /* @__PURE__ */ React.createElement("div", { className: "min-w-0" }, /* @__PURE__ */ React.createElement(Tacho, { activities: acts, typeMeta, height: 42, showHours: false, isToday, nowMinutes: nowMin })),
      /* @__PURE__ */ React.createElement("div", { className: "text-right" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-base font-bold text-emerald-600 -tracking-wide" }, fmtMins(onDuty)), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[8.5px] tracking-wider text-om-muted uppercase mt-0.5" }, __("On duty")))
    );
  })), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-3 px-3.5 py-2.5 bg-om-card border border-om-line2 rounded-om font-mono text-[9.5px] tracking-wide text-om-muted uppercase" }, Object.entries(typeMeta).filter(([k]) => !["off", "custom"].includes(k)).map(([k, def]) => /* @__PURE__ */ React.createElement("span", { key: k, className: "flex items-center gap-1.5" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-2 rounded-sm", style: { background: def.color } }), def.label)))));
}
EmployeeTeam.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  EmployeeTeam as default
};
