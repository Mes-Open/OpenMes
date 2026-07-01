import { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Button, ConfirmDialog, StatusPill } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
function ModulesIndex() {
  const { modules = [], csrf_token } = usePage().props;
  const [toUninstall, setToUninstall] = useState(null);
  function postForm(action) {
    router.post(action, {}, { preserveScroll: true });
  }
  function confirmDestroy() {
    if (toUninstall) {
      router.delete(`/admin/modules/${toUninstall.name}`);
    }
    setToUninstall(null);
  }
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Installed Modules") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-5xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Installed Modules")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-sm mt-0.5" }, __("Enable and disable installed OpenMES extensions"))), /* @__PURE__ */ React.createElement(Link, { href: "/admin/modules/install" }, /* @__PURE__ */ React.createElement(Button, { variant: "primary" }, __("+ Install Module")))), modules.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om text-center py-16" }, /* @__PURE__ */ React.createElement("svg", { className: "mx-auto h-16 w-16 text-om-faint mb-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
    "path",
    {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      strokeWidth: "1",
      d: "M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
    }
  )), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-lg font-medium" }, __("No modules installed")), /* @__PURE__ */ React.createElement("p", { className: "text-om-faint text-sm mt-1" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/modules/install", className: "text-om-accent hover:underline" }, __("Install a module from a ZIP file")), " ", __("or place the module folder in"), " ", /* @__PURE__ */ React.createElement("code", { className: "bg-om-chip text-om-ink px-1 rounded-om-sm font-mono" }, "modules/"))) : /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 gap-4" }, modules.map((module) => /* @__PURE__ */ React.createElement(
    "div",
    {
      key: module.name,
      className: `bg-om-card border rounded-om p-4 flex flex-col gap-4 ${module.enabled ? "border-l-[3px] border-l-om-accent border-y-om-line border-r-om-line" : "border-om-line"}`
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 flex-wrap" }, /* @__PURE__ */ React.createElement("h3", { className: "text-[14px] font-semibold text-om-ink" }, module.display_name ?? module.name), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-faint" }, "v", module.version ?? "?"), module.enabled ? /* @__PURE__ */ React.createElement(StatusPill, { status: "running", label: __("Enabled"), pulse: false }) : /* @__PURE__ */ React.createElement(StatusPill, { status: "pending", label: __("Disabled") }), module.has_error && /* @__PURE__ */ React.createElement(StatusPill, { status: "blocked", label: __("Provider Error") })), module.author && /* @__PURE__ */ React.createElement("p", { className: "text-[11px] text-om-faint mt-0.5" }, __("by"), " ", module.homepage ? /* @__PURE__ */ React.createElement("a", { href: module.homepage, target: "_blank", rel: "noopener noreferrer", className: "hover:underline" }, module.author) : module.author))),
    /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted flex-1" }, module.description ?? __("No description.")),
    module.hooks && module.hooks.length > 0 && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1.5" }, __("Used hooks")), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1" }, module.hooks.map((hook) => /* @__PURE__ */ React.createElement(
      "span",
      {
        key: hook,
        className: "font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-om-sm"
      },
      hook
    )))),
    /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 pt-3 border-t border-om-line2" }, module.enabled ? /* @__PURE__ */ React.createElement(
      Button,
      {
        variant: "secondary",
        onClick: () => postForm(`/admin/modules/${module.name}/disable`)
      },
      __("Disable")
    ) : /* @__PURE__ */ React.createElement(
      Button,
      {
        variant: "primary",
        onClick: () => postForm(`/admin/modules/${module.name}/enable`)
      },
      __("Enable")
    ), module.name !== "ExampleHooks" && /* @__PURE__ */ React.createElement(
      Button,
      {
        variant: "secondary",
        onClick: () => setToUninstall({ name: module.name, displayName: module.display_name ?? module.name }),
        className: "text-om-blocked hover:text-om-blocked"
      },
      __("Uninstall")
    ))
  )))), /* @__PURE__ */ React.createElement(
    ConfirmDialog,
    {
      open: toUninstall !== null,
      onClose: () => setToUninstall(null),
      onConfirm: confirmDestroy,
      title: toUninstall ? __("Uninstall module :name? Files will be removed.", { name: toUninstall.displayName }) : "",
      confirmLabel: __("Uninstall"),
      cancelLabel: __("Cancel"),
      destructive: true
    }
  ));
}
ModulesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ModulesIndex as default
};
