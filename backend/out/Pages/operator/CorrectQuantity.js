import { Head, Link, useForm, usePage } from "@inertiajs/react";
import OperatorLayout from "../../layouts/OperatorLayout";
function CorrectQuantity() {
  const { shiftEntry, workOrder } = usePage().props;
  const form = useForm({ quantity: String(Math.round(shiftEntry.quantity)) });
  const submit = (e) => {
    e.preventDefault();
    form.put(`/operator/shift-entry/${shiftEntry.id}/correct`);
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: "Correct Quantity" }), /* @__PURE__ */ React.createElement("div", { className: "max-w-md mx-auto mt-8" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 mb-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/operator/workstation", className: "text-om-muted hover:text-om-ink" }, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" }))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-xl font-bold text-om-ink" }, "Correct Quantity"), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, "Modify a previously reported production quantity."))), /* @__PURE__ */ React.createElement("div", { className: "space-y-3 mb-6" }, /* @__PURE__ */ React.createElement(Row, { label: "Order No" }, /* @__PURE__ */ React.createElement("span", { className: "font-bold font-mono" }, workOrder.order_no)), /* @__PURE__ */ React.createElement(Row, { label: "Product" }, /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, workOrder.product_name ?? "\u2014")), /* @__PURE__ */ React.createElement(Row, { label: "Shift" }, /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, shiftEntry.shift.name ?? shiftEntry.shift.code)), /* @__PURE__ */ React.createElement(Row, { label: "Production Date" }, /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, shiftEntry.production_date)), /* @__PURE__ */ React.createElement(Row, { label: "Current Quantity" }, /* @__PURE__ */ React.createElement("span", { className: "font-bold text-om-accent" }, Math.round(shiftEntry.quantity)))), /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "mb-5" }, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "New Quantity ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      min: "0",
      max: "99999999",
      step: "1",
      required: true,
      autoFocus: true,
      inputMode: "numeric",
      value: form.data.quantity,
      onChange: (e) => form.setData("quantity", e.target.value),
      className: "form-input w-full text-2xl font-bold text-center py-3 tabular-nums"
    }
  ), form.errors.quantity && /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked text-sm mt-1" }, form.errors.quantity)), /* @__PURE__ */ React.createElement("div", { className: "flex gap-3" }, /* @__PURE__ */ React.createElement(Link, { href: "/operator/workstation", className: "flex-1 py-3 text-base text-center rounded-om-sm bg-om-line2 hover:bg-om-line text-om-ink font-medium" }, "Cancel"), /* @__PURE__ */ React.createElement("button", { type: "submit", disabled: form.processing, className: "flex-1 bg-om-ink hover:bg-om-ink-hover text-om-on-ink font-bold rounded-om-sm py-3 text-base disabled:opacity-50" }, "Save Correction"))))));
}
function Row({ label, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "flex justify-between border-b border-om-line2 pb-2 text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, label), children);
}
CorrectQuantity.layout = (page) => /* @__PURE__ */ React.createElement(OperatorLayout, null, page);
export {
  CorrectQuantity as default
};
