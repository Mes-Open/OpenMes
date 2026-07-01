import { Head, Link } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
function ModulesStore() {
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Module Store") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-3xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "mb-8" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Module Store")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, __("Browse and install ready-made OpenMES modules"))), /* @__PURE__ */ React.createElement("div", { className: "card text-center py-20" }, /* @__PURE__ */ React.createElement("div", { className: "inline-flex items-center justify-center w-20 h-20 rounded-full bg-om-chip mb-6" }, /* @__PURE__ */ React.createElement("svg", { className: "w-10 h-10 text-om-faint", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
    "path",
    {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      strokeWidth: "1.5",
      d: "M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
    }
  ))), /* @__PURE__ */ React.createElement("h2", { className: "text-2xl font-bold text-om-muted mb-2" }, __("Coming Soon")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-sm max-w-md mx-auto mb-6" }, __("The module store is being prepared. Soon you will be able to browse, purchase and install certified OpenMES extensions with a single click.")), /* @__PURE__ */ React.createElement("span", { className: "inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-om-downtime-bg text-om-downtime border border-om-line" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4 mr-1.5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" })), __("Coming soon")), /* @__PURE__ */ React.createElement("div", { className: "mt-8 pt-8 border-t border-om-line2" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mb-3" }, __("In the meantime, you can install modules manually:")), /* @__PURE__ */ React.createElement(Link, { href: "/admin/modules/install", className: "btn-touch btn-secondary text-sm" }, __("Install from ZIP file"))))));
}
ModulesStore.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ModulesStore as default
};
