import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { crewFields } from "./fields";
import { __ } from "../../../lib/i18n";
function CrewCreate() {
  const { divisions = [], users = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Crew") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Crew")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/crews",
      method: "post",
      fields: crewFields(divisions, users),
      initial: { code: "", name: "", division_id: "", leader_id: "", description: "", is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/crews"
    }
  ));
}
CrewCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CrewCreate as default
};
