import { Head, Link } from "@inertiajs/react";
import OnboardingLayout from "../../layouts/OnboardingLayout";
import { __ } from "../../lib/i18n";
function Complete() {
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Setup Complete") }), /* @__PURE__ */ React.createElement("div", { className: "text-center py-8" }, /* @__PURE__ */ React.createElement("div", { className: "w-16 h-16 bg-om-running-bg rounded-full flex items-center justify-center mx-auto mb-4" }, /* @__PURE__ */ React.createElement("svg", { className: "w-8 h-8 text-om-running", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
    "path",
    {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      strokeWidth: "2",
      d: "M5 13l4 4L19 7"
    }
  ))), /* @__PURE__ */ React.createElement("h2", { className: "text-2xl font-bold text-om-ink mb-2" }, __("Setup Complete!")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mb-6" }, __("Your production line, product type, process template, and first work order have been created.")), /* @__PURE__ */ React.createElement("div", { className: "space-y-3" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "btn-touch btn-primary block" }, __("Go to Dashboard")), /* @__PURE__ */ React.createElement(Link, { href: "/operator/select-line", className: "btn-touch btn-secondary block" }, __("Start as Operator")))));
}
Complete.layout = (page) => /* @__PURE__ */ React.createElement(OnboardingLayout, null, page);
export {
  Complete as default
};
