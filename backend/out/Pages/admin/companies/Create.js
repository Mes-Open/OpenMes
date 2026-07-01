import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { COMPANY_FIELDS } from "./fields";
function CompanyCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Company" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Company"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/companies",
      method: "post",
      fields: COMPANY_FIELDS,
      initial: { code: "", name: "", tax_id: "", type: "supplier", email: "", phone: "", address: "", is_active: true },
      submitLabel: "Create",
      cancelHref: "/admin/companies"
    }
  ));
}
CompanyCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CompanyCreate as default
};
