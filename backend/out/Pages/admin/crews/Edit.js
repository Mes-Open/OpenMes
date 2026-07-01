import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { crewFields } from "./fields";
import { __ } from "../../../lib/i18n";
function CrewEdit() {
  const { crew, divisions = [], users = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :name", { name: crew.name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Crew")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/crews/${crew.id}`,
      method: "put",
      fields: crewFields(divisions, users),
      initial: {
        code: crew.code ?? "",
        name: crew.name ?? "",
        division_id: crew.division_id != null ? String(crew.division_id) : "",
        leader_id: crew.leader_id != null ? String(crew.leader_id) : "",
        description: crew.description ?? "",
        is_active: !!crew.is_active
      },
      submitLabel: __("Save Changes"),
      cancelHref: "/admin/crews"
    }
  ));
}
CrewEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CrewEdit as default
};
