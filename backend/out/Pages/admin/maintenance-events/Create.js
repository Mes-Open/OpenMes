import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { maintenanceEventFields } from "./fields";
import { __ } from "../../../lib/i18n";
function MaintenanceEventCreate() {
  const lists = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Maintenance Event") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Maintenance Event")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-4 max-w-2xl" }, __("Select at least one of Tool, Line, or Workstation.")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/maintenance-events",
      method: "post",
      fields: maintenanceEventFields(lists),
      initial: {
        title: "",
        event_type: "planned",
        tool_id: "",
        line_id: "",
        workstation_id: "",
        cost_source_id: "",
        assigned_to_id: "",
        scheduled_at: "",
        scheduled_end_at: "",
        actual_cost: "",
        currency: "PLN",
        description: ""
      },
      submitLabel: "Create",
      cancelHref: "/admin/maintenance-events"
    }
  ));
}
MaintenanceEventCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaintenanceEventCreate as default
};
