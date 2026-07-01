import { Head, Link } from "@inertiajs/react";
import AppLayout from "../../../../layouts/AppLayout";
import OpcuaConnectionForm from "./OpcuaConnectionForm";
import { __ } from "../../../../lib/i18n";
function OpcuaCreate() {
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("New OPC UA Connection") }), /* @__PURE__ */ React.createElement("div", { className: "p-6 max-w-2xl" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/admin/connectivity/opcua",
      className: "text-sm text-om-muted hover:text-om-ink flex items-center gap-1"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    __("Back to OPC UA Connections")
  ), /* @__PURE__ */ React.createElement("h1", { className: "mt-3 text-2xl font-bold text-om-ink" }, __("New OPC UA Connection"))), /* @__PURE__ */ React.createElement(
    OpcuaConnectionForm,
    {
      action: "/admin/connectivity/opcua",
      method: "post",
      submitLabel: __("Create Connection"),
      cancelHref: "/admin/connectivity/opcua"
    }
  )));
}
OpcuaCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  OpcuaCreate as default
};
