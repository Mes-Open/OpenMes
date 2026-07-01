import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { skillFields } from "./fields";
import { __ } from "../../../lib/i18n";
function SkillCreate() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Skill") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Skill")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/skills",
      method: "post",
      fields: skillFields(),
      initial: { code: "", name: "", description: "" },
      submitLabel: __("Create"),
      cancelHref: "/admin/skills"
    }
  ));
}
SkillCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SkillCreate as default
};
