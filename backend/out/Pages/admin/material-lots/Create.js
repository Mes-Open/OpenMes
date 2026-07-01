import { Head, usePage } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { materialLotFields } from "./fields";
function MaterialLotCreate() {
  const { materials = [], sources = [], statuses = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Material Lot") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Material Lot")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/material-lots",
      method: "post",
      fields: materialLotFields(materials, sources, statuses),
      initial: {
        lot_number: "",
        material_id: "",
        source_id: "",
        quantity_received: "",
        quantity_available: "",
        unit_of_measure: "pcs",
        received_at: "",
        manufacturing_date: "",
        expiry_date: "",
        status: "received",
        supplier_lot_no: "",
        supplier_reference: "",
        source_container_no: ""
      },
      submitLabel: "Create",
      cancelHref: "/admin/material-lots"
    }
  ));
}
MaterialLotCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaterialLotCreate as default
};
