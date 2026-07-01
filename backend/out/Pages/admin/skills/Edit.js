import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { skillFields } from "./fields";
import { __ } from "../../../lib/i18n";
function SkillEdit({ skill }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :name", { name: skill.name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Skill")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/skills/${skill.id}`,
      method: "put",
      fields: skillFields(),
      initial: {
        code: skill.code ?? "",
        name: skill.name ?? "",
        description: skill.description ?? ""
      },
      submitLabel: __("Save Changes"),
      cancelHref: "/admin/skills"
    }
  ));
}
SkillEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SkillEdit as default
};
