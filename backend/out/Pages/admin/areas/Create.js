import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { areaFields } from "./fields";
import { __ } from "../../../lib/i18n";
function AreaCreate() {
  const { sites = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Area") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Area")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/areas",
      method: "post",
      fields: areaFields(sites),
      initial: { site_id: "", code: "", name: "", description: "", is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/areas"
    }
  ));
}
AreaCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  AreaCreate as default
};
