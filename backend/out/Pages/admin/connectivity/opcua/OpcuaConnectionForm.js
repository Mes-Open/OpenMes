import { Link, useForm } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
import { Section, Field } from "../ui";
import { __ } from "../../../../lib/i18n";
function OpcuaConnectionForm({ action, method, submitLabel, cancelHref, connection = null, onDelete }) {
  const opcua = connection?.opcua ?? null;
  const form = useForm({
    name: connection?.name ?? "",
    description: connection?.description ?? "",
    is_active: connection?.is_active ?? false,
    endpoint_url: opcua?.endpoint_url ?? "",
    security_policy: opcua?.security_policy ?? "None",
    security_mode: opcua?.security_mode ?? "None",
    auth_mode: opcua?.auth_mode ?? "anonymous",
    username: opcua?.username ?? "",
    password: "",
    publishing_interval_ms: String(opcua?.publishing_interval_ms ?? 1e3)
  });
  const { data, setData, errors, processing } = form;
  const submit = (e) => {
    e.preventDefault();
    form.submit(method, action);
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "space-y-6" }, /* @__PURE__ */ React.createElement(Section, { title: __("General") }, /* @__PURE__ */ React.createElement(Field, { label: __("Name"), required: true, error: errors.name }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), required: true, className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Description"), error: errors.description }, /* @__PURE__ */ React.createElement("textarea", { value: data.description, onChange: (e) => setData("description", e.target.value), rows: 2, className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Checkbox, { checked: data.is_active, onChange: (next) => setData("is_active", next), label: __("Active (gateway subscribes on start)") })), /* @__PURE__ */ React.createElement(Section, { title: __("Endpoint") }, /* @__PURE__ */ React.createElement(Field, { label: __("Endpoint URL"), required: true, error: errors.endpoint_url }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.endpoint_url, onChange: (e) => setData("endpoint_url", e.target.value), placeholder: "opc.tcp://192.168.1.50:4840", required: true, className: "form-input w-full font-mono" })), /* @__PURE__ */ React.createElement(Field, { label: __("Publishing interval (ms)"), required: true, error: errors.publishing_interval_ms }, /* @__PURE__ */ React.createElement("input", { type: "number", value: data.publishing_interval_ms, onChange: (e) => setData("publishing_interval_ms", e.target.value), min: "100", max: "60000", required: true, className: "form-input w-full" }))), /* @__PURE__ */ React.createElement(Section, { title: __("Security") }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(Field, { label: __("Security policy"), required: true, error: errors.security_policy }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.security_policy == null ? "" : String(data.security_policy),
      onChange: (v) => setData("security_policy", v),
      options: [
        { value: "None", label: __("None") },
        { value: "Basic256Sha256", label: "Basic256Sha256" }
      ],
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("Security mode"), required: true, error: errors.security_mode }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.security_mode == null ? "" : String(data.security_mode),
      onChange: (v) => setData("security_mode", v),
      options: [
        { value: "None", label: __("None") },
        { value: "Sign", label: __("Sign") },
        { value: "SignAndEncrypt", label: __("Sign & Encrypt") }
      ],
      className: "w-full"
    }
  ))), /* @__PURE__ */ React.createElement(Field, { label: __("Authentication"), required: true, error: errors.auth_mode }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.auth_mode == null ? "" : String(data.auth_mode),
      onChange: (v) => setData("auth_mode", v),
      options: [
        { value: "anonymous", label: __("Anonymous") },
        { value: "username", label: __("Username / password") },
        { value: "certificate", label: __("Certificate") }
      ],
      className: "w-full"
    }
  )), data.auth_mode === "username" && /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(Field, { label: __("Username"), error: errors.username }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.username, onChange: (e) => setData("username", e.target.value), autoComplete: "off", className: "form-input w-full" })), /* @__PURE__ */ React.createElement(
    Field,
    {
      label: /* @__PURE__ */ React.createElement(React.Fragment, null, __("Password"), opcua?.has_password && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint font-normal ml-1" }, __("(leave blank to keep current)"))),
      error: errors.password
    },
    /* @__PURE__ */ React.createElement("input", { type: "password", value: data.password, onChange: (e) => setData("password", e.target.value), autoComplete: "new-password", className: "form-input w-full" })
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing, disabled: processing }, processing ? __("Saving\u2026") : submitLabel), /* @__PURE__ */ React.createElement(Link, { href: cancelHref, className: "px-5 py-2 bg-om-chip text-om-muted text-sm font-medium rounded-om-sm hover:bg-om-line2 transition-colors" }, __("Cancel")), onDelete && /* @__PURE__ */ React.createElement(Button, { type: "button", variant: "danger", onClick: onDelete, className: "ml-auto" }, __("Delete Connection"))));
}
export {
  OpcuaConnectionForm as default
};
