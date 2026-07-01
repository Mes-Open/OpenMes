import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { subassemblyFields } from "./fields";
import { __ } from "../../../lib/i18n";
function SubassemblyCreate() {
  const { productTypes = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Subassembly") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Subassembly")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/subassemblies",
      method: "post",
      fields: subassemblyFields(productTypes),
      initial: { product_type_id: "", code: "", name: "", description: "", is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/subassemblies"
    }
  ));
}
SubassemblyCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SubassemblyCreate as default
};
