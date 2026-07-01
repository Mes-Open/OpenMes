import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { FACTORY_FIELDS } from "./fields";
import { __ } from "../../../lib/i18n";
function FactoryCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Factory") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Factory")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/factories",
      method: "post",
      fields: FACTORY_FIELDS,
      initial: { code: "", name: "", description: "", is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/factories"
    }
  ));
}
FactoryCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  FactoryCreate as default
};
