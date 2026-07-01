import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { Button } from "@openmes/ui";
import AuthLayout from "../../layouts/AuthLayout";
import { __ } from "../../lib/i18n";
function ChangePassword({ forceChange = false }) {
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: ""
  });
  const submit = (e) => {
    e.preventDefault();
    form.post("/settings/change-password");
  };
  const passwordsMatch = form.data.password && form.data.password_confirmation && form.data.password === form.data.password_confirmation;
  const isDisabled = !form.data.current_password || !form.data.password || !form.data.password_confirmation || !passwordsMatch;
  const fieldCls = (hasError) => `w-full text-[13px] text-om-ink placeholder:text-om-faint bg-om-bg rounded-om-sm border px-3 py-2.5 pr-12 outline-none transition-colors focus:border-om-accent focus:bg-om-card focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)] ${hasError ? "border-om-blocked" : "border-om-line"}`;
  const labelCls = "block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint";
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("h1", { className: "text-xl font-semibold tracking-[-0.02em] text-om-ink text-center" }, __("Change Password")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-2 text-center text-sm" }, __("Update your password to keep your account secure.")), forceChange && /* @__PURE__ */ React.createElement("div", { className: "mt-4 p-4 bg-om-downtime-bg border border-om-downtime/30 text-om-downtime rounded-om-sm text-sm" }, /* @__PURE__ */ React.createElement("strong", null, __("Action required:")), " ", __("You must change your password before continuing."))), /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("label", { htmlFor: "current_password", className: labelCls }, __("Current Password")), /* @__PURE__ */ React.createElement("div", { className: "relative" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: showCurrent ? "text" : "password",
      id: "current_password",
      name: "current_password",
      value: form.data.current_password,
      onChange: (e) => form.setData("current_password", e.target.value),
      className: fieldCls(form.errors.current_password),
      autoComplete: "current-password",
      required: true
    }
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setShowCurrent((v) => !v),
      className: "absolute right-3 top-1/2 -translate-y-1/2 text-om-faint hover:text-om-muted",
      tabIndex: -1
    },
    /* @__PURE__ */ React.createElement(EyeIcon, { open: showCurrent })
  )), form.errors.current_password && /* @__PURE__ */ React.createElement("p", { className: "mt-[5px] text-[11.5px] text-om-blocked" }, form.errors.current_password)), /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("label", { htmlFor: "new_password", className: labelCls }, __("New Password")), /* @__PURE__ */ React.createElement("div", { className: "relative" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: showNew ? "text" : "password",
      id: "new_password",
      name: "new_password",
      value: form.data.password,
      onChange: (e) => form.setData("password", e.target.value),
      className: fieldCls(form.errors.password),
      autoComplete: "new-password",
      minLength: 8,
      required: true
    }
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setShowNew((v) => !v),
      className: "absolute right-3 top-1/2 -translate-y-1/2 text-om-faint hover:text-om-muted",
      tabIndex: -1
    },
    /* @__PURE__ */ React.createElement(EyeIcon, { open: showNew })
  )), /* @__PURE__ */ React.createElement("p", { className: "mt-[5px] text-[11.5px] text-om-faint" }, __("Minimum 8 characters")), form.errors.password && /* @__PURE__ */ React.createElement("p", { className: "mt-[5px] text-[11.5px] text-om-blocked" }, form.errors.password)), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("label", { htmlFor: "new_password_confirmation", className: labelCls }, __("Confirm New Password")), /* @__PURE__ */ React.createElement("div", { className: "relative" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: showConfirm ? "text" : "password",
      id: "new_password_confirmation",
      name: "new_password_confirmation",
      value: form.data.password_confirmation,
      onChange: (e) => form.setData("password_confirmation", e.target.value),
      className: fieldCls(false),
      autoComplete: "new-password",
      minLength: 8,
      required: true
    }
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setShowConfirm((v) => !v),
      className: "absolute right-3 top-1/2 -translate-y-1/2 text-om-faint hover:text-om-muted",
      tabIndex: -1
    },
    /* @__PURE__ */ React.createElement(EyeIcon, { open: showConfirm })
  )), /* @__PURE__ */ React.createElement("p", { className: `mt-[5px] text-[11.5px] ${passwordsMatch ? "text-om-running" : "text-om-faint"}` }, !form.data.password_confirmation && __("Re-enter your new password"), form.data.password_confirmation && !passwordsMatch && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, __("Passwords do not match")), passwordsMatch && __("\u2713 Passwords match"))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row gap-3" }, /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "accent",
      className: "flex-1",
      loading: form.processing,
      disabled: isDisabled
    },
    form.processing ? __("Changing...") : __("Change Password")
  ), !forceChange && /* @__PURE__ */ React.createElement(
    "a",
    {
      href: "/operator/select-line",
      className: "flex-1 inline-flex items-center justify-center text-[13px] font-semibold text-om-ink bg-transparent border border-om-line hover:bg-om-chip rounded-om-sm px-4 py-[9px] text-center transition-colors"
    },
    __("Cancel")
  ))));
}
ChangePassword.layout = (page) => /* @__PURE__ */ React.createElement(AuthLayout, null, page);
function EyeIcon({ open }) {
  if (open) {
    return /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
      "path",
      {
        strokeLinecap: "round",
        strokeLinejoin: "round",
        strokeWidth: "2",
        d: "M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
      }
    ));
  }
  return /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 12a3 3 0 11-6 0 3 3 0 016 0z" }), /* @__PURE__ */ React.createElement(
    "path",
    {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      strokeWidth: "2",
      d: "M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
    }
  ));
}
export {
  ChangePassword as default
};
