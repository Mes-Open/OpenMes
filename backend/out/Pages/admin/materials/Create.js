import { Head, usePage } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { materialFields } from "./fields";
function MaterialCreate() {
  const { materialTypes = [], customFields = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Material") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Material")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/materials",
      method: "post",
      fields: materialFields(materialTypes),
      customFields,
      initial: {
        code: "",
        name: "",
        material_type_id: "",
        unit_of_measure: "pcs",
        tracking_type: "none",
        default_scrap_percentage: "",
        description: "",
        external_code: "",
        external_system: "",
        is_active: true,
        custom_fields: {}
      },
      submitLabel: "Create",
      cancelHref: "/admin/materials"
    }
  ));
}
MaterialCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaterialCreate as default
};
