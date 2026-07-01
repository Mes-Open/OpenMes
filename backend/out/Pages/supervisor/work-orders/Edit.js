import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { woFields } from "../../admin/work-orders/fields";
function SupervisorWorkOrderEdit() {
  const { workOrder, lines = [], productTypes = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${workOrder.order_no}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "Edit Work Order"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/supervisor/work-orders/${workOrder.id}`,
      method: "put",
      fields: woFields(lines, productTypes, { withStatus: true }),
      initial: {
        order_no: workOrder.order_no ?? "",
        customer_order_no: workOrder.customer_order_no ?? "",
        line_id: workOrder.line_id != null ? String(workOrder.line_id) : "",
        product_type_id: workOrder.product_type_id != null ? String(workOrder.product_type_id) : "",
        planned_qty: workOrder.planned_qty ?? "",
        priority: workOrder.priority ?? 0,
        due_date: workOrder.due_date ?? "",
        description: workOrder.description ?? "",
        status: workOrder.status ?? "PENDING"
      },
      submitLabel: "Save Changes",
      cancelHref: "/supervisor/work-orders"
    }
  ));
}
SupervisorWorkOrderEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SupervisorWorkOrderEdit as default
};
