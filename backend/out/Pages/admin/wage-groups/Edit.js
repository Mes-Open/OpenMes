import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { wageGroupFields } from "./fields";
import { __ } from "../../../lib/i18n";
function WageGroupEdit({ wageGroup }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :name", { name: wageGroup.name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Wage Group")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/wage-groups/${wageGroup.id}`,
      method: "put",
      fields: wageGroupFields(),
      initial: {
        code: wageGroup.code ?? "",
        name: wageGroup.name ?? "",
        description: wageGroup.description ?? "",
        base_hourly_rate: wageGroup.base_hourly_rate ?? "",
        currency: wageGroup.currency ?? "",
        is_active: !!wageGroup.is_active
      },
      submitLabel: __("Save Changes"),
      cancelHref: "/admin/wage-groups"
    }
  ));
}
WageGroupEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WageGroupEdit as default
};
