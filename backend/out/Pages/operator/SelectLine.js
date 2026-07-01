import { Head, useForm, usePage } from "@inertiajs/react";
import { __ } from "../../lib/i18n";
import { Button, Dropdown, StatusPill } from "@openmes/ui";
import OperatorLayout from "../../layouts/OperatorLayout";
function SelectLine() {
  const { lines = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Select Production Line") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-6xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("h1", { className: "text-[28px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Select Production Line")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-2" }, __("Choose a production line and optionally a workstation"))), lines.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om text-center py-12 px-6" }, /* @__PURE__ */ React.createElement("svg", { className: "mx-auto h-12 w-12 text-om-faintest", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" })), /* @__PURE__ */ React.createElement("h3", { className: "mt-2 text-sm font-medium text-om-ink" }, __("No lines assigned")), /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-sm text-om-muted" }, __("You are not assigned to any production lines. Please contact your administrator."))) : /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" }, lines.map((line) => /* @__PURE__ */ React.createElement(LineCard, { key: line.id, line })))));
}
function LineCard({ line }) {
  const form = useForm({ line_id: line.id, workstation_id: "" });
  const submit = (e) => {
    e.preventDefault();
    form.post("/operator/select-line");
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-6 transition-shadow hover:shadow-[0_16px_40px_-24px_rgba(26,25,23,0.4)]" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-semibold tracking-[-0.01em] text-om-ink" }, line.name), /* @__PURE__ */ React.createElement(StatusPill, { status: "running", label: __("Active") })), line.description && /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-4" }, line.description), /* @__PURE__ */ React.createElement("div", { className: "border-t border-om-line2 pt-4 mb-4" }, line.workstations.length > 0 ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("label", { className: "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-2" }, "Workstation ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faintest normal-case tracking-normal" }, "(optional)")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.data.workstation_id == null ? "" : String(form.data.workstation_id),
      onChange: (v) => form.setData("workstation_id", v),
      options: [
        { value: "", label: "All workstations" },
        ...line.workstations.map((ws) => ({ value: String(ws.id), label: `${ws.name}${ws.code ? ` (${ws.code})` : ""}` }))
      ],
      className: "w-full"
    }
  )) : /* @__PURE__ */ React.createElement("div", { className: "flex items-center text-sm text-om-faint" }, /* @__PURE__ */ React.createElement("svg", { className: "h-5 w-5 mr-2", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" })), /* @__PURE__ */ React.createElement("span", null, "No workstations"))), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "accent",
      disabled: form.processing,
      className: "w-full px-6 py-4 text-[15px]"
    },
    /* @__PURE__ */ React.createElement("span", null, __("Select")),
    /* @__PURE__ */ React.createElement("svg", { className: "h-5 w-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M9 5l7 7-7 7" }))
  )));
}
SelectLine.layout = (page) => /* @__PURE__ */ React.createElement(OperatorLayout, null, page);
export {
  SelectLine as default
};
