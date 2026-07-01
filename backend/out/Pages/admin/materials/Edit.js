import { Head, usePage } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { materialFields } from "./fields";
function MaterialEdit() {
  const { material, materialTypes = [], customFields = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${material.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Material")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/materials/${material.id}`,
      method: "put",
      fields: materialFields(materialTypes),
      customFields,
      initial: {
        code: material.code ?? "",
        name: material.name ?? "",
        material_type_id: material.material_type_id != null ? String(material.material_type_id) : "",
        unit_of_measure: material.unit_of_measure ?? "",
        tracking_type: material.tracking_type ?? "none",
        default_scrap_percentage: material.default_scrap_percentage ?? "",
        description: material.description ?? "",
        external_code: material.external_code ?? "",
        external_system: material.external_system ?? "",
        is_active: !!material.is_active,
        custom_fields: material.custom_fields ?? {}
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/materials"
    }
  ));
}
MaterialEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaterialEdit as default
};
