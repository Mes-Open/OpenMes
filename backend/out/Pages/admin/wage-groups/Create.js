import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { wageGroupFields } from "./fields";
import { __ } from "../../../lib/i18n";
function WageGroupCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Wage Group") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Wage Group")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/wage-groups",
      method: "post",
      fields: wageGroupFields(),
      initial: { code: "", name: "", description: "", base_hourly_rate: "", currency: "", is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/wage-groups"
    }
  ));
}
WageGroupCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WageGroupCreate as default
};
