import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
function MqttConnectionForm({ action, method, submitLabel, cancelHref, connection = null, onDelete }) {
  const mqtt = connection?.mqtt ?? null;
  const form = useForm({
    name: connection?.name ?? "",
    description: connection?.description ?? "",
    is_active: connection?.is_active ?? false,
    broker_host: mqtt?.broker_host ?? "",
    broker_port: String(mqtt?.broker_port ?? 1883),
    client_id: mqtt?.client_id ?? "",
    username: mqtt?.username ?? "",
    password: "",
    use_tls: mqtt?.use_tls ?? false,
    ca_cert: mqtt?.ca_cert ?? "",
    qos_default: String(mqtt?.qos_default ?? 0),
    keep_alive_seconds: String(mqtt?.keep_alive_seconds ?? 60),
    connect_timeout: String(mqtt?.connect_timeout ?? 10),
    reconnect_delay_seconds: String(mqtt?.reconnect_delay_seconds ?? 5),
    clean_session: mqtt?.clean_session ?? true
  });
  const { data, setData, errors, processing } = form;
  const submit = (e) => {
    e.preventDefault();
    form.submit(method, action);
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "space-y-6" }, /* @__PURE__ */ React.createElement(Section, { title: "General" }, /* @__PURE__ */ React.createElement(Field, { label: "Name", required: true, error: errors.name }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: data.name,
      onChange: (e) => setData("name", e.target.value),
      required: true,
      className: "form-input w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: "Description", error: errors.description }, /* @__PURE__ */ React.createElement(
    "textarea",
    {
      value: data.description,
      onChange: (e) => setData("description", e.target.value),
      rows: 2,
      className: "form-input w-full"
    }
  )), /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: data.is_active,
      onChange: (next) => setData("is_active", next),
      label: "Active (start listening on daemon start)"
    }
  )), /* @__PURE__ */ React.createElement(Section, { title: "Broker" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-4" }, /* @__PURE__ */ React.createElement("div", { className: "col-span-2" }, /* @__PURE__ */ React.createElement(Field, { label: "Host", required: true, error: errors.broker_host }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: data.broker_host,
      onChange: (e) => setData("broker_host", e.target.value),
      placeholder: "broker.example.com",
      required: true,
      className: "form-input w-full font-mono"
    }
  ))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(Field, { label: "Port", required: true, error: errors.broker_port }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      value: data.broker_port,
      onChange: (e) => setData("broker_port", e.target.value),
      min: "1",
      max: "65535",
      required: true,
      className: "form-input w-full font-mono"
    }
  )))), /* @__PURE__ */ React.createElement(Field, { label: "Client ID", error: errors.client_id }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: data.client_id,
      onChange: (e) => setData("client_id", e.target.value),
      placeholder: "Auto-generated if empty",
      className: "form-input w-full font-mono"
    }
  ))), /* @__PURE__ */ React.createElement(Section, { title: "Authentication" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(Field, { label: "Username", error: errors.username }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: data.username,
      onChange: (e) => setData("username", e.target.value),
      autoComplete: "off",
      className: "form-input w-full"
    }
  )), /* @__PURE__ */ React.createElement(
    Field,
    {
      label: /* @__PURE__ */ React.createElement(React.Fragment, null, "Password", mqtt?.has_password && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint font-normal ml-1" }, "(leave blank to keep current)")),
      error: errors.password
    },
    /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "password",
        value: data.password,
        onChange: (e) => setData("password", e.target.value),
        autoComplete: "new-password",
        className: "form-input w-full"
      }
    )
  ))), /* @__PURE__ */ React.createElement(Section, { title: "TLS / Security" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: data.use_tls,
      onChange: (next) => setData("use_tls", next),
      label: "Enable TLS (port 8883)"
    }
  ), data.use_tls && /* @__PURE__ */ React.createElement(Field, { label: "CA Certificate (PEM)", error: errors.ca_cert }, /* @__PURE__ */ React.createElement(
    "textarea",
    {
      value: data.ca_cert,
      onChange: (e) => setData("ca_cert", e.target.value),
      rows: 4,
      placeholder: "-----BEGIN CERTIFICATE-----",
      className: "form-input w-full text-xs font-mono"
    }
  ))), /* @__PURE__ */ React.createElement(Section, { title: "Advanced" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(Field, { label: "QoS default", error: errors.qos_default }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.qos_default == null ? "" : String(data.qos_default),
      onChange: (v) => setData("qos_default", v),
      options: [
        { value: "0", label: "QoS 0 \u2014 At most once" },
        { value: "1", label: "QoS 1 \u2014 At least once" },
        { value: "2", label: "QoS 2 \u2014 Exactly once" }
      ],
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: "Keep-alive (seconds)", error: errors.keep_alive_seconds }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      value: data.keep_alive_seconds,
      onChange: (e) => setData("keep_alive_seconds", e.target.value),
      min: "5",
      max: "3600",
      className: "form-input w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: "Connect timeout (seconds)", error: errors.connect_timeout }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      value: data.connect_timeout,
      onChange: (e) => setData("connect_timeout", e.target.value),
      min: "1",
      max: "120",
      className: "form-input w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: "Reconnect delay (seconds)", error: errors.reconnect_delay_seconds }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      value: data.reconnect_delay_seconds,
      onChange: (e) => setData("reconnect_delay_seconds", e.target.value),
      min: "1",
      max: "300",
      className: "form-input w-full"
    }
  ))), /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: data.clean_session,
      onChange: (next) => setData("clean_session", next),
      label: "Clean session (recommended for stateless connections)"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing }, processing ? "Saving\u2026" : submitLabel), /* @__PURE__ */ React.createElement(
    "a",
    {
      href: cancelHref,
      className: "px-5 py-2 bg-om-chip text-om-muted text-sm font-medium rounded-om-sm hover:bg-om-line2 transition-colors"
    },
    "Cancel"
  ), onDelete && /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "button",
      variant: "danger",
      onClick: onDelete,
      className: "ml-auto"
    },
    "Delete Connection"
  )));
}
function Section({ title, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 p-5 space-y-4" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wider" }, title), children);
}
function Field({ label, required, error, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), children, error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
export {
  MqttConnectionForm as default
};
