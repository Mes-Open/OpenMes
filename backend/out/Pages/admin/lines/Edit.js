import { Head, usePage } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { lineFields } from "./fields";
function LineEdit() {
  const { line, areas = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${line.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Production Line")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/lines/${line.id}`,
      method: "put",
      fields: lineFields(areas),
      initial: {
        code: line.code ?? "",
        name: line.name ?? "",
        area_id: line.area_id != null ? String(line.area_id) : "",
        description: line.description ?? "",
        is_active: !!line.is_active,
        custom_fields: line.custom_fields ?? {}
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/lines"
    }
  ));
}
LineEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  LineEdit as default
};
