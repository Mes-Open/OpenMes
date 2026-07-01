import { Head } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { PRODUCT_TYPE_FIELDS } from "./fields";
function ProductTypeCreate({ customFields = [] }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Product Type") }), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      title: __("New Product Type"),
      breadcrumbs: [
        { label: __("Dashboard"), href: "/admin/dashboard" },
        { label: __("Product Types"), href: "/admin/product-types" },
        { label: __("New") }
      ],
      backHref: "/admin/product-types",
      action: "/admin/product-types",
      method: "post",
      fields: PRODUCT_TYPE_FIELDS,
      customFields,
      initial: { code: "", name: "", description: "", unit_of_measure: "pcs", is_active: true, custom_fields: {} },
      submitLabel: "Create",
      cancelHref: "/admin/product-types"
    }
  ));
}
ProductTypeCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProductTypeCreate as default
};
