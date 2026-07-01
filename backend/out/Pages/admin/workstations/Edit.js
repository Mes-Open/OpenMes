import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Button, Checkbox } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import CustomFields from "../../../components/CustomFields";
import { customFieldInitial, customFieldProps, submitForm } from "../../../lib/customFieldForm";
import { __ } from "../../../lib/i18n";
function WorkstationEdit() {
  const { line, workstation, workers = [], customFields = [] } = usePage().props;
  const assignedWorkerIds = workers.filter((w) => w.workstation_id === workstation.id).map((w) => w.id);
  const form = useForm({
    code: workstation.code ?? "",
    name: workstation.name ?? "",
    workstation_type: workstation.workstation_type ?? "",
    is_active: !!workstation.is_active,
    worker_ids: assignedWorkerIds,
    ...customFieldInitial(workstation.custom_fields)
  });
  const submit = (e) => {
    e.preventDefault();
    submitForm(form, "put", `/admin/lines/${line.id}/workstations/${workstation.id}`);
  };
  const toggleWorker = (workerId) => {
    const current = form.data.worker_ids;
    const next = current.includes(workerId) ? current.filter((id) => id !== workerId) : [...current, workerId];
    form.setData("worker_ids", next);
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-2xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit Workstation: :name", { name: workstation.name }) }), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/workstations`,
      className: "text-om-accent hover:text-om-accent flex items-center gap-2 mb-4 text-sm"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    __("Back to Workstations")
  ), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Edit Workstation")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, line.name)), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "bg-om-card rounded-om-sm shadow-sm p-6 space-y-5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Workstation Code"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.code,
      onChange: (e) => form.setData("code", e.target.value),
      placeholder: __("e.g., WS-A01, ASSEMBLY-1"),
      className: "form-input w-full",
      required: true,
      autoFocus: true
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Unique identifier for this workstation")), form.errors.code && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, form.errors.code)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Workstation Name"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.name,
      onChange: (e) => form.setData("name", e.target.value),
      placeholder: __("e.g., Assembly Station 1, Quality Check Point"),
      className: "form-input w-full",
      required: true
    }
  ), form.errors.name && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, form.errors.name)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Workstation Type")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.workstation_type,
      onChange: (e) => form.setData("workstation_type", e.target.value),
      placeholder: __("e.g., Assembly, Quality Control, Packaging (optional)"),
      className: "form-input w-full"
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Optional classification for this workstation")), form.errors.workstation_type && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, form.errors.workstation_type)), /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: form.data.is_active,
      onChange: (next) => form.setData("is_active", next),
      label: __("Active (workstation is ready for use)")
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "border-t border-om-line2 pt-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-base font-semibold text-om-ink mb-1" }, __("Assigned Workers")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-3" }, __("Workers regularly operating at this workstation.")), workers.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint italic" }, __("No active workers in the system.")) : /* @__PURE__ */ React.createElement("div", { className: "divide-y divide-om-line2 border border-om-line2 rounded-om-sm overflow-hidden" }, workers.map((worker) => {
    const isAssigned = form.data.worker_ids.includes(worker.id);
    return /* @__PURE__ */ React.createElement(
      "label",
      {
        key: worker.id,
        className: `flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-om-bg ${isAssigned ? "bg-om-chip" : ""}`
      },
      /* @__PURE__ */ React.createElement(
        Checkbox,
        {
          checked: isAssigned,
          onChange: () => toggleWorker(worker.id)
        }
      ),
      /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0" }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-medium text-om-ink" }, worker.name), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint font-mono ml-2" }, worker.code), worker.workstation_id && !isAssigned && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-orange-500 ml-2" }, __("(currently at: :station)", { station: worker.workstation_name ?? "\u2026" }))),
      worker.crew_name && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint shrink-0" }, worker.crew_name)
    );
  })), form.errors.worker_ids && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, form.errors.worker_ids)), customFields.length > 0 && /* @__PURE__ */ React.createElement(CustomFields, { ...customFieldProps(form, customFields) }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: form.processing }, form.processing ? __("Saving\u2026") : __("Update Workstation")), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/workstations`,
      className: "text-om-muted hover:text-om-ink text-sm"
    },
    __("Cancel")
  ))));
}
WorkstationEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkstationEdit as default
};
