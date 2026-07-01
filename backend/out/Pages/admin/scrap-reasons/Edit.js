import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { scrapReasonFields } from "./fields";
import { __ } from "../../../lib/i18n";
function ScrapReasonEdit({ scrapReason }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit Scrap Reason") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Scrap Reason")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/scrap-reasons/${scrapReason.id}`,
      method: "put",
      fields: scrapReasonFields(),
      initial: {
        code: scrapReason.code ?? "",
        name: scrapReason.name ?? "",
        category: scrapReason.category ?? "",
        description: scrapReason.description ?? "",
        sort_order: scrapReason.sort_order ?? 0,
        is_active: !!scrapReason.is_active
      },
      submitLabel: __("Save Changes"),
      cancelHref: "/admin/scrap-reasons"
    }
  ));
}
ScrapReasonEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ScrapReasonEdit as default
};
