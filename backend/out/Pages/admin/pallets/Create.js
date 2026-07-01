import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import PalletForm from "./PalletForm";
function PalletCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Pallet" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Pallet"), /* @__PURE__ */ React.createElement(
    PalletForm,
    {
      action: "/admin/pallets",
      method: "post",
      initial: {
        work_order_id: "",
        batch_id: "",
        qty: 0,
        status: "open",
        location: "",
        erp_reference: ""
      },
      submitLabel: "Create"
    }
  ));
}
PalletCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  PalletCreate as default
};
