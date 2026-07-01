import { Link, useForm, usePage } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
function PalletForm({ action, method, initial, submitLabel }) {
  const { workOrders = [], statuses = [] } = usePage().props;
  const form = useForm(initial);
  const { data, setData, errors, processing } = form;
  const selectedWo = workOrders.find((wo) => String(wo.id) === String(data.work_order_id));
  const batches = selectedWo?.batches ?? [];
  const submit = (e) => {
    e.preventDefault();
    form.submit(method, action);
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Work order ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.work_order_id == null ? "" : String(data.work_order_id),
      onChange: (v) => {
        setData("work_order_id", v);
        setData("batch_id", "");
      },
      placeholder: "\u2014 Select work order \u2014",
      options: workOrders.map((wo) => ({ value: String(wo.id), label: wo.order_no })),
      className: "w-full"
    }
  ), errors.work_order_id && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.work_order_id)), batches.length > 0 && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Batch"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.batch_id == null ? "" : String(data.batch_id),
      onChange: (v) => setData("batch_id", v),
      placeholder: "\u2014 None \u2014",
      options: batches.map((b) => ({ value: String(b.id), label: b.label })),
      className: "w-full"
    }
  ), errors.batch_id && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.batch_id)), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Quantity"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      min: 0,
      value: data.qty ?? 0,
      onChange: (e) => setData("qty", e.target.value),
      className: "form-input w-full"
    }
  ), errors.qty && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.qty)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Status ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.status == null ? "" : String(data.status),
      onChange: (v) => setData("status", v),
      options: statuses.map((s) => ({ value: String(s.value), label: s.label })),
      className: "w-full"
    }
  ), errors.status && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.status))), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: "Location",
      value: data.location,
      error: errors.location,
      onChange: (v) => setData("location", v)
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: "ERP reference",
      value: data.erp_reference,
      error: errors.erp_reference,
      onChange: (v) => setData("erp_reference", v)
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "submit",
      disabled: processing,
      className: "bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover disabled:opacity-50"
    },
    processing ? "Saving\u2026" : submitLabel
  ), /* @__PURE__ */ React.createElement(Link, { href: "/admin/pallets", className: "text-om-muted hover:text-om-ink text-sm" }, "Cancel")));
}
function TextField({ label, value, error, onChange }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: value ?? "",
      onChange: (e) => onChange(e.target.value),
      className: "form-input w-full"
    }
  ), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
export {
  PalletForm as default
};
