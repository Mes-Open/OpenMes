import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Button, Checkbox } from "@openmes/ui";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
function Access() {
  const { tabs = [], roles = [], matrix = {}, lockedRole = "Admin" } = usePage().props;
  const initial = {};
  roles.forEach((role) => {
    initial[role] = role === lockedRole ? tabs.map((t) => t.key) : matrix[role] ?? [];
  });
  const form = useForm({ access: initial });
  const { data, setData, processing } = form;
  const isChecked = (role, key) => role === lockedRole || (data.access[role] ?? []).includes(key);
  const toggle = (role, key) => {
    if (role === lockedRole) return;
    const current = data.access[role] ?? [];
    const next = current.includes(key) ? current.filter((k) => k !== key) : [...current, key];
    setData("access", { ...data.access, [role]: next });
  };
  const submit = (e) => {
    e.preventDefault();
    form.post("/settings/access", { preserveScroll: true });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-5xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Tab Access") }), /* @__PURE__ */ React.createElement(Link, { href: "/settings", className: "mb-4 flex items-center gap-1 text-sm text-om-accent hover:underline" }, "\u2039 ", __("Settings")), /* @__PURE__ */ React.createElement("h1", { className: "mb-1 text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Tab Access")), /* @__PURE__ */ React.createElement("p", { className: "mb-6 text-sm text-om-muted" }, __("Grant each role access to individual admin-panel tabs. The Admin role always has full access.")), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "overflow-x-auto rounded-om border border-om-line bg-om-card p-5" }, /* @__PURE__ */ React.createElement("table", { className: "w-full text-sm" }, /* @__PURE__ */ React.createElement("thead", null, /* @__PURE__ */ React.createElement("tr", { className: "border-b border-om-line2 text-left" }, /* @__PURE__ */ React.createElement("th", { className: "py-2 pr-4 font-mono text-[9px] font-medium uppercase tracking-[0.1em] text-om-faint" }, __("Tab")), roles.map((role) => /* @__PURE__ */ React.createElement(
    "th",
    {
      key: role,
      className: "whitespace-nowrap px-3 py-2 text-center font-mono text-[9px] font-medium uppercase tracking-[0.1em] text-om-faint"
    },
    role,
    role === lockedRole && /* @__PURE__ */ React.createElement("span", { className: "block text-[9px] font-normal normal-case tracking-normal text-om-faintest" }, __("full"))
  )))), /* @__PURE__ */ React.createElement("tbody", null, tabs.map((tab) => /* @__PURE__ */ React.createElement("tr", { key: tab.key, className: "border-b border-om-line2 last:border-0" }, /* @__PURE__ */ React.createElement("td", { className: "py-2.5 pr-4 font-medium text-om-ink" }, __(tab.label)), roles.map((role) => /* @__PURE__ */ React.createElement("td", { key: role, className: "px-3 py-2.5 text-center" }, /* @__PURE__ */ React.createElement("span", { className: "inline-flex justify-center" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: isChecked(role, tab.key),
      disabled: role === lockedRole,
      onChange: () => toggle(role, tab.key)
    }
  )))))))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-4" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing }, processing ? __("Saving\u2026") : __("Save")), /* @__PURE__ */ React.createElement(Link, { href: "/settings", className: "text-sm text-om-muted hover:text-om-ink" }, __("Cancel")))));
}
Access.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  Access as default
};
