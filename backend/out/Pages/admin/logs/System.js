import { useState, useEffect, useRef, useMemo } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import { DatePicker, Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
const LEVEL_BADGE = {
  debug: "bg-om-chip text-om-muted",
  info: "bg-om-chip text-om-accent",
  notice: "bg-om-chip text-om-accent",
  warning: "bg-om-downtime-bg text-om-downtime",
  error: "bg-om-blocked-bg text-om-blocked",
  critical: "bg-om-blocked-bg text-om-blocked font-bold",
  alert: "bg-om-blocked-bg text-om-blocked font-bold",
  emergency: "bg-om-blocked-bg border border-om-blocked text-om-blocked font-bold"
};
const DEPLOYMENT_STATE_BADGE = {
  completed: "bg-om-running-bg text-om-running",
  failed: "bg-om-blocked-bg text-om-blocked",
  rolled_back: "bg-om-blocked-bg text-om-blocked",
  queued: "bg-om-chip text-om-accent",
  in_progress: "bg-om-chip text-om-accent"
};
function Pagination({ meta, links, onPage }) {
  if (!meta || meta.last_page <= 1) return null;
  return /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1 flex-wrap" }, links.map((link, i) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key: i,
      type: "button",
      disabled: !link.url,
      onClick: () => link.url && onPage(new URL(link.url).searchParams.get("page")),
      className: `px-3 py-1 text-sm rounded border transition-colors ${link.active ? "bg-om-ink text-om-on-ink border-om-accent" : link.url ? "border-om-line text-om-muted hover:bg-om-bg" : "border-om-line2 text-om-faint cursor-default"}`,
      dangerouslySetInnerHTML: { __html: link.label }
    }
  )));
}
function AppTab({ entries: initialEntries, availableDates, date, level, search }) {
  const [form, setForm] = useState({ date: date ?? "", level: level ?? "", search: search ?? "" });
  const [entries, setEntries] = useState(Array.isArray(initialEntries) ? initialEntries : []);
  const [live, setLive] = useState(false);
  const [liveError, setLiveError] = useState(null);
  const timerRef = useRef(null);
  useEffect(() => {
    if (!live) {
      setEntries(Array.isArray(initialEntries) ? initialEntries : []);
    }
  }, [initialEntries]);
  const applyFilters = () => {
    const params = { tab: "app", ...form };
    Object.keys(params).forEach((k) => {
      if (!params[k]) delete params[k];
    });
    if (!params.tab) params.tab = "app";
    router.get("/admin/logs/system", params, { preserveState: false });
  };
  const clearFilters = () => {
    router.get("/admin/logs/system", { tab: "app" }, { preserveState: false });
  };
  const fetchLive = async () => {
    try {
      const r = await fetch("/admin/logs/system/tail", {
        headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        cache: "no-store",
        credentials: "same-origin"
      });
      if (!r.ok) {
        setLiveError(`Live tail error: ${r.status}`);
        return;
      }
      const d = await r.json();
      setEntries(Array.isArray(d.entries) ? d.entries : []);
      setLiveError(null);
    } catch {
      setLiveError("Live tail unreachable");
    }
  };
  const startLive = () => {
    setLive(true);
    setLiveError(null);
    fetchLive();
    timerRef.current = setInterval(fetchLive, 5e3);
  };
  const stopLive = () => {
    setLive(false);
    clearInterval(timerRef.current);
    timerRef.current = null;
  };
  useEffect(() => () => clearInterval(timerRef.current), []);
  const truncate = (s, n) => !s ? "" : s.length > n ? s.substring(0, n) + "\u2026" : s;
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-5 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Date"), availableDates && availableDates.length > 0 ? /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.date == null ? "" : String(form.date),
      onChange: (v) => setForm((f) => ({ ...f, date: v })),
      options: availableDates.map((d) => ({ value: String(d), label: d })),
      className: "w-full"
    }
  ) : /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: form.date || null,
      onChange: (iso) => setForm((f) => ({ ...f, date: iso ?? "" })),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Level"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.level == null ? "" : String(form.level),
      onChange: (v) => setForm((f) => ({ ...f, level: v })),
      options: [
        { value: "", label: "All levels" },
        ...["debug", "info", "notice", "warning", "error", "critical", "alert", "emergency"].map((lvl) => ({
          value: lvl,
          label: lvl.charAt(0).toUpperCase() + lvl.slice(1)
        }))
      ],
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "sm:col-span-2" }, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Search"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.search,
      onChange: (e) => setForm((f) => ({ ...f, search: e.target.value })),
      placeholder: "Search message or stack trace\u2026",
      className: "form-input w-full",
      onKeyDown: (e) => {
        if (e.key === "Enter") applyFilters();
      }
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-2 mt-3" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: applyFilters,
      className: "px-4 py-2 text-sm font-medium rounded-om-sm bg-om-ink text-om-on-ink hover:bg-om-ink-hover transition-colors"
    },
    "Apply"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: clearFilters,
      className: "px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
    },
    "Clear"
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: live ? stopLive : startLive,
      "aria-pressed": live,
      className: "px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors flex items-center gap-2"
    },
    live ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("span", { className: "w-2 h-2 rounded-full bg-om-blocked animate-pulse", "aria-hidden": "true" }), "Live \u2014 stop") : /* @__PURE__ */ React.createElement(React.Fragment, null, "\u25B6 Live tail")
  ), live && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, liveError ? /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, liveError) : "Auto-refreshing every 5s")), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm overflow-hidden" }, entries.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "px-4 py-16 text-center text-om-faint" }, "No log entries match your filters.") : entries.map((entry, idx) => /* @__PURE__ */ React.createElement(AppLogEntry, { key: `${idx}:${entry.timestamp}:${String(entry.message ?? "").substring(0, 40)}`, entry, truncate }))), entries.length > 0 && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mt-2" }, entries.length, " entries shown (most recent first). Older entries beyond the 2 MB tail window are not displayed."));
}
function AppLogEntry({ entry, truncate }) {
  const [open, setOpen] = useState(false);
  const badgeCls = LEVEL_BADGE[(entry.level ?? "").toLowerCase()] ?? "bg-om-chip text-om-muted";
  const hasContext = entry.context && entry.context.trim() !== "" || entry.message && entry.message.length > 300;
  return /* @__PURE__ */ React.createElement("div", { className: "border-b last:border-b-0" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setOpen((v) => !v),
      className: "w-full text-left px-4 py-3 hover:bg-om-bg flex items-start gap-3"
    },
    /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs text-om-muted whitespace-nowrap mt-0.5" }, entry.timestamp),
    /* @__PURE__ */ React.createElement("span", { className: `inline-block px-2 py-0.5 rounded text-xs uppercase whitespace-nowrap ${badgeCls}` }, entry.level),
    /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint whitespace-nowrap" }, entry.environment),
    /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-ink break-all flex-1" }, truncate(entry.message, 300))
  ), open && hasContext && /* @__PURE__ */ React.createElement("pre", { className: "bg-om-panel text-xs text-om-muted px-4 py-3 overflow-x-auto whitespace-pre-wrap break-words border-t" }, entry.message, entry.context ? "\n\n" + entry.context.replace(/\s+$/, "") : ""));
}
function FailedJobsTab({ entries, missing }) {
  const logItems = entries?.data ?? (Array.isArray(entries) ? entries : []);
  const meta = entries?.meta ?? null;
  const paginationLinks = entries?.links ?? [];
  const { csrf_token } = usePage().props;
  const goPage = (page) => {
    router.get("/admin/logs/system", { tab: "failed_jobs", page }, { preserveState: false });
  };
  const columns = useMemo(() => [
    {
      id: "id",
      accessorKey: "id",
      header: "ID",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs font-mono text-om-muted whitespace-nowrap" }, row.original.id)
    },
    {
      id: "connection",
      accessorKey: "connection",
      header: "Connection",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted whitespace-nowrap" }, row.original.connection)
    },
    {
      id: "queue",
      accessorKey: "queue",
      header: "Queue",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted whitespace-nowrap" }, row.original.queue)
    },
    {
      id: "payload",
      header: "Payload",
      enableSorting: false,
      cell: ({ row }) => /* @__PURE__ */ React.createElement(FailedJobPayloadCell, { job: row.original })
    },
    {
      id: "exception",
      accessorFn: (r) => r.exception,
      header: "Exception",
      enableSorting: false,
      meta: { flex: true },
      cell: ({ row }) => /* @__PURE__ */ React.createElement(FailedJobExceptionCell, { job: row.original })
    },
    {
      id: "failed_at",
      accessorKey: "failed_at",
      header: "Failed at",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted whitespace-nowrap font-mono" }, row.original.failed_at)
    },
    {
      id: "actions",
      header: "Actions",
      enableSorting: false,
      cell: ({ row }) => /* @__PURE__ */ React.createElement("form", { method: "POST", action: `/admin/logs/system/retry-failed-job/${row.original.uuid}`, className: "inline" }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrf_token }), /* @__PURE__ */ React.createElement(
        "button",
        {
          type: "submit",
          className: "px-3 py-1 text-xs rounded bg-om-ink text-om-on-ink hover:bg-om-ink-hover transition-colors"
        },
        "Retry"
      ))
    }
  ], [csrf_token]);
  if (missing) {
    return /* @__PURE__ */ React.createElement("div", { className: "rounded-om-sm border border-om-line bg-om-downtime-bg text-amber-900 p-5" }, /* @__PURE__ */ React.createElement("p", { className: "font-medium" }, "Failed jobs table is missing."), /* @__PURE__ */ React.createElement("p", { className: "text-sm mt-1" }, "Run the Laravel queue migrations to enable the failed_jobs table."));
  }
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: logItems,
      columns,
      paginated: false,
      searchPlaceholder: "Search failed jobs\u2026",
      columnsLabel: "Columns",
      columnsMenuLabel: "Toggle columns",
      emptyLabel: "No failed jobs."
    }
  ), meta && meta.last_page > 1 && /* @__PURE__ */ React.createElement("div", { className: "mt-3" }, /* @__PURE__ */ React.createElement(Pagination, { meta, links: paginationLinks, onPage: goPage })));
}
function FailedJobPayloadCell({ job }) {
  const [showPayload, setShowPayload] = useState(false);
  return /* @__PURE__ */ React.createElement("div", { className: "text-xs text-om-muted" }, /* @__PURE__ */ React.createElement("button", { type: "button", onClick: () => setShowPayload((v) => !v), className: "text-om-accent hover:underline" }, showPayload ? "Hide payload" : "View payload"), showPayload && /* @__PURE__ */ React.createElement("pre", { className: "mt-2 bg-om-panel p-2 rounded max-w-xl overflow-x-auto whitespace-pre-wrap break-words" }, String(job.payload ?? "").substring(0, 4e3)));
}
function FailedJobExceptionCell({ job }) {
  const [showTrace, setShowTrace] = useState(false);
  const firstLine = (job.exception ?? "").split("\n")[0].substring(0, 200);
  return /* @__PURE__ */ React.createElement("div", { className: "text-xs text-om-muted max-w-md" }, /* @__PURE__ */ React.createElement("div", { className: "text-om-blocked break-words" }, firstLine), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: () => setShowTrace((v) => !v), className: "mt-1 text-om-accent hover:underline text-xs" }, showTrace ? "Hide stack trace" : "Show stack trace"), showTrace && /* @__PURE__ */ React.createElement("pre", { className: "mt-2 bg-om-panel p-2 rounded max-w-xl overflow-x-auto whitespace-pre-wrap break-words text-om-muted" }, String(job.exception ?? "").substring(0, 8e3)));
}
function DeploymentsTab({ entries, missing }) {
  const logItems = entries?.data ?? (Array.isArray(entries) ? entries : []);
  const meta = entries?.meta ?? null;
  const paginationLinks = entries?.links ?? [];
  const goPage = (page) => {
    router.get("/admin/logs/system", { tab: "deployments", page }, { preserveState: false });
  };
  if (missing) {
    return /* @__PURE__ */ React.createElement("div", { className: "rounded-om-sm border border-om-line bg-om-chip text-om-ink p-5" }, /* @__PURE__ */ React.createElement("p", { className: "font-medium" }, "Deployment audit log is not available on this build."), /* @__PURE__ */ React.createElement("p", { className: "text-sm mt-1" }, "Deployments log requires v0.12+ schema (system_updates table). This table was introduced by the updater hardening work and has not been merged into this branch yet."), /* @__PURE__ */ React.createElement("p", { className: "text-xs mt-2 text-om-accent" }, "Once the system_updates migration lands, this tab will surface start/end timestamps, the upgraded version, success/failure status, and any error output from each deployment."));
  }
  const columns = useMemo(() => [
    {
      id: "started_at",
      accessorKey: "started_at",
      header: "Started",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs font-mono text-om-muted whitespace-nowrap" }, row.original.started_at ?? "\u2014")
    },
    {
      id: "finished_at",
      accessorKey: "finished_at",
      header: "Finished",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs font-mono text-om-muted whitespace-nowrap" }, row.original.finished_at ?? "\u2014")
    },
    {
      id: "version",
      accessorFn: (r) => `${r.from_version ?? ""} ${r.to_version ?? ""}`,
      header: "Version",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-ink whitespace-nowrap font-mono" }, row.original.from_version ?? "\u2014", " \u2192 ", row.original.to_version ?? "\u2014")
    },
    {
      id: "status",
      accessorFn: (r) => r.state ?? "unknown",
      header: "Status",
      cell: ({ row }) => {
        const state = row.original.state ?? "unknown";
        const badgeCls = DEPLOYMENT_STATE_BADGE[state] ?? "bg-om-chip text-om-muted";
        return /* @__PURE__ */ React.createElement("span", { className: `inline-block px-2 py-0.5 rounded text-xs font-medium ${badgeCls}` }, state.replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase()));
      }
    },
    {
      id: "triggered_by",
      accessorKey: "triggered_by",
      header: "Triggered by",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted whitespace-nowrap" }, row.original.triggered_by ?? "\u2014")
    },
    {
      id: "output",
      header: "Output",
      enableSorting: false,
      meta: { flex: true },
      cell: ({ row }) => /* @__PURE__ */ React.createElement(DeploymentOutputCell, { row: row.original })
    }
  ], []);
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: logItems,
      columns,
      paginated: false,
      searchPlaceholder: "Search deployments\u2026",
      columnsLabel: "Columns",
      columnsMenuLabel: "Toggle columns",
      emptyLabel: "No deployments recorded."
    }
  ), meta && meta.last_page > 1 && /* @__PURE__ */ React.createElement("div", { className: "mt-3" }, /* @__PURE__ */ React.createElement(Pagination, { meta, links: paginationLinks, onPage: goPage })));
}
function DeploymentOutputCell({ row }) {
  const [showOutput, setShowOutput] = useState(false);
  return /* @__PURE__ */ React.createElement("div", { className: "text-xs text-om-muted" }, row.error ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("button", { type: "button", onClick: () => setShowOutput((v) => !v), className: "text-om-accent hover:underline" }, showOutput ? "Hide output" : "View output"), showOutput && /* @__PURE__ */ React.createElement("pre", { className: "mt-2 bg-om-panel p-2 rounded max-w-xl overflow-x-auto whitespace-pre-wrap break-words" }, String(row.error).substring(0, 8e3))) : /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "\u2014"));
}
const TABS = [
  { key: "app", label: "Application log" },
  { key: "failed_jobs", label: "Failed jobs" },
  { key: "deployments", label: "Deployments" }
];
function System() {
  const { tab, entries, availableDates, date, level, search, missing } = usePage().props;
  const switchTab = (key) => {
    router.get("/admin/logs/system", { tab: key }, { preserveState: false });
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: "System Logs" }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, "System Logs"), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, "Application errors, failed jobs, and deployment events \u2014 for diagnostics.")), /* @__PURE__ */ React.createElement("div", { className: "border-b mb-4 flex gap-1 flex-wrap" }, TABS.map(({ key, label }) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key,
      type: "button",
      onClick: () => switchTab(key),
      className: `px-4 py-2 text-sm font-medium border-b-2 -mb-px transition ${tab === key ? "border-om-accent text-om-accent" : "border-transparent text-om-muted hover:text-om-ink"}`
    },
    label
  ))), tab === "app" && /* @__PURE__ */ React.createElement(
    AppTab,
    {
      entries,
      availableDates,
      date,
      level,
      search
    }
  ), tab === "failed_jobs" && /* @__PURE__ */ React.createElement(FailedJobsTab, { entries, missing: missing ?? false }), tab === "deployments" && /* @__PURE__ */ React.createElement(DeploymentsTab, { entries, missing: missing ?? false })));
}
System.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  System as default
};
