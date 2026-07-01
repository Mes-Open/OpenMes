import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import DefinitionForm from "../../../components/DefinitionForm";
import { __ } from "../../../lib/i18n";
function CustomFieldEdit() {
  const { definition, entities = [], types = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :label", { label: definition.label }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Custom Field")), /* @__PURE__ */ React.createElement(
    DefinitionForm,
    {
      action: `/admin/custom-fields/${definition.id}`,
      method: "put",
      entities,
      types,
      initial: {
        entity_type: definition.entity_type ?? "",
        key: definition.key ?? "",
        label: definition.label ?? "",
        type: definition.type ?? "",
        required: !!definition.required,
        is_active: !!definition.is_active,
        position: definition.position ?? 0,
        config: {
          options: definition.config?.options ?? [],
          min: definition.config?.min ?? "",
          max: definition.config?.max ?? ""
        }
      },
      submitLabel: __("Save Changes")
    }
  ));
}
CustomFieldEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CustomFieldEdit as default
};
