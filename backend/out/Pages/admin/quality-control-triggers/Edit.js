import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { triggerFields } from "./fields";
import { __ } from "../../../lib/i18n";
const str = (v) => v === null || v === void 0 ? "" : String(v);
function QualityControlTriggerEdit() {
  const { trigger, templates, lines, workstations, productTypes } = usePage().props;
  const initial = {
    name: trigger.name ?? "",
    trigger_type: trigger.trigger_type,
    quality_check_template_id: str(trigger.quality_check_template_id),
    threshold_n: str(trigger.threshold_n),
    downtime_min_minutes: str(trigger.downtime_min_minutes),
    line_id: str(trigger.line_id),
    workstation_id: str(trigger.workstation_id),
    product_type_id: str(trigger.product_type_id),
    is_blocking: !!trigger.is_blocking,
    is_active: !!trigger.is_active
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit Quality Control Trigger") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Quality Control Trigger")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/quality-control-triggers/${trigger.id}`,
      method: "put",
      fields: triggerFields({ templates, lines, workstations, productTypes }),
      initial,
      submitLabel: __("Save"),
      cancelHref: "/admin/quality-control-triggers"
    }
  ));
}
QualityControlTriggerEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  QualityControlTriggerEdit as default
};
