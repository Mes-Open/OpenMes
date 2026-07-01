import { Fragment, useEffect } from "react";
import { Link, useForm, usePage } from "@inertiajs/react";
import { Button, Checkbox, DatePicker, Dropdown } from "@openmes/ui";
import CustomFields from "./CustomFields";
import { customFieldProps, submitForm } from "../lib/customFieldForm";
import { __ } from "../lib/i18n";
const LABEL_CLASS = "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]";
const INPUT_CLASS = "w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]";
function ResourceForm({
  action,
  method = "post",
  fields,
  initial,
  submitLabel = "Save",
  cancelHref,
  breadcrumbs,
  backHref,
  backLabel = "Back",
  title,
  customFields
}) {
  const form = useForm(initial);
  const { data, setData, errors, processing } = form;
  useEffect(() => {
    const keys = Object.keys(errors);
    if (keys.length === 0) return;
    const el = document.querySelector(`[name="${keys[0]}"]`);
    if (el) {
      el.scrollIntoView({ behavior: "smooth", block: "center" });
      el.focus({ preventScroll: true });
    }
  }, [errors]);
  const pageCustomFields = usePage().props.customFields;
  const customFieldDefs = customFields ?? pageCustomFields ?? [];
  const submit = (e) => {
    e.preventDefault();
    submitForm(form, method, action);
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, breadcrumbs?.length > 0 && /* @__PURE__ */ React.createElement("nav", { className: "text-[13px] text-om-muted mb-4 flex items-center gap-1" }, breadcrumbs.map((b, i) => /* @__PURE__ */ React.createElement(Fragment, { key: i }, i > 0 && /* @__PURE__ */ React.createElement("span", null, "/"), b.href ? /* @__PURE__ */ React.createElement(Link, { href: b.href, className: "hover:underline hover:text-om-ink" }, b.label) : /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, b.label)))), backHref && /* @__PURE__ */ React.createElement(Link, { href: backHref, className: "text-[13px] text-om-muted hover:text-om-ink flex items-center gap-2 mb-4 transition-colors" }, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M15 19l-7-7 7-7" })), __(backLabel)), title && /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-6" }, title), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "bg-om-card border border-om-line rounded-om p-6 max-w-2xl space-y-5" }, Object.keys(errors).length > 0 && /* @__PURE__ */ React.createElement("div", { className: "bg-om-blocked-bg border border-om-blocked/20 rounded-om-sm p-3" }, /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] font-semibold text-om-blocked" }, __("Please fix the following:")), /* @__PURE__ */ React.createElement("ul", { className: "mt-1 text-[11.5px] text-om-blocked list-disc list-inside" }, Object.entries(errors).map(([field, msg]) => /* @__PURE__ */ React.createElement("li", { key: field }, fields.find((f) => f.name === field)?.label ?? field, ": ", msg)))), fields.map((f) => /* @__PURE__ */ React.createElement(Field, { key: f.name, field: f, value: data[f.name], error: errors[f.name], setData })), customFieldDefs.length > 0 && /* @__PURE__ */ React.createElement(CustomFields, { ...customFieldProps(form, customFieldDefs) }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing }, processing ? __("Saving\u2026") : submitLabel), cancelHref && /* @__PURE__ */ React.createElement(
    Link,
    {
      href: cancelHref,
      className: "inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
    },
    __("Cancel")
  ))));
}
function Field({ field, value, error, setData }) {
  const { name, label, type = "text", required, placeholder, help, options } = field;
  const set = (v) => setData(name, v);
  if (type === "checkbox") {
    return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(Checkbox, { checked: !!value, onChange: (next) => set(next), label: __(label) }), help && /* @__PURE__ */ React.createElement("p", { className: "text-[12px] text-om-muted mt-1" }, __(help)));
  }
  if (type === "checkbox-group") {
    const selected = Array.isArray(value) ? value : [];
    const toggle = (v) => set(selected.includes(v) ? selected.filter((x) => x !== v) : [...selected, v]);
    return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, __(label), " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")), /* @__PURE__ */ React.createElement("div", { name, className: "flex flex-wrap gap-2" }, (options ?? []).map((o) => {
      const active = selected.includes(o.value);
      return /* @__PURE__ */ React.createElement(
        "button",
        {
          type: "button",
          key: String(o.value),
          onClick: () => toggle(o.value),
          className: `px-3 py-1.5 rounded-om-sm text-[13px] border transition-colors ${active ? "bg-om-ink text-om-on-ink border-om-ink" : "bg-om-card text-om-ink border-om-line hover:bg-om-chip"}`
        },
        __(o.label)
      );
    })), help && /* @__PURE__ */ React.createElement("p", { className: "text-[12px] text-om-muted mt-1" }, __(help)), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, error));
  }
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, __(label), " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")), type === "textarea" ? /* @__PURE__ */ React.createElement(
    "textarea",
    {
      name,
      value: value ?? "",
      onChange: (e) => set(e.target.value),
      rows: 3,
      placeholder: placeholder ? __(placeholder) : void 0,
      className: INPUT_CLASS
    }
  ) : type === "select" ? /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      className: "w-full",
      options: (options ?? []).map((o) => ({ value: String(o.value), label: __(o.label) })),
      value: value == null ? "" : String(value),
      onChange: (v) => set(v),
      placeholder: placeholder ? __(placeholder) : `${__(label)}\u2026`
    }
  ) : type === "date" ? /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      className: "w-full",
      value: value || null,
      onChange: (iso) => set(iso ?? ""),
      placeholder
    }
  ) : type === "color" ? /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "color",
      name,
      value: value || "#3b82f6",
      onChange: (e) => set(e.target.value),
      className: "h-9 w-16 rounded-om-sm border border-om-line bg-om-bg p-0.5"
    }
  ) : /* @__PURE__ */ React.createElement(
    "input",
    {
      type: { number: "number", date: "date", time: "time", datetime: "datetime-local" }[type] ?? "text",
      name,
      value: value ?? "",
      onChange: (e) => set(e.target.value),
      placeholder: placeholder ? __(placeholder) : void 0,
      className: INPUT_CLASS
    }
  ), help && /* @__PURE__ */ React.createElement("p", { className: "text-[12px] text-om-muted mt-1" }, help), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, error));
}
export {
  ResourceForm as default
};
