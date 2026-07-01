import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import { default as ResourceTable } from "../../../components/ResourceTable";
import { EVENT_STATUS_STYLES } from "./fields";
import { __ } from "../../../lib/i18n";
function MaintenanceEventsIndex() {
  const {
    toolNames = {},
    lineNames = {},
    workstationNames = {},
    userNames = {}
  } = usePage().props;
  const columns = [
    { key: "title", label: __("Title"), className: "font-medium text-om-ink" },
    { key: "event_type", label: __("Type"), className: "text-om-muted" },
    {
      key: "target",
      label: __("Target"),
      className: "text-om-muted",
      render: (r) => toolNames[r.tool_id] ?? lineNames[r.line_id] ?? workstationNames[r.workstation_id] ?? "\u2014"
    },
    {
      key: "assigned",
      label: __("Assigned"),
      className: "text-om-muted",
      render: (r) => userNames[r.assigned_to_id] ?? "\u2014"
    },
    {
      key: "scheduled_at",
      label: __("Scheduled"),
      className: "text-om-muted",
      render: (r) => r.scheduled_at ? r.scheduled_at.slice(0, 16).replace("T", " ") : "\u2014"
    },
    {
      key: "status",
      label: __("Status"),
      render: (r) => /* @__PURE__ */ React.createElement(
        "span",
        {
          className: `text-xs px-2 py-0.5 rounded font-medium ${EVENT_STATUS_STYLES[r.status] ?? "bg-om-chip text-om-muted"}`
        },
        {
          pending: __("Pending"),
          planned: __("Planned"),
          in_progress: __("In Progress"),
          completed: __("Completed"),
          done: __("Completed"),
          cancelled: __("Cancelled")
        }[r.status] ?? r.status
      )
    }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/maintenance-events/${r.id}/edit` },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete maintenance event ":name"?', { name: r.title }))) {
          router.delete(`/admin/maintenance-events/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Maintenance Events") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "maintenance_events",
      title: __("Maintenance Events"),
      createHref: "/admin/maintenance-events/create",
      createLabel: __("+ New Event"),
      columns,
      orderBy: "scheduled_at",
      orderDir: "desc",
      actions,
      emptyText: __("No maintenance events yet.")
    }
  ));
}
MaintenanceEventsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaintenanceEventsIndex as default
};
