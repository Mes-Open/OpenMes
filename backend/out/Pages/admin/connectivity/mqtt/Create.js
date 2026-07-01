import { Head } from "@inertiajs/react";
import AppLayout from "../../../../layouts/AppLayout";
import MqttConnectionForm from "./MqttConnectionForm";
function MqttCreate() {
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: "New MQTT Connection" }), /* @__PURE__ */ React.createElement("div", { className: "p-6 max-w-2xl" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: "/admin/connectivity/mqtt",
      className: "text-sm text-om-muted hover:text-om-ink flex items-center gap-1"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    "Back to MQTT Connections"
  ), /* @__PURE__ */ React.createElement("h1", { className: "mt-3 text-2xl font-bold text-om-ink" }, "New MQTT Connection")), /* @__PURE__ */ React.createElement(
    MqttConnectionForm,
    {
      action: "/admin/connectivity/mqtt",
      method: "post",
      submitLabel: "Create Connection",
      cancelHref: "/admin/connectivity/mqtt"
    }
  )));
}
MqttCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MqttCreate as default
};
