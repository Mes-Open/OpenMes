import { useState } from "react";
import { Link, useForm } from "@inertiajs/react";
import { Button, Checkbox, TextField } from "@openmes/ui";
import AuthLayout from "../../layouts/AuthLayout";
import { __ } from "../../lib/i18n";
function Login({ pinEnabled = false, regEnabled = false }) {
  const [tab, setTab] = useState("password");
  const passwordForm = useForm({
    username: "",
    password: "",
    remember: false
  });
  const pinForm = useForm({
    username: "",
    pin: ""
  });
  const submitPassword = (e) => {
    e.preventDefault();
    passwordForm.post("/login");
  };
  const submitPin = (e) => {
    e.preventDefault();
    pinForm.post("/login/pin");
  };
  const switchTab = (t) => {
    setTab(t);
    passwordForm.clearErrors();
    pinForm.clearErrors();
  };
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-semibold tracking-[-0.02em] text-om-ink mb-6 text-center" }, __("Sign in")), pinEnabled && /* @__PURE__ */ React.createElement("div", { className: "flex rounded-om-sm bg-om-chip p-1 mb-6" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      "data-testid": "tab-password",
      onClick: () => switchTab("password"),
      className: `flex-1 py-2 text-sm font-medium rounded-[6px] transition-colors ${tab === "password" ? "bg-om-card text-om-ink shadow-sm" : "text-om-muted hover:text-om-ink"}`
    },
    __("Password")
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      "data-testid": "tab-pin",
      onClick: () => switchTab("pin"),
      className: `flex-1 py-2 text-sm font-medium rounded-[6px] transition-colors ${tab === "pin" ? "bg-om-card text-om-ink shadow-sm" : "text-om-muted hover:text-om-ink"}`
    },
    __("Quick PIN")
  )), tab === "password" && /* @__PURE__ */ React.createElement("form", { onSubmit: submitPassword }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "mb-4",
      label: __("Username"),
      id: "username",
      name: "username",
      value: passwordForm.data.username,
      onChange: (v) => passwordForm.setData("username", v),
      error: passwordForm.errors.username,
      autoComplete: "username",
      autoFocus: true,
      required: true
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "mb-4",
      label: __("Password"),
      type: "password",
      id: "password",
      name: "password",
      value: passwordForm.data.password,
      onChange: (v) => passwordForm.setData("password", v),
      error: passwordForm.errors.password,
      autoComplete: "current-password",
      required: true
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "mb-6 flex items-center" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      id: "remember",
      checked: passwordForm.data.remember,
      onChange: (v) => passwordForm.setData("remember", v),
      label: __("Remember me")
    }
  )), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "accent",
      className: "w-full",
      loading: passwordForm.processing,
      disabled: !passwordForm.data.username || !passwordForm.data.password
    },
    passwordForm.processing ? __("Logging in...") : __("Sign in")
  )), pinEnabled && tab === "pin" && /* @__PURE__ */ React.createElement("form", { onSubmit: submitPin }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "mb-4",
      label: __("Username"),
      id: "pin_username",
      name: "username",
      value: pinForm.data.username,
      onChange: (v) => pinForm.setData("username", v),
      error: pinForm.errors.username,
      autoComplete: "username",
      required: true
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("label", { htmlFor: "pin_input", className: "block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("PIN")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "password",
      id: "pin_input",
      name: "pin",
      value: pinForm.data.pin,
      onChange: (e) => pinForm.setData("pin", e.target.value),
      inputMode: "numeric",
      pattern: "[0-9]*",
      maxLength: 6,
      className: `w-full text-center text-2xl font-mono tracking-[0.5em] text-om-ink placeholder:text-om-faint bg-om-bg rounded-om-sm border px-3 py-2.5 outline-none transition-colors focus:border-om-accent focus:bg-om-card focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)] ${pinForm.errors.pin ? "border-om-blocked" : "border-om-line"}`,
      autoComplete: "off",
      placeholder: "----",
      required: true
    }
  ), pinForm.errors.pin && /* @__PURE__ */ React.createElement("p", { className: "mt-[5px] text-[11.5px] text-om-blocked" }, pinForm.errors.pin), /* @__PURE__ */ React.createElement("p", { className: "mt-[5px] text-[11.5px] text-om-faint" }, __("Enter your 4\u20136 digit PIN"))), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "accent",
      className: "w-full",
      loading: pinForm.processing,
      disabled: !pinForm.data.username || pinForm.data.pin.length < 4
    },
    pinForm.processing ? __("Logging in...") : __("Quick PIN Login")
  ), /* @__PURE__ */ React.createElement("p", { className: "mt-4 text-center text-[11.5px] text-om-faint" }, __("No PIN yet? Log in with password first, then set your PIN in Settings."))), regEnabled && /* @__PURE__ */ React.createElement("p", { className: "mt-6 text-center text-sm text-om-muted" }, __("Don't have an account?"), " ", /* @__PURE__ */ React.createElement(Link, { href: "/register", className: "text-om-accent hover:underline font-medium" }, __("Create account"))));
}
Login.layout = (page) => /* @__PURE__ */ React.createElement(AuthLayout, null, page);
export {
  Login as default
};
