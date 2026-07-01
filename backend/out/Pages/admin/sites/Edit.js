import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { siteFields } from "./fields";
import { __ } from "../../../lib/i18n";
function SiteEdit() {
  const { site, companies = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :name", { name: site.name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Site")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/sites/${site.id}`,
      method: "put",
      fields: siteFields(companies),
      initial: {
        company_id: site.company_id != null ? String(site.company_id) : "",
        code: site.code ?? "",
        name: site.name ?? "",
        description: site.description ?? "",
        address: site.address ?? "",
        city: site.city ?? "",
        country: site.country ?? "",
        timezone: site.timezone ?? "",
        is_active: !!site.is_active,
        custom_fields: site.custom_fields ?? {}
      },
      submitLabel: __("Save Changes"),
      cancelHref: "/admin/sites"
    }
  ));
}
SiteEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SiteEdit as default
};
