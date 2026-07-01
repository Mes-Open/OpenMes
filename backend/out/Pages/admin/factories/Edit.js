import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { FACTORY_FIELDS } from "./fields";
import { __ } from "../../../lib/i18n";
function FactoryEdit({ factory }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :name", { name: factory.name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Factory")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/factories/${factory.id}`,
      method: "put",
      fields: FACTORY_FIELDS,
      initial: {
        code: factory.code ?? "",
        name: factory.name ?? "",
        description: factory.description ?? "",
        is_active: !!factory.is_active
      },
      submitLabel: __("Save Changes"),
      cancelHref: "/admin/factories"
    }
  ));
}
FactoryEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  FactoryEdit as default
};
