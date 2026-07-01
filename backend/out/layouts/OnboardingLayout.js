import { Head, router, usePage } from "@inertiajs/react";
import { __ } from "../lib/i18n";
function OnboardingLayout({ children }) {
  const { step = 1, csrf_token } = usePage().props;
  const steps = ["Line", "Product", "Process", "Work Order"];
  const skipWizard = (e) => {
    e.preventDefault();
    router.post("/onboarding/skip", {}, { headers: { "X-CSRF-TOKEN": csrf_token } });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-bg min-h-screen flex flex-col items-center justify-center p-4 font-sans" }, /* @__PURE__ */ React.createElement(Head, { title: "OpenMES \u2014 Setup Wizard" }), /* @__PURE__ */ React.createElement("div", { className: "w-full max-w-2xl" }, /* @__PURE__ */ React.createElement("div", { className: "text-center mb-8" }, /* @__PURE__ */ React.createElement("img", { src: "/logo_open_mes.png", alt: "OpenMES", className: "h-9 w-auto mx-auto mb-3" }), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint" }, __("Setup Wizard"))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-center mb-8" }, steps.map((label, i) => {
    const stepNum = i + 1;
    const done = stepNum < step;
    const current = stepNum === step;
    return /* @__PURE__ */ React.createElement("div", { key: label, className: "flex items-center" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col items-center" }, /* @__PURE__ */ React.createElement(
      "div",
      {
        className: `w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold
                                            ${done ? "bg-om-running text-white" : current ? "bg-om-accent text-white" : "bg-om-chip text-om-faint border border-om-line"}`
      },
      done ? /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "currentColor", viewBox: "0 0 20 20" }, /* @__PURE__ */ React.createElement(
        "path",
        {
          fillRule: "evenodd",
          d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
          clipRule: "evenodd"
        }
      )) : stepNum
    ), /* @__PURE__ */ React.createElement(
      "span",
      {
        className: `font-mono text-[9.5px] uppercase tracking-[0.08em] mt-1.5 ${current ? "text-om-accent font-medium" : "text-om-faint"}`
      },
      __(label)
    )), i < steps.length - 1 && /* @__PURE__ */ React.createElement(
      "div",
      {
        className: `w-12 h-px mx-1 ${done ? "bg-om-running" : "bg-om-line"}`
      }
    ));
  })), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)] p-8" }, children), /* @__PURE__ */ React.createElement("div", { className: "text-center mt-4" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: skipWizard,
      className: "text-sm text-om-faint hover:text-om-muted"
    },
    __("Skip wizard \u2192")
  ))));
}
export {
  OnboardingLayout as default
};
