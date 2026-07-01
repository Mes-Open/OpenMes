import { Head, usePage } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { lineFields } from "./fields";
function LineCreate() {
  const { areas = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Production Line") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Production Line")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/lines",
      method: "post",
      fields: lineFields(areas),
      initial: { code: "", name: "", area_id: "", description: "", is_active: true },
      submitLabel: "Create",
      cancelHref: "/admin/lines"
    }
  ));
}
LineCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  LineCreate as default
};
