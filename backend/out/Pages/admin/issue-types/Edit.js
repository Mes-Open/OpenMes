import { Head } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { ISSUE_TYPE_FIELDS } from "./fields";
function IssueTypeEdit({ issueType }) {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${issueType.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "Edit Issue Type"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/issue-types/${issueType.id}`,
      method: "put",
      fields: ISSUE_TYPE_FIELDS,
      initial: {
        code: issueType.code ?? "",
        name: issueType.name ?? "",
        severity: issueType.severity ?? "MEDIUM",
        is_blocking: !!issueType.is_blocking,
        is_active: !!issueType.is_active
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/issue-types"
    }
  ));
}
IssueTypeEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  IssueTypeEdit as default
};
