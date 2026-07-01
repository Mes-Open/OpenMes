import { Head, router, usePage } from "@inertiajs/react";
import { useMemo } from "react";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
const TYPE_COLORS = {
  production: "bg-om-chip text-om-accent",
  inspection: "bg-om-downtime-bg text-om-downtime",
  maintenance: "bg-om-downtime-bg text-om-downtime",
  setup: "bg-om-chip text-om-muted",
  cleaning: "bg-om-running-bg text-om-running",
  transport: "bg-om-chip text-om-ink",
  other: "bg-om-chip text-om-muted"
};
function ProcessSegmentShow() {
  const { segment, usingSteps = [], requiredSkills = [] } = usePage().props;
  const typeColor = TYPE_COLORS[segment.segment_type] ?? "bg-om-chip text-om-muted";
  const usageCount = usingSteps.length;
  const handleDelete = () => {
    if (!confirm("Delete this process segment?")) return;
    router.delete(`/admin/process-segments/${segment.id}`, { preserveScroll: false });
  };
  const usageColumns = useMemo(() => [
    {
      id: "product",
      accessorFn: (r) => r.product_type_name,
      header: "Product",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.product_type_name ?? "\u2014")
    },
    {
      id: "template",
      accessorFn: (r) => r.template_name,
      header: "Template",
      cell: ({ row }) => row.original.template_url ? /* @__PURE__ */ React.createElement("a", { href: row.original.template_url, className: "text-om-accent hover:underline" }, row.original.template_name) : /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.template_name ?? "\u2014")
    },
    {
      id: "step_number",
      accessorKey: "step_number",
      header: "Step #",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.step_number)
    },
    {
      id: "name",
      accessorKey: "name",
      header: "Step name",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, row.original.name)
    },
    {
      id: "workstation",
      accessorFn: (r) => r.workstation_name,
      header: "Workstation",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.workstation_name ?? "\u2014")
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `Process Segment \u2014 ${segment.name}` }), /* @__PURE__ */ React.createElement("div", { className: "max-w-5xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 flex-wrap" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-sm text-om-muted" }, segment.code), /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded-full text-xs font-medium ${typeColor}` }, capitalize(segment.segment_type)), segment.is_active ? /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded-full text-xs bg-om-running-bg text-om-running" }, "Active") : /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded-full text-xs bg-om-chip text-om-muted" }, "Inactive")), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mt-1" }, segment.name), segment.description && /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, segment.description)), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement("a", { href: `/admin/process-segments/${segment.id}/edit`, className: "btn-touch btn-secondary" }, "Edit"), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: handleDelete,
      disabled: usageCount > 0,
      className: `btn-touch ${usageCount > 0 ? "bg-om-chip text-om-faint cursor-not-allowed" : "bg-om-blocked-bg text-om-blocked hover:bg-om-blocked-bg"}`
    },
    "Delete"
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-2 space-y-4" }, /* @__PURE__ */ React.createElement("section", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-4" }, "Definition"), /* @__PURE__ */ React.createElement("dl", { className: "grid grid-cols-1 sm:grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase tracking-wide" }, "Code"), /* @__PURE__ */ React.createElement("dd", { className: "mt-1 font-mono text-om-ink" }, segment.code)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase tracking-wide" }, "Type"), /* @__PURE__ */ React.createElement("dd", { className: "mt-1" }, /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded-full text-xs font-medium ${typeColor}` }, capitalize(segment.segment_type)))), segment.description && /* @__PURE__ */ React.createElement("div", { className: "sm:col-span-2" }, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase tracking-wide" }, "Description"), /* @__PURE__ */ React.createElement("dd", { className: "mt-1 text-om-muted whitespace-pre-wrap" }, segment.description)), segment.standard_instruction && /* @__PURE__ */ React.createElement("div", { className: "sm:col-span-2" }, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase tracking-wide" }, "Standard instruction"), /* @__PURE__ */ React.createElement("dd", { className: "mt-1 text-om-muted whitespace-pre-wrap p-3 bg-om-panel rounded" }, segment.standard_instruction)))), /* @__PURE__ */ React.createElement("section", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-4" }, "Execution"), /* @__PURE__ */ React.createElement("dl", { className: "grid grid-cols-1 sm:grid-cols-3 gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase tracking-wide" }, "Workstation type"), /* @__PURE__ */ React.createElement("dd", { className: "mt-1 text-om-ink" }, segment.workstation_type_name ?? "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase tracking-wide" }, "Estimated duration"), /* @__PURE__ */ React.createElement("dd", { className: "mt-1 text-om-ink" }, segment.estimated_duration_minutes != null ? `${segment.estimated_duration_minutes} min` : "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase tracking-wide" }, "Required operators"), /* @__PURE__ */ React.createElement("dd", { className: "mt-1 text-om-ink" }, segment.required_operators)), /* @__PURE__ */ React.createElement("div", { className: "sm:col-span-3" }, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase tracking-wide mb-1" }, "Required skills"), /* @__PURE__ */ React.createElement("dd", null, requiredSkills.length === 0 ? /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-faint" }, "\u2014 None \u2014") : /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1" }, requiredSkills.map((skill) => /* @__PURE__ */ React.createElement("span", { key: skill.id, className: "px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium" }, skill.code, " \xB7 ", skill.name))))), /* @__PURE__ */ React.createElement("div", { className: "sm:col-span-3" }, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase tracking-wide mb-1" }, "Parameters"), /* @__PURE__ */ React.createElement("dd", null, !segment.parameters ? /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-faint" }, "\u2014 None \u2014") : /* @__PURE__ */ React.createElement("pre", { className: "text-xs bg-om-ink text-gray-100 p-3 rounded overflow-x-auto" }, JSON.stringify(segment.parameters, null, 2)))))), /* @__PURE__ */ React.createElement("section", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-1" }, "Usage"), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mb-3" }, "Template steps that reference this segment."), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: usingSteps,
      columns: usageColumns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: "Not used by any process template yet."
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement("section", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-3" }, "Metadata"), /* @__PURE__ */ React.createElement("ul", { className: "space-y-2 text-sm" }, /* @__PURE__ */ React.createElement("li", { className: "flex justify-between gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "Used by"), /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, usageCount, " step(s)")), /* @__PURE__ */ React.createElement("li", { className: "flex justify-between gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "Created"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, segment.created_at)), segment.created_by_name && /* @__PURE__ */ React.createElement("li", { className: "flex justify-between gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "Created by"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, segment.created_by_name)), /* @__PURE__ */ React.createElement("li", { className: "flex justify-between gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "Updated"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, segment.updated_at))))))));
}
ProcessSegmentShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function capitalize(str) {
  if (!str) return "";
  return str.charAt(0).toUpperCase() + str.slice(1);
}
export {
  ProcessSegmentShow as default
};
