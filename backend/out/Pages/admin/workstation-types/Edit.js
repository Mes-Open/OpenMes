import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { WORKSTATION_TYPE_FIELDS } from "./fields";
import { __ } from "../../../lib/i18n";
function WorkstationTypeEdit({ workstationType }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit Workstation Type: :name", { name: workstationType.name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Workstation Type")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/workstation-types/${workstationType.id}`,
      method: "put",
      fields: WORKSTATION_TYPE_FIELDS,
      initial: {
        code: workstationType.code ?? "",
        name: workstationType.name ?? "",
        description: workstationType.description ?? "",
        is_active: !!workstationType.is_active
      },
      submitLabel: __("Save Changes"),
      cancelHref: "/admin/workstation-types"
    }
  ));
}
WorkstationTypeEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkstationTypeEdit as default
};
