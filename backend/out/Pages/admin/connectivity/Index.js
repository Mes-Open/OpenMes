import { Head, Link, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import { StatusDot } from "./ui";
import { __, formatNumber } from "../../../lib/i18n";
const PROTOCOL_LABELS = {
  mqtt: "MQTT",
  modbus: "Modbus",
  opcua: "OPC UA",
  rest: "REST"
};
function ConnectivityIndex() {
  const { connections = [] } = usePage().props;
  const counts = connections.reduce((acc, c) => {
    acc[c.protocol] = (acc[c.protocol] ?? 0) + 1;
    return acc;
  }, {});
  const handleToggle = (conn) => {
    if (conn.protocol !== "mqtt") return;
    router.post(`/admin/connectivity/mqtt/${conn.id}/toggle-active`, {}, { preserveScroll: true });
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Machine Connectivity") }), /* @__PURE__ */ React.createElement("div", { className: "p-6 space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-2xl font-bold text-om-ink" }, __("Machine Connectivity")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Overview of all machine communication channels.")))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 border-b border-om-line2" }, /* @__PURE__ */ React.createElement("span", { className: "px-4 py-2 text-sm font-medium border-b-2 border-om-accent text-om-accent" }, __("All"), " (", connections.length, ")"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/connectivity/mqtt", className: "px-4 py-2 text-sm font-medium border-b-2 border-transparent text-om-muted hover:text-om-ink transition-colors" }, "MQTT (", counts.mqtt ?? 0, ")"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/connectivity/modbus", className: "px-4 py-2 text-sm font-medium border-b-2 border-transparent text-om-muted hover:text-om-ink transition-colors" }, "Modbus (", counts.modbus ?? 0, ")"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/connectivity/opcua", className: "px-4 py-2 text-sm font-medium border-b-2 border-transparent text-om-muted hover:text-om-ink transition-colors" }, "OPC UA (", counts.opcua ?? 0, ")")), connections.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "text-center py-16 text-om-faint" }, /* @__PURE__ */ React.createElement("svg", { className: "w-12 h-12 mx-auto mb-3 opacity-40", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "1.5", d: "M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" })), /* @__PURE__ */ React.createElement("p", { className: "text-sm" }, __("No connections defined yet.")), /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-sm" }, __("Pick a protocol tab above to add one."))) : /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" }, connections.map((conn) => {
    const base = `/admin/connectivity/${conn.protocol}`;
    const count = conn.protocol === "mqtt" ? conn.topics_count : conn.tags_count;
    const countLabel = conn.protocol === "mqtt" ? count === 1 ? __("topic") : __("topics") : count === 1 ? __("tag") : __("tags");
    return /* @__PURE__ */ React.createElement(
      "div",
      {
        key: conn.id,
        className: "bg-om-card rounded-om border border-om-line2 shadow-sm hover:shadow-md transition-shadow p-5 flex flex-col gap-3"
      },
      /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 min-w-0" }, /* @__PURE__ */ React.createElement(StatusDot, { color: conn.status_color, pulse: conn.status === "connected" }), /* @__PURE__ */ React.createElement("h3", { className: "font-semibold text-om-ink truncate" }, conn.name)), /* @__PURE__ */ React.createElement("span", { className: "shrink-0 text-xs px-2 py-0.5 rounded-full font-medium uppercase tracking-wide bg-om-chip text-om-muted" }, PROTOCOL_LABELS[conn.protocol] ?? conn.protocol)),
      conn.description && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted line-clamp-2" }, conn.description),
      conn.endpoint && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint font-mono truncate", title: conn.endpoint }, conn.endpoint),
      /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between text-xs text-om-muted" }, /* @__PURE__ */ React.createElement("span", null, count ?? 0, " ", countLabel), conn.protocol === "mqtt" && /* @__PURE__ */ React.createElement("span", null, formatNumber(Number(conn.messages_received)), " ", __("msg"))),
      conn.last_connected_at && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint" }, __("Last connected:"), " ", conn.last_connected_at),
      /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 pt-1 border-t border-om-line2 mt-auto" }, /* @__PURE__ */ React.createElement(
        Link,
        {
          href: `${base}/${conn.id}`,
          className: "flex-1 text-center text-xs px-3 py-1.5 bg-om-chip text-om-accent rounded-md hover:bg-om-chip transition-colors font-medium"
        },
        __("View")
      ), /* @__PURE__ */ React.createElement(
        Link,
        {
          href: `${base}/${conn.id}/edit`,
          className: "flex-1 text-center text-xs px-3 py-1.5 bg-om-panel text-om-muted rounded-md hover:bg-om-chip transition-colors font-medium"
        },
        __("Edit")
      ), conn.protocol === "mqtt" && /* @__PURE__ */ React.createElement(
        "button",
        {
          type: "button",
          onClick: () => handleToggle(conn),
          className: `text-xs px-3 py-1.5 rounded-md font-medium transition-colors ${conn.is_active ? "bg-om-blocked-bg text-om-blocked hover:bg-om-blocked-bg" : "bg-om-running-bg text-om-running hover:bg-om-running-bg"}`
        },
        conn.is_active ? __("Disable") : __("Enable")
      ))
    );
  }))));
}
ConnectivityIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ConnectivityIndex as default
};
