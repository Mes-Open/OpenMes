import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { woFields } from "../../admin/work-orders/fields";
function SupervisorWorkOrderCreate() {
  const { lines = [], productTypes = [], customFields = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Work Order" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Work Order"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/supervisor/work-orders",
      method: "post",
      fields: woFields(lines, productTypes),
      customFields,
      initial: { order_no: "", line_id: "", product_type_id: "", planned_qty: "", priority: 0, due_date: "", description: "", custom_fields: {} },
      submitLabel: "Create",
      cancelHref: "/supervisor/work-orders"
    }
  ));
}
SupervisorWorkOrderCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SupervisorWorkOrderCreate as default
};
