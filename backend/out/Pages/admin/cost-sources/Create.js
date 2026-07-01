import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { COST_SOURCE_FIELDS } from "./fields";
function CostSourceCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Cost Source" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Cost Source"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/cost-sources",
      method: "post",
      fields: COST_SOURCE_FIELDS,
      initial: { code: "", name: "", description: "", unit_cost: "", unit: "", currency: "", is_active: true },
      submitLabel: "Create",
      cancelHref: "/admin/cost-sources"
    }
  ));
}
CostSourceCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CostSourceCreate as default
};
