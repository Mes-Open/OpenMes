import { Link, useForm } from "@inertiajs/react";
import { Button, Checkbox, TextField } from "@openmes/ui";
import AuthLayout from "../../layouts/AuthLayout";
import { __ } from "../../lib/i18n";
function Register() {
  const form = useForm({
    name: "",
    username: "",
    email: "",
    password: "",
    password_confirmation: "",
    marketing_consent: false
  });
  const submit = (e) => {
    e.preventDefault();
    form.post("/register");
  };
  const isDisabled = !form.data.name || !form.data.username || !form.data.email || !form.data.password || !form.data.password_confirmation;
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-semibold tracking-[-0.02em] text-om-ink mb-4 text-center" }, __("Create account")), /* @__PURE__ */ React.createElement("div", { className: "mb-5 flex items-start gap-3 rounded-om-sm border border-om-downtime/30 bg-om-downtime-bg px-4 py-3 text-sm text-om-downtime" }, /* @__PURE__ */ React.createElement(
    "svg",
    {
      xmlns: "http://www.w3.org/2000/svg",
      className: "mt-0.5 h-4 w-4 shrink-0",
      fill: "none",
      viewBox: "0 0 24 24",
      stroke: "currentColor"
    },
    /* @__PURE__ */ React.createElement(
      "path",
      {
        strokeLinecap: "round",
        strokeLinejoin: "round",
        strokeWidth: "2",
        d: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
      }
    )
  ), /* @__PURE__ */ React.createElement("span", null, __("This is a"), " ", /* @__PURE__ */ React.createElement("strong", null, __("demo account")), " \u2014 ", __("it will be automatically deleted after"), " ", /* @__PURE__ */ React.createElement("strong", null, __("3 hours")), ".")), /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "mb-4",
      label: __("Full name"),
      id: "name",
      name: "name",
      value: form.data.name,
      onChange: (v) => form.setData("name", v),
      error: form.errors.name,
      autoComplete: "name",
      autoFocus: true,
      required: true
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "mb-4",
      label: __("Username"),
      id: "username",
      name: "username",
      value: form.data.username,
      onChange: (v) => form.setData("username", v),
      error: form.errors.username,
      autoComplete: "username",
      required: true
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "mb-4",
      label: __("Email"),
      type: "email",
      id: "email",
      name: "email",
      value: form.data.email,
      onChange: (v) => form.setData("email", v),
      error: form.errors.email,
      autoComplete: "email",
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
      value: form.data.password,
      onChange: (v) => form.setData("password", v),
      error: form.errors.password,
      autoComplete: "new-password",
      required: true
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "mb-4",
      label: __("Confirm password"),
      type: "password",
      id: "password_confirmation",
      name: "password_confirmation",
      value: form.data.password_confirmation,
      onChange: (v) => form.setData("password_confirmation", v),
      error: form.errors.password_confirmation,
      autoComplete: "new-password",
      required: true
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("label", { className: "flex items-start gap-2 cursor-pointer" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: form.data.marketing_consent,
      onChange: (next) => form.setData("marketing_consent", next),
      className: "mt-1"
    }
  ), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted leading-relaxed" }, __("I agree to receive product updates and marketing communications via email."), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "/ ", __("I agree to receive product updates and marketing communications via email."))))), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "accent",
      className: "w-full",
      loading: form.processing,
      disabled: isDisabled
    },
    form.processing ? __("Creating account...") : __("Create account")
  )), /* @__PURE__ */ React.createElement("p", { className: "mt-6 text-center text-sm text-om-muted" }, __("Already have an account?"), " ", /* @__PURE__ */ React.createElement(Link, { href: "/login", className: "text-om-accent hover:underline font-medium" }, __("Sign in"))));
}
Register.layout = (page) => /* @__PURE__ */ React.createElement(AuthLayout, null, page);
export {
  Register as default
};
