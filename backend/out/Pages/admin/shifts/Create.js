import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { shiftFields } from "./fields";
function ShiftCreate() {
  const { lines = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Shift" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Shift"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/shifts",
      method: "post",
      fields: shiftFields(lines),
      initial: { code: "", name: "", line_id: "", start_time: "", end_time: "", sort_order: 0, is_active: true },
      submitLabel: "Create",
      cancelHref: "/admin/shifts"
    }
  ));
}
ShiftCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ShiftCreate as default
};
