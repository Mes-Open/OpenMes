import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { shiftFields } from "./fields";
function ShiftEdit() {
  const { shift, lines = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${shift.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "Edit Shift"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/shifts/${shift.id}`,
      method: "put",
      fields: shiftFields(lines),
      initial: {
        code: shift.code ?? "",
        name: shift.name ?? "",
        line_id: shift.line_id != null ? String(shift.line_id) : "",
        start_time: (shift.start_time ?? "").slice(0, 5),
        end_time: (shift.end_time ?? "").slice(0, 5),
        sort_order: shift.sort_order ?? 0,
        is_active: !!shift.is_active,
        custom_fields: shift.custom_fields ?? {}
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/shifts"
    }
  ));
}
ShiftEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ShiftEdit as default
};
