import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function MaintenanceSchedulesIndex() {
  const { toolNames = {}, lineNames = {}, workstationNames = {} } = usePage().props;
  const columns = [
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    {
      key: "target",
      label: __("Target"),
      className: "text-om-muted",
      render: (r) => toolNames[r.tool_id] ?? lineNames[r.line_id] ?? workstationNames[r.workstation_id] ?? "\u2014"
    },
    { key: "frequency", label: __("Frequency"), className: "text-om-muted" },
    { key: "interval_value", label: __("Every"), className: "text-om-muted" },
    {
      key: "next_due_at",
      label: __("Next Due"),
      className: "text-om-muted",
      render: (r) => r.next_due_at ? r.next_due_at.slice(0, 16).replace("T", " ") : "\u2014"
    },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/maintenance-schedules/${r.id}/edit` },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete maintenance schedule ":name"?', { name: r.name }))) {
          router.delete(`/admin/maintenance-schedules/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Maintenance Schedules") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "maintenance_schedules",
      title: __("Maintenance Schedules"),
      createHref: "/admin/maintenance-schedules/create",
      createLabel: __("+ New Schedule"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No maintenance schedules yet.")
    }
  ));
}
MaintenanceSchedulesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaintenanceSchedulesIndex as default
};
