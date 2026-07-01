import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { COST_SOURCE_FIELDS } from "./fields";
function CostSourceEdit({ costSource }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${costSource.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "Edit Cost Source"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/cost-sources/${costSource.id}`,
      method: "put",
      fields: COST_SOURCE_FIELDS,
      initial: {
        code: costSource.code ?? "",
        name: costSource.name ?? "",
        description: costSource.description ?? "",
        unit_cost: costSource.unit_cost ?? "",
        unit: costSource.unit ?? "",
        currency: costSource.currency ?? "",
        is_active: !!costSource.is_active
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/cost-sources"
    }
  ));
}
CostSourceEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CostSourceEdit as default
};
