import { Head } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { PRODUCT_TYPE_FIELDS } from "./fields";
function ProductTypeEdit({ productType, customFields = [] }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${productType.name}` }), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      title: __("Edit Product Type"),
      breadcrumbs: [
        { label: __("Dashboard"), href: "/admin/dashboard" },
        { label: __("Product Types"), href: "/admin/product-types" },
        { label: productType.name, href: `/admin/product-types/${productType.id}` },
        { label: __("Edit") }
      ],
      backHref: "/admin/product-types",
      action: `/admin/product-types/${productType.id}`,
      method: "put",
      fields: PRODUCT_TYPE_FIELDS,
      customFields,
      initial: {
        code: productType.code ?? "",
        name: productType.name ?? "",
        description: productType.description ?? "",
        unit_of_measure: productType.unit_of_measure ?? "pcs",
        is_active: !!productType.is_active,
        custom_fields: productType.custom_fields ?? {}
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/product-types"
    }
  ));
}
ProductTypeEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProductTypeEdit as default
};
