import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { areaFields } from "./fields";
import { __ } from "../../../lib/i18n";
function AreaEdit() {
  const { area, sites = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :name", { name: area.name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Area")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/areas/${area.id}`,
      method: "put",
      fields: areaFields(sites),
      initial: {
        site_id: area.site_id != null ? String(area.site_id) : "",
        code: area.code ?? "",
        name: area.name ?? "",
        description: area.description ?? "",
        is_active: !!area.is_active,
        custom_fields: area.custom_fields ?? {}
      },
      submitLabel: __("Save Changes"),
      cancelHref: "/admin/areas"
    }
  ));
}
AreaEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  AreaEdit as default
};
