import { useMemo, useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import { DatePicker, Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
const ACTION_COLORS = {
  created: "bg-om-running-bg text-om-running",
  updated: "bg-om-chip text-om-accent",
  deleted: "bg-om-blocked-bg text-om-blocked",
  login: "bg-om-chip text-purple-700",
  logout: "bg-om-chip text-om-muted",
  login_failed: "bg-om-blocked-bg text-om-blocked"
};
const METHOD_COLORS = {
  GET: "bg-om-chip text-om-muted",
  POST: "bg-om-running-bg text-om-running",
  PUT: "bg-om-chip text-om-accent",
  PATCH: "bg-om-chip text-om-accent",
  DELETE: "bg-om-blocked-bg text-om-blocked"
};
function entityLabel(log) {
  const type = log.entity_type ? String(log.entity_type).split("\\").pop() : null;
  if (!type) return null;
  return log.entity_id ? `${type} #${log.entity_id}` : type;
}
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
function DetailModal({ log, onClose }) {
  if (!log) return null;
  const formatTs = (v) => {
    if (!v) return "\u2014";
    return String(v).replace("T", " ").replace(/\.\d+Z?$/, "");
  };
  const prettyJson = (v) => {
    if (v == null) return "";
    try {
      return JSON.stringify(v, null, 2);
    } catch {
      return String(v);
    }
  };
  return /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4",
      role: "dialog",
      "aria-modal": "true",
      "aria-labelledby": "log-detail-title",
      onClick: onClose
    },
    /* @__PURE__ */ React.createElement(
      "div",
      {
        className: "bg-om-card rounded-om-sm shadow-xl max-w-3xl w-full max-h-[80vh] overflow-y-auto",
        onClick: (e) => e.stopPropagation()
      },
      /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between p-4 border-b" }, /* @__PURE__ */ React.createElement("h3", { id: "log-detail-title", className: "text-lg font-semibold" }, "Log entry details"), /* @__PURE__ */ React.createElement(
        "button",
        {
          type: "button",
          onClick: onClose,
          className: "text-om-faint hover:text-om-ink text-xl leading-none",
          "aria-label": "Close"
        },
        "\xD7"
      )),
      /* @__PURE__ */ React.createElement("div", { className: "p-4 space-y-3 text-sm" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Timestamp:"), " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs" }, formatTs(log.created_at))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Source:"), " ", /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded text-xs uppercase ${log.source === "audit" ? "bg-om-chip text-purple-700" : "bg-om-chip text-om-muted"}` }, log.source || "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "User:"), " ", /* @__PURE__ */ React.createElement("span", null, log.user?.name ?? "Guest")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "IP address:"), " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs" }, log.ip_address || "\u2014")), log.source === "audit" && /* @__PURE__ */ React.createElement("div", { className: "space-y-2 border-t pt-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Action:"), " ", /* @__PURE__ */ React.createElement("span", null, log.action || "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Entity:"), " ", /* @__PURE__ */ React.createElement("span", null, entityLabel(log) || "\u2014")), log.before_state && /* @__PURE__ */ React.createElement("details", { className: "mt-2" }, /* @__PURE__ */ React.createElement("summary", { className: "text-xs text-om-muted cursor-pointer hover:text-om-ink" }, "Before state"), /* @__PURE__ */ React.createElement("pre", { className: "bg-om-panel p-2 rounded text-xs overflow-x-auto mt-1 whitespace-pre-wrap break-words" }, prettyJson(log.before_state))), log.after_state && /* @__PURE__ */ React.createElement("details", { className: "mt-2", open: true }, /* @__PURE__ */ React.createElement("summary", { className: "text-xs text-om-muted cursor-pointer hover:text-om-ink" }, "After state"), /* @__PURE__ */ React.createElement("pre", { className: "bg-om-panel p-2 rounded text-xs overflow-x-auto mt-1 whitespace-pre-wrap break-words" }, prettyJson(log.after_state)))), log.source === "request" && /* @__PURE__ */ React.createElement("div", { className: "space-y-2 border-t pt-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Method:"), " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono px-2 py-0.5 rounded bg-om-chip text-xs" }, log.method || "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Path:"), " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs break-all" }, log.path || "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Route name:"), " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs" }, log.route_name || "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Status:"), " ", /* @__PURE__ */ React.createElement("span", null, log.status ?? "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Duration:"), " ", /* @__PURE__ */ React.createElement("span", null, log.duration_ms != null ? `${log.duration_ms} ms` : "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("strong", { className: "text-om-muted" }, "Sampled:"), " ", /* @__PURE__ */ React.createElement("span", null, log.sampled ? "yes" : "no"))), /* @__PURE__ */ React.createElement("div", { className: "text-xs text-om-faint pt-3 border-t break-words" }, /* @__PURE__ */ React.createElement("strong", null, "User agent:"), " ", /* @__PURE__ */ React.createElement("span", null, log.user_agent || "\u2014"))),
      /* @__PURE__ */ React.createElement("div", { className: "flex justify-end p-3 border-t bg-om-panel" }, /* @__PURE__ */ React.createElement(
        "button",
        {
          type: "button",
          onClick: onClose,
          className: "px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
        },
        "Close"
      ))
    )
  );
}
function Activity() {
  const { logs, users = [], actions = [], entityTypes = [], filters = {} } = usePage().props;
  const [form, setForm] = useState({
    from: filters.from ?? "",
    to: filters.to ?? "",
    user_id: filters.user_id ?? "",
    source: filters.source ?? "",
    entity_type: filters.entity_type ?? "",
    action: filters.action ?? ""
  });
  const [detailLog, setDetailLog] = useState(null);
  const apply = (overrides = {}) => {
    const params = { ...form, ...overrides };
    Object.keys(params).forEach((k) => {
      if (!params[k]) delete params[k];
    });
    router.get("/admin/logs/activity", params, { preserveState: false });
  };
  const clear = () => {
    router.get("/admin/logs/activity", {}, { preserveState: false });
  };
  const goPage = (page) => {
    const params = { ...form, page };
    Object.keys(params).forEach((k) => {
      if (!params[k]) delete params[k];
    });
    router.get("/admin/logs/activity", params, { preserveState: false });
  };
  const exportUrl = () => {
    const p = new URLSearchParams();
    Object.entries(form).forEach(([k, v]) => {
      if (v) p.set(k, v);
    });
    const qs = p.toString();
    return `/admin/logs/activity/export${qs ? "?" + qs : ""}`;
  };
  const logItems = logs?.data ?? [];
  const meta = logs?.meta ?? null;
  const paginationLinks = logs?.links ?? [];
  const columns = useMemo(() => [
    {
      id: "when",
      accessorFn: (r) => r.created_at,
      header: "When",
      cell: ({ row }) => {
        const log = row.original;
        return /* @__PURE__ */ React.createElement("span", { className: "text-om-muted whitespace-nowrap text-xs" }, log.created_at ? String(log.created_at).replace("T", " ").replace(/\.\d+Z?$/, "") : "\u2014");
      }
    },
    {
      id: "who",
      accessorFn: (r) => r.user?.name ?? "Guest",
      header: "Who",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-ink whitespace-nowrap" }, row.original.user?.name ?? "Guest")
    },
    {
      id: "what",
      accessorFn: (r) => r.source === "audit" ? r.action : r.path,
      header: "What",
      cell: ({ row }) => {
        const log = row.original;
        return log.source === "audit" ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("span", { className: `inline-block px-2 py-0.5 rounded text-xs font-medium ${ACTION_COLORS[log.action] ?? "bg-om-chip text-om-muted"}` }, log.action ? log.action.replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase()) : "\u2014"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-muted ml-1" }, entityLabel(log))) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("span", { className: `font-mono text-xs px-2 py-0.5 rounded ${METHOD_COLORS[log.method] ?? "bg-om-chip text-om-muted"}` }, log.method), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-muted text-xs font-mono break-all" }, log.path), " ", /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint whitespace-nowrap" }, "\u2192 ", log.status, " \u2022 ", log.duration_ms, "ms"));
      }
    },
    {
      id: "details",
      header: "Details",
      enableSorting: false,
      cell: ({ row }) => {
        const log = row.original;
        return /* @__PURE__ */ React.createElement("div", { className: "text-xs text-om-muted" }, /* @__PURE__ */ React.createElement(
          "button",
          {
            type: "button",
            onClick: () => setDetailLog(log),
            className: "text-om-accent hover:underline text-xs"
          },
          "Details"
        ), log.source === "audit" && (log.action === "updated" || log.action === "created") && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("span", { className: "text-om-faintest mx-1" }, "|"), /* @__PURE__ */ React.createElement(
          "a",
          {
            href: `/admin/audit-logs?user_id=${log.user_id ?? ""}&entity_type=${encodeURIComponent(log.entity_type ?? "")}`,
            className: "text-om-accent hover:underline"
          },
          "View changes"
        )), /* @__PURE__ */ React.createElement("div", { className: "text-om-faint mt-1" }, log.ip_address));
      }
    }
  ], [setDetailLog]);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: "Activity Logs" }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, "Activity Logs"), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, "What users did across the system \u2014 entity changes, navigation, auth events.")), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-5 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "From"), /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: form.from || null,
      onChange: (iso) => setForm((f) => ({ ...f, from: iso ?? "" })),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "To"), /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: form.to || null,
      onChange: (iso) => setForm((f) => ({ ...f, to: iso ?? "" })),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "User"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.user_id == null ? "" : String(form.user_id),
      onChange: (v) => setForm((f) => ({ ...f, user_id: v })),
      options: [
        { value: "", label: "All users" },
        ...users.map((u) => ({ value: String(u.id), label: u.name }))
      ],
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Source"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.source == null ? "" : String(form.source),
      onChange: (v) => setForm((f) => ({ ...f, source: v })),
      options: [
        { value: "", label: "All sources" },
        { value: "audit", label: "Entity changes" },
        { value: "request", label: "Navigation" }
      ],
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Entity"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.entity_type == null ? "" : String(form.entity_type),
      onChange: (v) => setForm((f) => ({ ...f, entity_type: v })),
      options: [
        { value: "", label: "All entities" },
        ...entityTypes.map((et) => ({ value: String(et), label: String(et).split("\\").pop() }))
      ],
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Action"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.action == null ? "" : String(form.action),
      onChange: (v) => setForm((f) => ({ ...f, action: v })),
      options: [
        { value: "", label: "All actions" },
        ...actions.map((a) => ({ value: String(a), label: a }))
      ],
      className: "w-full"
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-2 mt-3" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => apply(),
      className: "px-4 py-2 text-sm font-medium rounded-om-sm bg-om-ink text-om-on-ink hover:bg-om-ink-hover transition-colors"
    },
    "Apply"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: clear,
      className: "px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
    },
    "Clear"
  ), /* @__PURE__ */ React.createElement(
    "a",
    {
      href: exportUrl(),
      className: "sm:ml-auto px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
    },
    "Export CSV"
  ))), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: logItems,
      columns,
      searchable: true,
      columnToggle: true,
      paginated: false,
      searchPlaceholder: "Search activity\u2026",
      columnsLabel: "Columns",
      columnsMenuLabel: "Toggle columns",
      emptyLabel: "No activity in this period."
    }
  ), meta && meta.last_page > 1 && /* @__PURE__ */ React.createElement("div", { className: "mt-3" }, /* @__PURE__ */ React.createElement(Pagination, { meta, links: paginationLinks, onPage: goPage }))), detailLog && /* @__PURE__ */ React.createElement(DetailModal, { log: detailLog, onClose: () => setDetailLog(null) }));
}
Activity.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  Activity as default
};
