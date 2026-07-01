import { Head, Link, usePage } from "@inertiajs/react";
import AppLayout from "../layouts/AppLayout";
import OperatorLayout from "../layouts/OperatorLayout";
import { __ } from "../lib/i18n";
const TITLES = {
  403: "Forbidden",
  404: "Page not found",
  429: "Too many requests",
  500: "Server error",
  503: "Service unavailable"
};
const MESSAGES = {
  403: "You do not have permission to view this page.",
  404: "The page you are looking for does not exist or was moved.",
  429: "Too many requests. Please wait a moment and try again.",
  500: "Something went wrong on our end. Please try again.",
  503: "The service is temporarily unavailable. Please try again shortly."
};
function ErrorPage({ status }) {
  const { auth } = usePage().props;
  const roles = auth?.user?.roles ?? [];
  const title = TITLES[status] ?? "Error";
  const message = MESSAGES[status] ?? "An unexpected error occurred.";
  const content = /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `${status} \u2014 ${__(title)}` }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-center py-20 px-4" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om shadow-sm border border-om-line2 p-10 max-w-md w-full text-center" }, /* @__PURE__ */ React.createElement("p", { className: "text-6xl font-extrabold text-gray-200 leading-none" }, status), /* @__PURE__ */ React.createElement("h1", { className: "mt-4 text-xl font-bold text-om-ink" }, __(title)), /* @__PURE__ */ React.createElement("p", { className: "mt-2 text-sm text-om-muted" }, __(message)), /* @__PURE__ */ React.createElement("div", { className: "mt-6 flex items-center justify-center gap-3" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/",
      className: "bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover"
    },
    __("Back to dashboard")
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => window.location.reload(),
      className: "text-om-muted hover:text-om-ink text-sm"
    },
    __("Try again")
  )))));
  if (roles.length === 0) {
    return content;
  }
  const Layout = roles.includes("Admin") || roles.includes("Supervisor") ? AppLayout : OperatorLayout;
  return /* @__PURE__ */ React.createElement(Layout, null, content);
}
export {
  ErrorPage as default
};
