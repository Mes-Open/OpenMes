import { Head, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ProcessSegmentForm from "./Form";
function ProcessSegmentCreate() {
  const { workstationTypes = [], skills = [], segmentTypes = [] } = usePage().props;
  const form = useForm({
    code: "",
    name: "",
    description: "",
    segment_type: "production",
    workstation_type_id: "",
    estimated_duration_minutes: "",
    required_operators: 1,
    standard_instruction: "",
    required_skill_ids: [],
    parameters_raw: ""
  });
  const submit = (e) => {
    e.preventDefault();
    form.post("/admin/process-segments");
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Process Segment" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Process Segment"), /* @__PURE__ */ React.createElement(
    ProcessSegmentForm,
    {
      form,
      workstationTypes,
      skills,
      segmentTypes,
      submitLabel: "Create",
      onSubmit: submit
    }
  ));
}
ProcessSegmentCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProcessSegmentCreate as default
};
