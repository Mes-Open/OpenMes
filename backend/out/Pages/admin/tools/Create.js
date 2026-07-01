import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { toolFields } from "./fields";
function ToolCreate() {
  const { workstationTypes = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Tool" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Tool"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/tools",
      method: "post",
      fields: toolFields(workstationTypes),
      initial: { code: "", name: "", description: "", workstation_type_id: "", status: "available", next_service_at: "" },
      submitLabel: "Create",
      cancelHref: "/admin/tools"
    }
  ));
}
ToolCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ToolCreate as default
};
