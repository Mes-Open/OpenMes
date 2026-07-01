import { Head, Link, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../../layouts/AppLayout";
import { __, formatDate } from "../../../../lib/i18n";
function toMin(t) {
  if (!t) return 0;
  const [h, m] = t.split(":").map(Number);
  return h * 60 + m;
}
function fmtMins(m) {
  return `${String(Math.floor(m / 60)).padStart(2, "0")}:${String(m % 60).padStart(2, "0")}`;
}
function Tacho({ activities, typeMeta, height = 56, showHours = true, isToday = false, nowMinutes = null, highlightId = null }) {
  const totalMin = 24 * 60;
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "relative overflow-hidden rounded-om-sm border border-om-line2 bg-om-panel",
      style: { height: `${height}px` }
    },
    Array.from({ length: 24 }, (_, h) => /* @__PURE__ */ React.createElement(
      "div",
      {
        key: h,
        className: `absolute top-0 bottom-0 ${h % 6 === 0 ? "bg-om-line" : "bg-om-line2/50"}`,
        style: { left: `${h / 24 * 100}%`, width: "1px" }
      }
    )),
    activities.map((a, i) => {
      const left = toMin(a.from) / totalMin * 100;
      const endMin = a.to === "24:00" ? totalMin : toMin(a.to);
      const width = a.to === "24:00" ? 100 - left : (endMin - toMin(a.from)) / totalMin * 100;
      const color = typeMeta[a.type]?.color ?? "#94a3b8";
      const hl = highlightId !== null && a.id === highlightId;
      return /* @__PURE__ */ React.createElement(
        "div",
        {
          key: i,
          className: "absolute",
          title: `${a.label ?? typeMeta[a.type]?.label ?? a.type} \xB7 ${a.from} \u2192 ${a.to}`,
          style: {
            left: `${left}%`,
            width: `${width}%`,
            top: hl ? "0" : "2px",
            bottom: hl ? "0" : "2px",
            background: color,
            ...hl ? { border: "2px solid #fff", boxShadow: "0 0 0 2px rgba(245,165,36,0.6)" } : {}
          }
        }
      );
    }),
    isToday && nowMinutes !== null && /* @__PURE__ */ React.createElement(
      "div",
      {
        className: "absolute -top-1 -bottom-1 w-0.5 bg-om-downtime z-10",
        style: { left: `${nowMinutes / totalMin * 100}%` }
      },
      /* @__PURE__ */ React.createElement("div", { className: "absolute -top-1 -left-1 w-2.5 h-2.5 rounded-full bg-om-card border-2 border-om-downtime" })
    )
  ), showHours && /* @__PURE__ */ React.createElement("div", { className: "flex justify-between mt-1 font-mono text-[9px] text-om-muted tracking-wider" }, [0, 3, 6, 9, 12, 15, 18, 21, 24].map((h) => /* @__PURE__ */ React.createElement("span", { key: h }, String(h).padStart(2, "0")))));
}
function EmployeeTabs({ view, date, selectedWorkerId, selectedWorker, workers }) {
  const tabs = [
    { key: "day", label: __("Day plan") },
    { key: "team", label: __("Team day") },
    { key: "month", label: __("Month") }
  ];
  const navTo = (params) => router.get("/admin/schedule/employees", params, { preserveState: false });
  return /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap items-start justify-between gap-3 mb-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[11px] tracking-wider font-bold uppercase text-om-downtime" }, __("Employee day planner \xB7 Tacho view")), /* @__PURE__ */ React.createElement("h1", { className: "text-2xl md:text-3xl font-bold text-om-ink mt-0.5" }, view === "team" ? `${__("Team day")} \xB7 ${formatDate(new Date(date), { weekday: "long", day: "numeric", month: "long", year: "numeric" })}` : view === "month" ? `${__("Month overview")} \xB7 ${formatDate(new Date(date), { month: "long", year: "numeric" })}` : /* @__PURE__ */ React.createElement(React.Fragment, null, selectedWorker?.name ?? __("Day plan"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faint font-normal text-lg" }, "\xB7 ", formatDate(new Date(date), { weekday: "short", day: "numeric", month: "short", year: "numeric" }))))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap items-center gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "inline-flex p-1 rounded-om-sm bg-om-line2" }, tabs.map(({ key, label }) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key,
      onClick: () => navTo({ view: key, date, worker_id: selectedWorkerId }),
      className: `px-3 py-1.5 rounded-md font-mono text-[11px] font-bold tracking-wider uppercase transition-colors ${view === key ? "bg-om-downtime text-white" : "text-om-muted hover:text-om-ink"}`
    },
    label
  ))), /* @__PURE__ */ React.createElement(Link, { href: "/admin/schedule", className: "px-3 py-2 text-xs font-medium text-om-muted hover:text-om-ink" }, "\u2190 ", __("Production schedule")), selectedWorkerId && /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/schedule/employees/add?worker_id=${selectedWorkerId}&date=${date}`,
      className: "inline-flex items-center gap-2 px-3 h-9 rounded-om-sm bg-om-downtime hover:brightness-95 text-white font-mono text-xs font-bold tracking-wider uppercase"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24", strokeWidth: "2.4" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M12 4v16m8-8H4" })),
    __("Add activity")
  )));
}
function EmployeeDay() {
  const {
    view,
    date,
    workers = [],
    selectedWorker,
    selectedWorkerId,
    activities = [],
    customTypes = [],
    typeMeta = {}
  } = usePage().props;
  const dateObj = new Date(date);
  const isToday = dateObj.toISOString().slice(0, 10) === (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
  const nowMin = isToday ? (/* @__PURE__ */ new Date()).getHours() * 60 + (/* @__PURE__ */ new Date()).getMinutes() : null;
  const sums = {};
  activities.forEach((a) => {
    sums[a.type] = (sums[a.type] ?? 0) + (a.duration ?? 0);
  });
  const totalWork = (sums.work ?? 0) + (sums.setup ?? 0) + (sums.qc ?? 0);
  const totalBreaks = (sums.break ?? 0) + (sums.rest ?? 0);
  const dayStrip = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(date);
    d.setDate(d.getDate() + (i - 3));
    return d.toISOString().slice(0, 10);
  });
  const navTo = (params) => router.get("/admin/schedule/employees", params, { preserveState: false });
  const handleDelete = async (actId) => {
    if (!confirm(__("Remove this activity?"))) return;
    const csrfToken = document.querySelector("meta[name=csrf-token]")?.content ?? "";
    await fetch(`/admin/schedule/employees/${actId}`, {
      method: "DELETE",
      headers: { "X-CSRF-TOKEN": csrfToken, "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" }
    });
    router.reload({ preserveState: false });
  };
  let spotlight = null;
  if (isToday && nowMin !== null) {
    spotlight = activities.find((a) => nowMin >= toMin(a.from) && nowMin < toMin(a.to === "24:00" ? "23:59" : a.to)) ?? null;
  }
  if (!spotlight) {
    spotlight = activities.find((a) => a.type !== "off") ?? null;
  }
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Employee Day Plan") }), /* @__PURE__ */ React.createElement(EmployeeTabs, { view, date, selectedWorkerId, selectedWorker, workers }), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-[260px_1fr_360px] gap-4" }, /* @__PURE__ */ React.createElement("aside", { className: "hidden lg:flex flex-col bg-om-card border border-om-line2 rounded-2xl p-4 min-h-[60vh]" }, /* @__PURE__ */ React.createElement("div", { className: "mb-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 px-3 py-2 rounded-om-sm bg-om-chip" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4 text-om-muted", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z" })), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      placeholder: __("Search worker"),
      className: "bg-transparent w-full text-sm text-om-muted placeholder-om-faint outline-none font-mono",
      onChange: (e) => {
      }
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10px] tracking-wider text-om-muted uppercase mt-1 mb-2" }, __("Workers"), " \xB7 ", workers.length), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-1.5 overflow-y-auto flex-1" }, workers.map((w) => {
    const on = w.id === selectedWorkerId;
    const parts = (w.name ?? "").trim().split(" ");
    const initials = ((parts[0]?.[0] ?? "") + (parts[parts.length - 1]?.[0] ?? "")).toUpperCase();
    return /* @__PURE__ */ React.createElement(
      "button",
      {
        key: w.id,
        onClick: () => navTo({ view: "day", date, worker_id: w.id }),
        className: `flex items-center gap-3 p-2.5 rounded-om-sm border transition-colors text-left ${on ? "bg-om-downtime-bg border-om-downtime" : "bg-om-panel border-om-line2 hover:bg-om-chip"}`
      },
      /* @__PURE__ */ React.createElement("div", { className: `w-8 h-8 rounded-om-sm font-mono text-[10px] font-bold flex items-center justify-center flex-shrink-0 ${on ? "bg-om-downtime text-white" : "bg-om-line2 text-om-muted"}` }, initials),
      /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0" }, /* @__PURE__ */ React.createElement("div", { className: "text-sm font-semibold text-om-ink truncate" }, w.name), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10px] text-om-muted mt-0.5 truncate" }, w.code, w.personnel_class_code ? ` \xB7 ${w.personnel_class_code}` : ""))
    );
  })), /* @__PURE__ */ React.createElement("div", { className: "mt-3 p-3 rounded-om-sm bg-om-panel" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase" }, __("Shift coverage")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-2xl font-bold text-emerald-600 mt-0.5" }, workers.length, /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-faint" }, "/", workers.length)))), /* @__PURE__ */ React.createElement("section", { className: "bg-om-card border border-om-line2 rounded-2xl p-4 md:p-5 flex flex-col gap-4 min-w-0" }, /* @__PURE__ */ React.createElement("div", { className: "flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1" }, dayStrip.map((d) => {
    const on = d === date;
    return /* @__PURE__ */ React.createElement(
      "button",
      {
        key: d,
        onClick: () => navTo({ view: "day", date: d, worker_id: selectedWorkerId }),
        className: `flex-shrink-0 px-3 py-2 rounded-om-sm font-mono text-[11px] font-bold tracking-wider uppercase border ${on ? "bg-om-downtime border-om-downtime text-white" : "bg-om-panel border-om-line2 text-om-muted hover:bg-om-chip"}`
      },
      formatDate(new Date(d), { weekday: "short", day: "numeric" })
    );
  })), /* @__PURE__ */ React.createElement("div", { className: "rounded-om bg-gradient-to-br from-gray-50 to-white border border-om-line2 p-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap justify-between items-start gap-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10px] tracking-wider font-bold uppercase text-om-downtime" }, formatDate(dateObj, { weekday: "short", day: "numeric", month: "short" }), " \xB7 ", __("A-shift")), /* @__PURE__ */ React.createElement("div", { className: "text-lg md:text-xl font-bold text-om-ink mt-1" }, __(":duration planned", { duration: fmtMins(totalWork + totalBreaks + (sums.maint ?? 0) + (sums.meeting ?? 0) + (sums.training ?? 0) + (sums.travel ?? 0)) })), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[11px] text-om-muted mt-0.5" }, __(":count activities", { count: activities.length }))), /* @__PURE__ */ React.createElement("div", { className: "text-right" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-xl md:text-2xl font-bold text-emerald-600" }, fmtMins(totalWork)), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase mt-0.5" }, __("Productive")))), /* @__PURE__ */ React.createElement("div", { className: "mt-4" }, /* @__PURE__ */ React.createElement(Tacho, { activities, typeMeta, height: 56, isToday, nowMinutes: nowMin })), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap justify-between gap-3 mt-4 font-mono text-[10.5px] text-om-muted" }, /* @__PURE__ */ React.createElement("span", null, "\u03A3 ", /* @__PURE__ */ React.createElement("span", { className: "font-bold text-om-ink" }, fmtMins(totalWork)), " ", __("work")), /* @__PURE__ */ React.createElement("span", null, /* @__PURE__ */ React.createElement("span", { className: "font-bold text-om-ink" }, fmtMins(totalBreaks)), " ", __("breaks")), /* @__PURE__ */ React.createElement("span", null, /* @__PURE__ */ React.createElement("span", { className: "font-bold text-rose-600" }, fmtMins(sums.maint ?? 0)), " ", __("maint")), /* @__PURE__ */ React.createElement("span", null, /* @__PURE__ */ React.createElement("span", { className: "font-bold text-om-ink" }, fmtMins(sums.off ?? 0)), " ", __("off")))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1.5" }, Object.entries(sums).map(([type, mins]) => {
    const def = typeMeta[type];
    if (!def) return null;
    return /* @__PURE__ */ React.createElement(
      "div",
      {
        key: type,
        className: "flex items-center gap-1.5 px-2.5 py-1 rounded-full border bg-om-card",
        style: { borderColor: `${def.color}80` }
      },
      /* @__PURE__ */ React.createElement("span", { className: "w-2 h-2 rounded-sm", style: { background: def.color } }),
      /* @__PURE__ */ React.createElement("span", { className: "text-xs font-semibold text-om-muted" }, def.label),
      /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] font-bold text-om-muted" }, fmtMins(mins))
    );
  })), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10.5px] tracking-wider text-om-muted uppercase mb-2" }, __("Activities"), " \xB7 ", activities.length), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-1" }, activities.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "p-6 text-center text-sm text-om-faint border border-dashed border-om-line2 rounded-om" }, __("No activities planned for this day.")) : activities.map((a, i) => {
    const def = typeMeta[a.type] ?? typeMeta.off ?? { color: "#94a3b8", label: a.type, short: "??" };
    const hl = isToday && nowMin !== null && nowMin >= toMin(a.from) && nowMin < toMin(a.to === "24:00" ? "23:59" : a.to);
    return /* @__PURE__ */ React.createElement("div", { key: i, className: `flex items-center gap-2.5 p-2.5 rounded-om border ${hl ? "bg-om-downtime-bg border-om-downtime" : "bg-om-panel border-om-line2"}` }, /* @__PURE__ */ React.createElement("div", { className: "w-1 self-stretch rounded-sm", style: { background: def.color } }), /* @__PURE__ */ React.createElement("div", { className: "w-8 h-8 rounded-om-sm flex items-center justify-center flex-shrink-0", style: { background: `${def.color}25` } }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[9px] font-bold tracking-wider", style: { color: def.color } }, def.short)), /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 flex-wrap" }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-semibold text-om-ink" }, a.label ?? def.label), hl && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[8.5px] px-1.5 py-0.5 rounded bg-om-downtime text-white font-bold tracking-wider" }, "NOW")), a.wo && /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10px] text-om-muted mt-0.5" }, a.wo, a.step ? ` \xB7 ${a.step}` : "")), /* @__PURE__ */ React.createElement("div", { className: "text-right min-w-[80px]" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[11px] text-om-muted font-semibold" }, a.from, " \u2192 ", a.to), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10px] mt-0.5 font-bold tracking-wider", style: { color: def.color } }, fmtMins(a.duration))), a.id && /* @__PURE__ */ React.createElement("button", { onClick: () => handleDelete(a.id), className: "p-1 text-om-faint hover:text-rose-500", title: __("Delete") }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22" }))));
  }))), selectedWorkerId && /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/schedule/employees/add?worker_id=${selectedWorkerId}&date=${date}`,
      className: "h-11 rounded-om border border-dashed border-om-line text-om-downtime font-mono text-[11.5px] font-bold tracking-wider uppercase flex items-center justify-center gap-2 hover:bg-om-downtime-bg"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24", strokeWidth: "2.4" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M12 4v16m8-8H4" })),
    __("Add activity")
  )), /* @__PURE__ */ React.createElement("aside", { className: "hidden lg:flex flex-col gap-3 bg-om-card border border-om-line2 rounded-2xl p-4" }, spotlight && (() => {
    const def = typeMeta[spotlight.type] ?? { color: "#94a3b8", label: spotlight.type, short: "??" };
    return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "w-2.5 h-2.5 rounded-sm", style: { background: def.color } }), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] tracking-wider font-bold uppercase text-om-downtime" }, __("Selected"), " \xB7 ", def.short)), /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mt-1.5" }, spotlight.label ?? def.label), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10.5px] text-om-muted mt-0.5" }, def.label.toUpperCase())), /* @__PURE__ */ React.createElement("div", { className: "rounded-om bg-om-panel p-3.5" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-baseline" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] tracking-wider text-om-muted uppercase" }, __("Duration")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-3xl font-bold mt-1 -tracking-wide", style: { color: def.color } }, fmtMins(spotlight.duration))), /* @__PURE__ */ React.createElement("div", { className: "text-right" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] tracking-wider text-om-muted uppercase" }, __("Window")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-sm font-bold mt-1 text-om-ink" }, spotlight.from, " \u2192 ", spotlight.to)))), spotlight.wo && /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "p-2.5 rounded-om-sm bg-om-panel" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase" }, __("Work Order")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-xs font-bold mt-1 text-om-ink" }, spotlight.wo)), /* @__PURE__ */ React.createElement("div", { className: "p-2.5 rounded-om-sm bg-om-panel" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase" }, __("Step")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-xs font-bold mt-1 text-om-ink" }, spotlight.step ?? "\u2014"))), spotlight.notes && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[10.5px] tracking-wider text-om-muted uppercase" }, __("Notes")), /* @__PURE__ */ React.createElement("div", { className: "mt-1.5 p-3 rounded-om-sm bg-om-panel text-xs italic text-om-muted leading-relaxed" }, "\u201C", spotlight.notes, "\u201D")));
  })(), /* @__PURE__ */ React.createElement("div", { className: "flex-1" }), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "p-2.5 rounded-om-sm bg-om-panel" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase" }, __("Activities")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-lg font-bold mt-1 text-om-ink" }, activities.length)), /* @__PURE__ */ React.createElement("div", { className: "p-2.5 rounded-om-sm bg-om-panel" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9px] tracking-wider text-om-muted uppercase" }, __("Maint")), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-lg font-bold mt-1 text-rose-600" }, fmtMins(sums.maint ?? 0)))))));
}
EmployeeDay.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  EmployeeTabs,
  Tacho,
  EmployeeDay as default
};
