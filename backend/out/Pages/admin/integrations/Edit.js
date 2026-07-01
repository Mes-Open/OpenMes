import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { INTEGRATION_FIELDS } from "./fields";
import { __ } from "../../../lib/i18n";
function IntegrationEdit({ integration }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :name", { name: integration.system_name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Integration")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/integrations/${integration.id}`,
      method: "put",
      fields: INTEGRATION_FIELDS,
      initial: {
        system_type: integration.system_type ?? "",
        system_name: integration.system_name ?? "",
        is_active: !!integration.is_active
      },
      submitLabel: __("Save Changes"),
      cancelHref: "/admin/integrations"
    }
  ));
}
IntegrationEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  IntegrationEdit as default
};
