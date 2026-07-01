import { Head, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ProcessSegmentForm from "./Form";
function ProcessSegmentEdit() {
  const { segment, parameters_raw = "", workstationTypes = [], skills = [], segmentTypes = [] } = usePage().props;
  const form = useForm({
    code: segment.code ?? "",
    name: segment.name ?? "",
    description: segment.description ?? "",
    segment_type: segment.segment_type ?? "production",
    workstation_type_id: segment.workstation_type_id != null ? String(segment.workstation_type_id) : "",
    estimated_duration_minutes: segment.estimated_duration_minutes ?? "",
    required_operators: segment.required_operators ?? 1,
    standard_instruction: segment.standard_instruction ?? "",
    required_skill_ids: segment.required_skill_ids ?? [],
    parameters_raw
  });
  const submit = (e) => {
    e.preventDefault();
    form.put(`/admin/process-segments/${segment.id}`);
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${segment.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "Edit Process Segment"), /* @__PURE__ */ React.createElement(
    ProcessSegmentForm,
    {
      form,
      workstationTypes,
      skills,
      segmentTypes,
      submitLabel: "Save Changes",
      onSubmit: submit
    }
  ));
}
ProcessSegmentEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProcessSegmentEdit as default
};
