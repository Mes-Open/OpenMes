import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import PalletForm from "./PalletForm";
import LabelPrintMenu from "../../../components/LabelPrintMenu";
function PalletEdit() {
  const { pallet, labelTemplates = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${pallet.pallet_no}` }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-6" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, "Edit Pallet ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-2xl text-om-muted" }, pallet.pallet_no)), /* @__PURE__ */ React.createElement(LabelPrintMenu, { kind: "pallet", id: pallet.id, templates: labelTemplates, label: "Print label" })), /* @__PURE__ */ React.createElement(
    PalletForm,
    {
      action: `/admin/pallets/${pallet.id}`,
      method: "put",
      initial: {
        work_order_id: pallet.work_order_id != null ? String(pallet.work_order_id) : "",
        batch_id: pallet.batch_id != null ? String(pallet.batch_id) : "",
        qty: pallet.qty ?? 0,
        status: pallet.status ?? "open",
        location: pallet.location ?? "",
        erp_reference: pallet.erp_reference ?? ""
      },
      submitLabel: "Save Changes"
    }
  ));
}
PalletEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  PalletEdit as default
};
