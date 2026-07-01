import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function ProcessSegmentsIndex() {
  const { workstationTypeNames = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "segment_type", label: __("Type"), className: "text-om-muted", render: (r) => r.segment_type },
    { key: "wstype", label: __("Workstation Type"), className: "text-om-muted", render: (r) => workstationTypeNames[r.workstation_type_id] ?? "\u2014" },
    { key: "required_operators", label: __("Operators"), className: "text-om-muted" },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/process-segments/${r.id}/edit` },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete process segment ":name"?', { name: r.name }))) {
          router.delete(`/admin/process-segments/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Process Segments") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "process_segments",
      title: __("Process Segments"),
      createHref: "/admin/process-segments/create",
      createLabel: __("+ New Segment"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No process segments yet.")
    }
  ));
}
ProcessSegmentsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProcessSegmentsIndex as default
};
