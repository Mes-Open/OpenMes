import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Badge, Button, InlineAlert, TextField } from "@openmes/ui";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
function Profile() {
  const { auth } = usePage().props;
  const user = auth?.user ?? {};
  const { data, setData, post, processing, errors } = useForm({
    name: user.name ?? "",
    email: user.email ?? ""
  });
  function handleSubmit(e) {
    e.preventDefault();
    post("/settings/profile");
  }
  const initial = user.name ? user.name.charAt(0).toUpperCase() : "?";
  const role = user.roles?.[0] ?? __("User");
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-2xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Profile") }), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/settings", className: "text-[13px] text-om-muted hover:text-om-ink flex items-center gap-2 mb-4 transition-colors" }, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })), __("Back")), /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Profile"))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-6" }, /* @__PURE__ */ React.createElement("form", { onSubmit: handleSubmit }, /* @__PURE__ */ React.createElement("div", { className: "mb-6 flex items-center gap-4 border-b border-om-line pb-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex-shrink-0 h-20 w-20 bg-om-chip rounded-full flex items-center justify-center" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-ink font-semibold text-3xl" }, initial)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h3", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink" }, user.username), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted" }, role))), /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "mb-6",
      label: __("Name"),
      id: "name",
      value: data.name,
      onChange: (v) => setData("name", v),
      error: errors.name,
      required: true,
      autoFocus: true
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "mb-6",
      label: __("Email"),
      type: "email",
      id: "email",
      value: data.email,
      onChange: (v) => setData("email", v),
      error: errors.email,
      required: true
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "bg-om-chip rounded-om-sm p-4 mb-6" }, /* @__PURE__ */ React.createElement("h3", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-3" }, __("Account Information")), /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between text-[12.5px]" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Username:")), /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, user.username)), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center text-[12.5px]" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Role:")), /* @__PURE__ */ React.createElement(Badge, { variant: "outline" }, role)))), /* @__PURE__ */ React.createElement(InlineAlert, { severity: "info", title: __("Note:"), className: "mb-6" }, __("To change your username or role, contact an administrator.")), /* @__PURE__ */ React.createElement("div", { className: "flex justify-end gap-3" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/settings",
      className: "inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
    },
    __("Cancel")
  ), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", loading: processing }, __("Save"))))));
}
Profile.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  Profile as default
};
