import { useState } from "react";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Button, InlineAlert, TextField } from "@openmes/ui";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
const PIN_INPUT_CLASS = "w-full text-center text-2xl font-mono tracking-[0.5em] text-om-ink placeholder:text-om-faint bg-om-bg rounded-om-sm border px-3 py-2.5 outline-none transition-colors focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]";
function Pin() {
  const { hasPin, csrf_token } = usePage().props;
  const { data, setData, post, processing, errors } = useForm({
    current_password: "",
    pin: "",
    pin_confirmation: ""
  });
  const [showRemove, setShowRemove] = useState(false);
  const {
    data: removeData,
    setData: setRemoveData,
    delete: destroyPin,
    processing: removeProcessing,
    errors: removeErrors
  } = useForm({ current_password: "" });
  const pinValid = data.pin.length >= 4 && data.pin === data.pin_confirmation;
  function handleSetPin(e) {
    e.preventDefault();
    post("/settings/pin");
  }
  function handleRemovePin(e) {
    e.preventDefault();
    destroyPin("/settings/pin");
  }
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-lg mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("PIN Setup") }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 mb-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/settings", className: "text-om-muted hover:text-om-ink transition-colors" }, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" }))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[22px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Quick PIN Login")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-[12.5px] mt-0.5" }, __("Set a 4\u20136 digit PIN for fast sign-in")))), hasPin && /* @__PURE__ */ React.createElement(InlineAlert, { severity: "success", title: __("PIN is active"), className: "mb-6" }, __("You can log in using your username and PIN.")), hasPin && /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-6 mb-6" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setShowRemove((v) => !v),
      className: "text-[12.5px] text-om-blocked hover:underline font-medium"
    },
    __("Remove PIN")
  ), showRemove && /* @__PURE__ */ React.createElement("form", { onSubmit: handleRemovePin, className: "mt-4 space-y-4" }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Confirm your password"),
      type: "password",
      id: "rm_password",
      value: removeData.current_password,
      onChange: (v) => setRemoveData("current_password", v),
      error: removeErrors.current_password,
      required: true
    }
  ), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "danger", loading: removeProcessing }, "Remove PIN"))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-2 pb-3 border-b border-om-line" }, hasPin ? __("Change PIN") : __("Set PIN")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-[12.5px] mb-4" }, __("Enter your current account password and choose a 4\u20136 digit numeric PIN.")), /* @__PURE__ */ React.createElement("form", { onSubmit: handleSetPin, className: "space-y-4" }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Current Password"),
      type: "password",
      id: "current_password",
      value: data.current_password,
      onChange: (v) => setData("current_password", v),
      error: errors.current_password,
      required: true,
      autoComplete: "current-password"
    }
  ), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { htmlFor: "pin", className: "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]" }, __("PIN (4\u20136 digits)")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "password",
      id: "pin",
      value: data.pin,
      onChange: (e) => setData("pin", e.target.value.replace(/\D/g, "").slice(0, 6)),
      inputMode: "numeric",
      pattern: "[0-9]*",
      maxLength: 6,
      className: `${PIN_INPUT_CLASS} ${errors.pin ? "border-om-blocked" : "border-om-line"}`,
      required: true,
      placeholder: "----"
    }
  ), errors.pin && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, errors.pin)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { htmlFor: "pin_confirmation", className: "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]" }, __("Confirm PIN")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "password",
      id: "pin_confirmation",
      value: data.pin_confirmation,
      onChange: (e) => setData("pin_confirmation", e.target.value.replace(/\D/g, "").slice(0, 6)),
      inputMode: "numeric",
      pattern: "[0-9]*",
      maxLength: 6,
      className: `${PIN_INPUT_CLASS} border-om-line`,
      required: true,
      placeholder: "----"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "pt-2" }, /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "accent",
      className: "w-full",
      disabled: !pinValid,
      loading: processing
    },
    hasPin ? __("Change PIN") : __("Set PIN")
  )))));
}
Pin.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  Pin as default
};
