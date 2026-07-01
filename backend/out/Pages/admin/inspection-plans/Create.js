import { Head, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import InspectionPlanForm from "./Form";
function InspectionPlanCreate() {
  const { materials = [], materialTypes = [] } = usePage().props;
  const form = useForm({
    name: "",
    description: "",
    scope: "generic",
    material_id: "",
    material_type_id: "",
    criteria: [{ name: "", type: "visual", required: true, unit: "", spec_min: "", spec_max: "" }],
    is_active: true
  });
  const submit = (e) => {
    e.preventDefault();
    form.post("/admin/inspection-plans");
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Inspection Plan" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Inspection Plan"), /* @__PURE__ */ React.createElement(InspectionPlanForm, { form, materials, materialTypes, submitLabel: "Create", onSubmit: submit }));
}
InspectionPlanCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  InspectionPlanCreate as default
};
