import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { divisionFields } from "./fields";
import { __ } from "../../../lib/i18n";
function DivisionCreate() {
  const { factories = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Division") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Division")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/divisions",
      method: "post",
      fields: divisionFields(factories),
      initial: { factory_id: "", code: "", name: "", description: "", is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/divisions"
    }
  ));
}
DivisionCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  DivisionCreate as default
};
