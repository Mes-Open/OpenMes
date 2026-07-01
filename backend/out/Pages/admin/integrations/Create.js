import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { INTEGRATION_FIELDS } from "./fields";
import { __ } from "../../../lib/i18n";
function IntegrationCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Integration") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Integration")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/integrations",
      method: "post",
      fields: INTEGRATION_FIELDS,
      initial: { system_type: "", system_name: "", is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/integrations"
    }
  ));
}
IntegrationCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  IntegrationCreate as default
};
