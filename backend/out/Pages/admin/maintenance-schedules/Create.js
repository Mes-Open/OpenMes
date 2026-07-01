import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { maintenanceScheduleFields } from "./fields";
function MaintenanceScheduleCreate() {
  const lists = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Maintenance Schedule" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Maintenance Schedule"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/maintenance-schedules",
      method: "post",
      fields: maintenanceScheduleFields(lists),
      initial: {
        name: "",
        description: "",
        event_type: "planned",
        tool_id: "",
        line_id: "",
        workstation_id: "",
        assigned_to_id: "",
        cost_source_id: "",
        frequency: "monthly",
        interval_value: 1,
        preferred_time: "",
        lead_time_days: "",
        next_due_at: "",
        is_active: true
      },
      submitLabel: "Create",
      cancelHref: "/admin/maintenance-schedules"
    }
  ));
}
MaintenanceScheduleCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaintenanceScheduleCreate as default
};
