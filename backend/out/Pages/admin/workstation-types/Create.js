import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { WORKSTATION_TYPE_FIELDS } from "./fields";
import { __ } from "../../../lib/i18n";
function WorkstationTypeCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Workstation Type") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Workstation Type")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/workstation-types",
      method: "post",
      fields: WORKSTATION_TYPE_FIELDS,
      initial: { code: "", name: "", description: "", is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/workstation-types"
    }
  ));
}
WorkstationTypeCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkstationTypeCreate as default
};
