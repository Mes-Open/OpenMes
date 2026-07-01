import { Head, Link, usePage } from "@inertiajs/react";
import AppLayout from "../../../../layouts/AppLayout";
import { StatusDot } from "../ui";
import { __ } from "../../../../lib/i18n";
function ModbusIndex() {
  const { connections = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Modbus Connections") }), /* @__PURE__ */ React.createElement("div", { className: "p-6 space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/admin/connectivity",
      className: "text-sm text-om-muted hover:text-om-ink flex items-center gap-1 mb-1"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    __("All connectivity")
  ), /* @__PURE__ */ React.createElement("h1", { className: "text-2xl font-bold text-om-ink" }, __("Modbus TCP")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Poll registers from Modbus TCP devices and map them to machine signals."))), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/admin/connectivity/modbus/create",
      className: "inline-flex items-center gap-2 px-4 py-2 bg-om-ink text-om-on-ink text-sm font-medium rounded-om-sm hover:bg-om-ink-hover transition-colors"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M12 4v16m8-8H4" })),
    __("New Connection")
  )), connections.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "text-center py-16 text-om-faint" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm" }, __("No Modbus connections defined yet.")), /* @__PURE__ */ React.createElement(Link, { href: "/admin/connectivity/modbus/create", className: "mt-2 inline-block text-om-accent hover:underline text-sm" }, __("Create your first Modbus connection \u2192"))) : /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" }, connections.map((conn) => /* @__PURE__ */ React.createElement(
    "div",
    {
      key: conn.id,
      className: "bg-om-card rounded-om border border-om-line2 shadow-sm hover:shadow-md transition-shadow p-5 flex flex-col gap-3"
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 min-w-0" }, /* @__PURE__ */ React.createElement(StatusDot, { color: conn.status_color, pulse: conn.status === "connected" }), /* @__PURE__ */ React.createElement("h3", { className: "font-semibold text-om-ink truncate" }, conn.name)), !conn.is_active && /* @__PURE__ */ React.createElement("span", { className: "shrink-0 text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted" }, __("Inactive"))),
    conn.description && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted line-clamp-2" }, conn.description),
    conn.host && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint font-mono" }, conn.host, ":", conn.port, " \xB7 ", __("unit"), " ", conn.unit_id),
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between text-xs text-om-muted" }, /* @__PURE__ */ React.createElement("span", null, conn.tags_count, " ", conn.tags_count === 1 ? __("tag") : __("tags")), conn.last_connected_at && /* @__PURE__ */ React.createElement("span", null, conn.last_connected_at)),
    /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 pt-1 border-t border-om-line2 mt-auto" }, /* @__PURE__ */ React.createElement(
      Link,
      {
        href: `/admin/connectivity/modbus/${conn.id}`,
        className: "flex-1 text-center text-xs px-3 py-1.5 bg-om-chip text-om-accent rounded-md hover:bg-om-chip transition-colors font-medium"
      },
      __("View")
    ), /* @__PURE__ */ React.createElement(
      Link,
      {
        href: `/admin/connectivity/modbus/${conn.id}/edit`,
        className: "flex-1 text-center text-xs px-3 py-1.5 bg-om-panel text-om-muted rounded-md hover:bg-om-chip transition-colors font-medium"
      },
      __("Edit")
    ))
  )))));
}
ModbusIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ModbusIndex as default
};
