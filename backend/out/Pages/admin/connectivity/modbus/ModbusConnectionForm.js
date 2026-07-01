import { Link, useForm } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
import { Section, Field } from "../ui";
import { __ } from "../../../../lib/i18n";
function ModbusConnectionForm({ action, method, submitLabel, cancelHref, connection = null, onDelete }) {
  const modbus = connection?.modbus ?? null;
  const form = useForm({
    name: connection?.name ?? "",
    description: connection?.description ?? "",
    is_active: connection?.is_active ?? false,
    host: modbus?.host ?? "",
    port: String(modbus?.port ?? 502),
    unit_id: String(modbus?.unit_id ?? 1),
    poll_interval_ms: String(modbus?.poll_interval_ms ?? 1e3),
    timeout_seconds: String(modbus?.timeout_seconds ?? 5),
    byte_order: modbus?.byte_order ?? "big",
    word_order: modbus?.word_order ?? "big"
  });
  const { data, setData, errors, processing } = form;
  const submit = (e) => {
    e.preventDefault();
    form.submit(method, action);
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "space-y-6" }, /* @__PURE__ */ React.createElement(Section, { title: __("General") }, /* @__PURE__ */ React.createElement(Field, { label: __("Name"), required: true, error: errors.name }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), required: true, className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Description"), error: errors.description }, /* @__PURE__ */ React.createElement("textarea", { value: data.description, onChange: (e) => setData("description", e.target.value), rows: 2, className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Checkbox, { checked: data.is_active, onChange: (next) => setData("is_active", next), label: __("Active (start polling on daemon start)") })), /* @__PURE__ */ React.createElement(Section, { title: __("Device") }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-4" }, /* @__PURE__ */ React.createElement("div", { className: "col-span-2" }, /* @__PURE__ */ React.createElement(Field, { label: __("Host"), required: true, error: errors.host }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.host, onChange: (e) => setData("host", e.target.value), placeholder: "192.168.1.50", required: true, className: "form-input w-full font-mono" }))), /* @__PURE__ */ React.createElement(Field, { label: __("Port"), required: true, error: errors.port }, /* @__PURE__ */ React.createElement("input", { type: "number", value: data.port, onChange: (e) => setData("port", e.target.value), min: "1", max: "65535", required: true, className: "form-input w-full font-mono" }))), /* @__PURE__ */ React.createElement(Field, { label: __("Unit ID (slave address)"), required: true, error: errors.unit_id }, /* @__PURE__ */ React.createElement("input", { type: "number", value: data.unit_id, onChange: (e) => setData("unit_id", e.target.value), min: "0", max: "255", required: true, className: "form-input w-full font-mono" }))), /* @__PURE__ */ React.createElement(Section, { title: __("Polling & encoding") }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(Field, { label: __("Poll interval (ms)"), required: true, error: errors.poll_interval_ms }, /* @__PURE__ */ React.createElement("input", { type: "number", value: data.poll_interval_ms, onChange: (e) => setData("poll_interval_ms", e.target.value), min: "100", max: "60000", required: true, className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Timeout (seconds)"), required: true, error: errors.timeout_seconds }, /* @__PURE__ */ React.createElement("input", { type: "number", value: data.timeout_seconds, onChange: (e) => setData("timeout_seconds", e.target.value), min: "1", max: "60", required: true, className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Byte order"), required: true, error: errors.byte_order }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [
        { value: "big", label: __("Big-endian") },
        { value: "little", label: __("Little-endian") }
      ],
      value: data.byte_order == null ? "" : String(data.byte_order),
      onChange: (v) => setData("byte_order", v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("Word order"), required: true, error: errors.word_order }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [
        { value: "big", label: __("Big-endian (high word first)") },
        { value: "little", label: __("Little-endian (low word first)") }
      ],
      value: data.word_order == null ? "" : String(data.word_order),
      onChange: (v) => setData("word_order", v),
      className: "w-full"
    }
  )))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { variant: "primary", type: "submit", loading: processing }, processing ? __("Saving\u2026") : submitLabel), /* @__PURE__ */ React.createElement(Link, { href: cancelHref, className: "px-5 py-2 bg-om-chip text-om-muted text-sm font-medium rounded-om-sm hover:bg-om-line2 transition-colors" }, __("Cancel")), onDelete && /* @__PURE__ */ React.createElement(Button, { variant: "danger", type: "button", onClick: onDelete, className: "ml-auto" }, __("Delete Connection"))));
}
export {
  ModbusConnectionForm as default
};
