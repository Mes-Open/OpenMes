import { Button, Checkbox, Dropdown } from "@openmes/ui";
const INPUT_CLASS = "w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-1.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]";
function RepeatableRows({ value, onChange, fields, addLabel = "+ Add row", newRow }) {
  const rows = value ?? [];
  const makeRow = newRow ?? (() => Object.fromEntries(fields.map((f) => [f.name, f.type === "select" ? f.options?.[0]?.value ?? "" : ""])));
  const update = (i, key, v) => onChange(rows.map((r, idx) => idx === i ? { ...r, [key]: v } : r));
  const add = () => onChange([...rows, makeRow()]);
  const remove = (i) => onChange(rows.filter((_, idx) => idx !== i));
  return /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 px-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, fields.map((f) => /* @__PURE__ */ React.createElement("div", { key: f.name, className: f.width ?? "flex-1" }, f.label)), /* @__PURE__ */ React.createElement("div", { className: "w-8" })), rows.length === 0 && /* @__PURE__ */ React.createElement("p", { className: "text-[13px] text-om-faint px-1" }, "No rows yet."), rows.map((row, i) => /* @__PURE__ */ React.createElement("div", { key: i, className: "flex items-center gap-2" }, fields.map((f) => /* @__PURE__ */ React.createElement("div", { key: f.name, className: `${f.width ?? "flex-1"} ${f.type === "checkbox" ? "flex justify-center" : ""}` }, f.type === "checkbox" ? /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: !!row[f.name],
      onChange: (next) => update(i, f.name, next)
    }
  ) : f.type === "select" ? /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      className: "w-full",
      options: (f.options ?? []).map((o) => ({ value: String(o.value), label: o.label })),
      value: row[f.name] == null ? "" : String(row[f.name]),
      onChange: (v) => update(i, f.name, v),
      placeholder: f.placeholder || `${f.label ?? ""}\u2026`
    }
  ) : /* @__PURE__ */ React.createElement(
    "input",
    {
      type: f.type === "number" ? "number" : "text",
      value: row[f.name] ?? "",
      onChange: (e) => update(i, f.name, e.target.value),
      placeholder: f.placeholder,
      className: INPUT_CLASS
    }
  ))), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => remove(i),
      className: "w-8 h-8 shrink-0 rounded-om-sm text-lg leading-none text-om-blocked hover:bg-om-blocked-bg transition-colors",
      title: "Remove"
    },
    "\xD7"
  ))), /* @__PURE__ */ React.createElement(Button, { variant: "ghost", onClick: add }, addLabel));
}
export {
  RepeatableRows as default
};
