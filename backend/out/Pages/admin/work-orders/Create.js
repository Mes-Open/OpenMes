import { Head, usePage } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { woFields } from "./fields";
function WorkOrderCreate() {
  const { lines = [], productTypes = [], customFields = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Work Order") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Work Order")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/work-orders",
      method: "post",
      fields: woFields(lines, productTypes),
      customFields,
      initial: { order_no: "", customer_order_no: "", line_id: "", product_type_id: "", planned_qty: "", priority: 0, due_date: "", description: "", custom_fields: {} },
      submitLabel: "Create",
      cancelHref: "/admin/work-orders"
    }
  ));
}
WorkOrderCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkOrderCreate as default
};
