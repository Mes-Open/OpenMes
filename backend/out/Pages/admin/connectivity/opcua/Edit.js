import { Head, Link, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../../layouts/AppLayout";
import OpcuaConnectionForm from "./OpcuaConnectionForm";
import { __ } from "../../../../lib/i18n";
function OpcuaEdit() {
  const { connection } = usePage().props;
  const handleDelete = () => {
    if (confirm(__("Delete this connection and all its tags?"))) {
      router.delete(`/admin/connectivity/opcua/${connection.id}`);
    }
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Edit: :name", { name: connection.name }) }), /* @__PURE__ */ React.createElement("div", { className: "p-6 max-w-2xl" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/connectivity/opcua/${connection.id}`,
      className: "text-sm text-om-muted hover:text-om-ink flex items-center gap-1"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    __("Back to :name", { name: connection.name })
  ), /* @__PURE__ */ React.createElement("h1", { className: "mt-3 text-2xl font-bold text-om-ink" }, __("Edit: :name", { name: connection.name }))), /* @__PURE__ */ React.createElement(
    OpcuaConnectionForm,
    {
      action: `/admin/connectivity/opcua/${connection.id}`,
      method: "put",
      submitLabel: __("Save Changes"),
      cancelHref: `/admin/connectivity/opcua/${connection.id}`,
      connection,
      onDelete: handleDelete
    }
  )));
}
OpcuaEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  OpcuaEdit as default
};
