import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import LotSequenceForm from "./LotSequenceForm";
import { __ } from "../../../lib/i18n";
function LotSequenceCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New LOT Sequence") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New LOT Sequence")), /* @__PURE__ */ React.createElement(
    LotSequenceForm,
    {
      action: "/admin/lot-sequences",
      method: "post",
      initial: {
        name: "",
        product_type_id: "",
        pattern: "",
        prefix: "",
        suffix: "",
        pad_size: 4,
        year_prefix: false,
        reset_period: "none"
      },
      submitLabel: __("Create")
    }
  ));
}
LotSequenceCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  LotSequenceCreate as default
};
