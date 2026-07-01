import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { maintenanceScheduleFields } from "./fields";
function MaintenanceScheduleEdit() {
  const { schedule, preferred_time = "", next_due_at = "", ...lists } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${schedule.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "Edit Maintenance Schedule"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/maintenance-schedules/${schedule.id}`,
      method: "put",
      fields: maintenanceScheduleFields(lists),
      initial: {
        name: schedule.name ?? "",
        description: schedule.description ?? "",
        event_type: schedule.event_type ?? "planned",
        tool_id: schedule.tool_id != null ? String(schedule.tool_id) : "",
        line_id: schedule.line_id != null ? String(schedule.line_id) : "",
        workstation_id: schedule.workstation_id != null ? String(schedule.workstation_id) : "",
        assigned_to_id: schedule.assigned_to_id != null ? String(schedule.assigned_to_id) : "",
        cost_source_id: schedule.cost_source_id != null ? String(schedule.cost_source_id) : "",
        frequency: schedule.frequency ?? "monthly",
        interval_value: schedule.interval_value ?? 1,
        preferred_time,
        lead_time_days: schedule.lead_time_days ?? "",
        next_due_at,
        is_active: !!schedule.is_active
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/maintenance-schedules"
    }
  ));
}
MaintenanceScheduleEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaintenanceScheduleEdit as default
};
