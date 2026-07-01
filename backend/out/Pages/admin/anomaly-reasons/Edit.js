import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { ANOMALY_REASON_FIELDS } from "./fields";
function AnomalyReasonEdit({ anomalyReason }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${anomalyReason.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "Edit Anomaly Reason"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/anomaly-reasons/${anomalyReason.id}`,
      method: "put",
      fields: ANOMALY_REASON_FIELDS,
      initial: {
        code: anomalyReason.code ?? "",
        name: anomalyReason.name ?? "",
        category: anomalyReason.category ?? "",
        description: anomalyReason.description ?? "",
        is_active: !!anomalyReason.is_active
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/anomaly-reasons"
    }
  ));
}
AnomalyReasonEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  AnomalyReasonEdit as default
};
