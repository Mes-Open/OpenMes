import { useRef, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
function ModulesInstall() {
  const { csrf_token } = usePage().props;
  const fileRef = useRef(null);
  const [filename, setFilename] = useState("");
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Install Module") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-3xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Install Module")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, __("Upload a module from a ZIP file or place the folder manually"))), /* @__PURE__ */ React.createElement("div", { className: "card mb-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-base font-bold text-om-ink mb-4 flex items-center gap-2" }, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 text-om-accent", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
    "path",
    {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      strokeWidth: "2",
      d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
    }
  )), __("Upload ZIP file")), /* @__PURE__ */ React.createElement("form", { method: "POST", action: "/admin/modules/upload", encType: "multipart/form-data" }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrf_token }), /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "border-2 border-dashed border-om-line hover:border-blue-400 rounded-om p-8 cursor-pointer text-center transition-colors mb-4",
      onClick: () => fileRef.current?.click()
    },
    /* @__PURE__ */ React.createElement("svg", { className: "mx-auto h-10 w-10 text-om-faint mb-2", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
      "path",
      {
        strokeLinecap: "round",
        strokeLinejoin: "round",
        strokeWidth: "1.5",
        d: "M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
      }
    )),
    /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, filename || __("Click to select a .zip file")),
    /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mt-1" }, __("Max 20 MB")),
    /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "file",
        name: "module_zip",
        ref: fileRef,
        accept: ".zip",
        className: "hidden",
        onChange: (e) => setFilename(e.target.files?.[0]?.name ?? ""),
        required: true
      }
    )
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "submit",
      disabled: !filename,
      className: `btn-touch btn-primary${!filename ? " opacity-50 cursor-not-allowed" : ""}`
    },
    __("Install Module")
  )), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mt-4" }, __("The ZIP must contain a"), " ", /* @__PURE__ */ React.createElement("code", { className: "bg-om-chip px-1 rounded" }, "module.json"), " ", __("file in the root directory or inside a single subfolder."))), /* @__PURE__ */ React.createElement("div", { className: "card bg-om-panel border border-om-line2" }, /* @__PURE__ */ React.createElement("h3", { className: "font-bold text-om-muted mb-2" }, __("Manual Installation")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-3" }, __("Place the module folder directly in"), " ", /* @__PURE__ */ React.createElement("code", { className: "bg-om-card border rounded px-1 text-xs" }, "modules/"), ",", " ", __("then go to"), " ", /* @__PURE__ */ React.createElement(Link, { href: "/admin/modules", className: "text-om-accent hover:underline" }, __("Installed Modules")), " ", __("and enable it.")), /* @__PURE__ */ React.createElement("div", { className: "text-xs font-mono bg-om-card border rounded p-3 text-om-muted space-y-0.5 mb-4" }, /* @__PURE__ */ React.createElement("p", null, "modules/YourModule/"), /* @__PURE__ */ React.createElement("p", { className: "pl-4" }, "\u251C\u2500\u2500 module.json"), /* @__PURE__ */ React.createElement("p", { className: "pl-4" }, "\u251C\u2500\u2500 Providers/"), /* @__PURE__ */ React.createElement("p", { className: "pl-8" }, "\u2502   \u2514\u2500\u2500 YourModuleServiceProvider.php"), /* @__PURE__ */ React.createElement("p", { className: "pl-4" }, "\u251C\u2500\u2500 Controllers/"), /* @__PURE__ */ React.createElement("p", { className: "pl-4" }, "\u251C\u2500\u2500 Models/"), /* @__PURE__ */ React.createElement("p", { className: "pl-4" }, "\u251C\u2500\u2500 migrations/"), /* @__PURE__ */ React.createElement("p", { className: "pl-4" }, "\u251C\u2500\u2500 views/"), /* @__PURE__ */ React.createElement("p", { className: "pl-4" }, "\u2514\u2500\u2500 README.md")), /* @__PURE__ */ React.createElement(
    "a",
    {
      href: "https://github.com/Mes-Open/OpenMes/blob/main/HOOKS.md",
      target: "_blank",
      rel: "noopener noreferrer",
      className: "text-sm text-om-accent hover:underline"
    },
    __("Available hooks and events (HOOKS.md) \u2197")
  ))));
}
ModulesInstall.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ModulesInstall as default
};
