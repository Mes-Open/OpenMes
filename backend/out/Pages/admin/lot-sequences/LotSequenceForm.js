import { useEffect, useRef, useState } from "react";
import { Link, useForm, usePage } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
import { __ } from "../../../lib/i18n";
const RESET_PERIODS = [
  { value: "none", label: "No reset" },
  { value: "yearly", label: "Yearly" },
  { value: "monthly", label: "Monthly" },
  { value: "daily", label: "Daily" },
  { value: "hourly", label: "Hourly" }
];
function LotSequenceForm({ action, method, initial, submitLabel }) {
  const { productTypes = [], patternTokens = [], csrf_token } = usePage().props;
  const form = useForm(initial);
  const { data, setData, errors, processing } = form;
  const [mode, setMode] = useState(initial.pattern ? "pattern" : "simple");
  const [preview, setPreview] = useState(null);
  const [previewError, setPreviewError] = useState(null);
  const patternInputRef = useRef(null);
  useEffect(() => {
    if (mode !== "pattern" || !data.pattern) {
      setPreview(null);
      setPreviewError(null);
      return;
    }
    const t = setTimeout(async () => {
      try {
        const r = await fetch("/admin/lot-sequences/preview", {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf_token,
            Accept: "application/json"
          },
          body: JSON.stringify({
            pattern: data.pattern,
            pad_size: data.pad_size || 4,
            product_type_id: data.product_type_id || null
          })
        });
        if (r.status === 422) {
          const body2 = await r.json();
          setPreview(null);
          setPreviewError(body2.errors?.pattern?.[0] ?? "Invalid pattern");
          return;
        }
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        const body = await r.json();
        setPreview(body.preview);
        setPreviewError(null);
      } catch {
        setPreview(null);
        setPreviewError(null);
      }
    }, 300);
    return () => clearTimeout(t);
  }, [mode, data.pattern, data.pad_size, data.product_type_id, csrf_token]);
  const insertToken = (token) => {
    const tag = `[${token}]`;
    const el = patternInputRef.current;
    const current = data.pattern ?? "";
    if (el && document.activeElement === el) {
      const start = el.selectionStart ?? current.length;
      const end = el.selectionEnd ?? current.length;
      setData("pattern", current.slice(0, start) + tag + current.slice(end));
      requestAnimationFrame(() => {
        el.focus();
        el.setSelectionRange(start + tag.length, start + tag.length);
      });
    } else {
      setData("pattern", current + tag);
    }
  };
  const switchMode = (m) => {
    setMode(m);
    if (m === "simple") setData("pattern", "");
  };
  const submit = (e) => {
    e.preventDefault();
    form.submit(method, action);
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5" }, /* @__PURE__ */ React.createElement(TextField, { label: __("Name"), required: true, value: data.name, error: errors.name, onChange: (v) => setData("name", v) }), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Product Type")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.product_type_id == null ? "" : String(data.product_type_id),
      onChange: (v) => setData("product_type_id", v),
      options: [
        { value: "", label: `\u2014 ${__("None (default sequence)")} \u2014` },
        ...productTypes.map((p) => ({ value: String(p.id), label: p.name }))
      ],
      className: "w-full"
    }
  ), errors.product_type_id && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.product_type_id)), /* @__PURE__ */ React.createElement("div", { className: "flex rounded-om-sm border border-om-line2 overflow-hidden w-fit text-sm" }, [
    ["pattern", __("Pattern")],
    ["simple", __("Simple")]
  ].map(([m, label]) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key: m,
      type: "button",
      onClick: () => switchMode(m),
      className: `px-4 py-1.5 font-medium ${mode === m ? "bg-om-ink text-om-on-ink" : "bg-om-card text-om-muted hover:bg-om-bg"}`
    },
    label
  ))), mode === "pattern" ? /* @__PURE__ */ React.createElement("div", { className: "space-y-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Pattern"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      ref: patternInputRef,
      type: "text",
      value: data.pattern ?? "",
      onChange: (e) => setData("pattern", e.target.value),
      placeholder: "test-[date]-[seq]-[hour]",
      className: "form-input w-full font-mono"
    }
  ), (errors.pattern || previewError) && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.pattern ?? previewError)), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1.5" }, patternTokens.map((t) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key: t,
      type: "button",
      onMouseDown: (e) => e.preventDefault(),
      onClick: () => insertToken(t),
      className: "px-2 py-0.5 rounded-full bg-om-chip hover:bg-om-chip text-xs font-mono text-om-muted border border-om-line2"
    },
    "[",
    t,
    "]"
  ))), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, __("Click a token to insert it. Pattern must contain exactly one"), " ", /* @__PURE__ */ React.createElement("code", null, "[seq]"), ". ", __("Use [date:y-m-d] for a custom date format.")), preview && /* @__PURE__ */ React.createElement("div", { className: "rounded-om-sm bg-om-panel border border-om-line2 px-3 py-2 text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Preview:"), " "), /* @__PURE__ */ React.createElement("span", { className: "font-mono font-medium text-om-ink" }, preview))) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(TextField, { label: __("Prefix"), required: true, value: data.prefix, error: errors.prefix, onChange: (v) => setData("prefix", v) }), /* @__PURE__ */ React.createElement(TextField, { label: __("Suffix"), value: data.suffix, error: errors.suffix, onChange: (v) => setData("suffix", v) }), /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: !!data.year_prefix,
      onChange: (next) => setData("year_prefix", next),
      label: __("Year Prefix")
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Pad Size")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      min: 1,
      max: 10,
      value: data.pad_size ?? 4,
      onChange: (e) => setData("pad_size", e.target.value),
      className: "form-input w-full"
    }
  ), errors.pad_size && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.pad_size)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Counter Reset")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.reset_period == null ? "none" : String(data.reset_period),
      onChange: (v) => setData("reset_period", v),
      options: RESET_PERIODS.map((o) => ({ value: String(o.value), label: __(o.label) })),
      className: "w-full"
    }
  ), errors.reset_period && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.reset_period))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing, disabled: processing }, submitLabel), /* @__PURE__ */ React.createElement(Link, { href: "/admin/lot-sequences", className: "text-om-muted hover:text-om-ink text-sm" }, __("Cancel"))));
}
function TextField({ label, required, value, error, onChange }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement("input", { type: "text", value: value ?? "", onChange: (e) => onChange(e.target.value), className: "form-input w-full" }), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
export {
  LotSequenceForm as default
};
