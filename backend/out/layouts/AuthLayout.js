import { usePage } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
function AuthLayout({ children }) {
  const { flash, locale, locales } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-bg min-h-screen flex items-center justify-center p-4 font-sans" }, /* @__PURE__ */ React.createElement("div", { className: "w-full max-w-md" }, /* @__PURE__ */ React.createElement("div", { className: "text-center mb-8" }, /* @__PURE__ */ React.createElement("img", { src: "/logo_open_mes.png", alt: "OpenMES", className: "h-9 w-auto mx-auto mb-3" }), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint" }, "Manufacturing Execution System")), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)] p-8" }, flash?.success && /* @__PURE__ */ React.createElement("div", { className: "mb-4 p-4 bg-om-running-bg border border-om-running/30 text-om-running text-sm rounded-om-sm", role: "alert" }, flash.success), flash?.error && /* @__PURE__ */ React.createElement("div", { className: "mb-4 p-4 bg-om-blocked-bg border border-om-blocked/30 text-om-blocked text-sm rounded-om-sm", role: "alert" }, flash.error), children), /* @__PURE__ */ React.createElement("div", { className: "text-center mt-6 text-sm text-om-muted space-y-3" }, locales && Object.keys(locales).length > 1 && /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: locale == null ? "" : String(locale),
      onChange: (v) => {
        window.location.href = `/locale/${v}`;
      },
      options: Object.entries(locales).map(([code, label]) => ({ value: String(code), label })),
      "aria-label": "Language",
      className: "mx-auto"
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[11px] text-om-faint" }, "\xA9 ", (/* @__PURE__ */ new Date()).getFullYear(), " All rights reserved."))));
}
export {
  AuthLayout as default
};
