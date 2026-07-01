import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { COMPANY_FIELDS } from "./fields";
function CompanyEdit({ company }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${company.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "Edit Company"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/companies/${company.id}`,
      method: "put",
      fields: COMPANY_FIELDS,
      initial: {
        code: company.code ?? "",
        name: company.name ?? "",
        tax_id: company.tax_id ?? "",
        type: company.type ?? "supplier",
        email: company.email ?? "",
        phone: company.phone ?? "",
        address: company.address ?? "",
        is_active: !!company.is_active
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/companies"
    }
  ));
}
CompanyEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CompanyEdit as default
};
