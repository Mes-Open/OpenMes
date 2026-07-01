import { Head, Link, usePage } from "@inertiajs/react";
import AppLayout from "../../../../layouts/AppLayout";
import TagManager from "../TagManager";
import RuntimePanel from "../RuntimePanel";
import { StatusDot, StatCard } from "../ui";
import { __ } from "../../../../lib/i18n";
function ModbusShow() {
  const { connection, workstations = [], runtime } = usePage().props;
  const modbus = connection.modbus;
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `${connection.name} \u2014 ${__("Modbus")}` }), /* @__PURE__ */ React.createElement("div", { className: "p-6 space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/admin/connectivity/modbus",
      className: "text-sm text-om-muted hover:text-om-ink flex items-center gap-1 mb-2"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    __("Modbus Connections")
  ), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement(StatusDot, { color: connection.status_color, pulse: connection.status === "connected", size: "w-3 h-3" }), /* @__PURE__ */ React.createElement("h1", { className: "text-2xl font-bold text-om-ink" }, connection.name), !connection.is_active && /* @__PURE__ */ React.createElement("span", { className: "text-xs px-2 py-0.5 bg-om-chip text-om-muted rounded-full" }, __("Inactive"))), modbus && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-sm text-om-muted font-mono" }, modbus.host, ":", modbus.port, " \xB7 ", __("unit"), " ", modbus.unit_id, " \xB7 ", __("poll"), " ", modbus.poll_interval_ms, "ms")), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/connectivity/modbus/${connection.id}/edit`,
      className: "px-4 py-2 text-sm font-medium bg-om-chip text-om-muted rounded-om-sm hover:bg-om-line2 transition-colors"
    },
    __("Edit")
  )), connection.status_message && /* @__PURE__ */ React.createElement("div", { className: "rounded-om-sm border border-om-line bg-om-blocked-bg px-4 py-3 text-sm text-om-blocked" }, connection.status_message), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-4" }, /* @__PURE__ */ React.createElement(StatCard, { value: connection.tags.length, label: __("Tags") }), /* @__PURE__ */ React.createElement(StatCard, { value: modbus ? `${modbus.byte_order}/${modbus.word_order}` : "\u2014", label: __("Byte / word order") }), /* @__PURE__ */ React.createElement(StatCard, { value: connection.status, label: connection.is_active ? __("Active") : __("Inactive"), capitalize: true })), /* @__PURE__ */ React.createElement(RuntimePanel, { runtime }), /* @__PURE__ */ React.createElement(
    TagManager,
    {
      connectionId: connection.id,
      tags: connection.tags,
      workstations,
      basePath: "/admin/connectivity/modbus",
      showRegisterType: true,
      addressLabel: __("Register address"),
      addressPlaceholder: "e.g. 40001"
    }
  )));
}
ModbusShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ModbusShow as default
};
