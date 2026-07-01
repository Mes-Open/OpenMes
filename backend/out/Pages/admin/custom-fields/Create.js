import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import DefinitionForm from "../../../components/DefinitionForm";
import { __ } from "../../../lib/i18n";
function CustomFieldCreate() {
  const { entities = [], types = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Custom Field") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Custom Field")), /* @__PURE__ */ React.createElement(
    DefinitionForm,
    {
      action: "/admin/custom-fields",
      method: "post",
      entities,
      types,
      initial: {
        entity_type: "",
        key: "",
        label: "",
        type: "",
        required: false,
        is_active: true,
        position: 0,
        config: { options: [] }
      },
      submitLabel: __("Create")
    }
  ));
}
CustomFieldCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CustomFieldCreate as default
};
