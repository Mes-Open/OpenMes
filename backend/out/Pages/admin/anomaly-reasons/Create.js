import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { ANOMALY_REASON_FIELDS } from "./fields";
import { __ } from "../../../lib/i18n";
function AnomalyReasonCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Anomaly Reason") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Anomaly Reason")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/anomaly-reasons",
      method: "post",
      fields: ANOMALY_REASON_FIELDS,
      initial: { code: "", name: "", category: "", description: "", is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/anomaly-reasons"
    }
  ));
}
AnomalyReasonCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  AnomalyReasonCreate as default
};
