import { __ } from "../lib/i18n";
function CustomFieldsDisplay({ definitions = [], values = {}, title }) {
  const present = definitions.filter((d) => {
    const v = values?.[d.key];
    return v != null && v !== "" && !(Array.isArray(v) && v.length === 0);
  });
  if (!present.length) return null;
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold text-om-ink pb-3 mb-4 border-b border-om-line2" }, title ?? __("Custom fields")), /* @__PURE__ */ React.createElement("dl", { className: "grid grid-cols-1 sm:grid-cols-2 gap-4" }, present.map((d) => /* @__PURE__ */ React.createElement("div", { key: d.key }, /* @__PURE__ */ React.createElement("dt", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[3px]" }, d.label), /* @__PURE__ */ React.createElement("dd", { className: "text-[13px] font-medium text-om-ink" }, renderValue(d, values[d.key]))))));
}
function fileUrl(meta) {
  return meta?.path ? `/admin/custom-field-files/${meta.path.split("/").pop()}` : null;
}
function renderValue(def, value) {
  const options = def.config?.options ?? [];
  if (def.type === "image") {
    const url = fileUrl(value);
    return url ? /* @__PURE__ */ React.createElement("a", { href: url, target: "_blank", rel: "noreferrer" }, /* @__PURE__ */ React.createElement("img", { src: url, alt: value?.name ?? "", className: "h-24 w-24 rounded-om-sm border border-om-line object-cover" })) : "\u2014";
  }
  if (def.type === "file") {
    const url = fileUrl(value);
    return url ? /* @__PURE__ */ React.createElement("a", { href: url, target: "_blank", rel: "noreferrer", className: "text-om-accent hover:underline" }, value?.name ?? __("Download")) : "\u2014";
  }
  if (def.type === "boolean") return value ? __("Yes") : __("No");
  if (def.type === "multiselect") {
    const set = Array.isArray(value) ? value : [];
    return options.filter((o) => set.includes(o.value)).map((o) => o.label).join(", ") || "\u2014";
  }
  if (def.type === "select") {
    return options.find((o) => o.value === value)?.label ?? String(value);
  }
  return String(value);
}
export {
  CustomFieldsDisplay as default
};
