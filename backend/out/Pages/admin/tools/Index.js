import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { TOOL_STATUS_LABELS } from "./fields";
import { __ } from "../../../lib/i18n";
const STATUS_STYLES = {
  available: "bg-om-running-bg text-om-running",
  in_use: "bg-om-chip text-om-accent",
  maintenance: "bg-om-downtime-bg text-om-downtime",
  retired: "bg-om-line2 text-om-muted"
};
function ToolsIndex() {
  const { workstationTypeNames = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    {
      key: "type",
      label: __("Workstation Type"),
      className: "text-om-muted",
      render: (r) => workstationTypeNames[r.workstation_type_id] ?? "\u2014"
    },
    {
      key: "status",
      label: __("Status"),
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: `text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? "bg-om-chip text-om-muted"}` }, TOOL_STATUS_LABELS[r.status] ?? r.status ?? "\u2014")
    },
    { key: "next_service_at", label: __("Next Service"), className: "text-om-muted", render: (r) => r.next_service_at ?? "\u2014" }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/tools/${r.id}/edit` },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete tool ":name"?', { name: r.name }))) {
          router.delete(`/admin/tools/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Tools") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "tools",
      title: __("Tools"),
      createHref: "/admin/tools/create",
      createLabel: __("+ New Tool"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No tools yet.")
    }
  ));
}
ToolsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ToolsIndex as default
};
