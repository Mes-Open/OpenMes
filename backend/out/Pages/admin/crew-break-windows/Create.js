import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { crewBreakWindowFields } from "./fields";
import { __ } from "../../../lib/i18n";
function CrewBreakWindowCreate() {
  const { crews = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Break Window") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Break Window")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/crew-break-windows",
      method: "post",
      fields: crewBreakWindowFields(crews),
      initial: {
        crew_id: "",
        name: "",
        start_time: "",
        end_time: "",
        days_of_week: [1, 2, 3, 4, 5],
        is_active: true
      },
      submitLabel: "Create",
      cancelHref: "/admin/crew-break-windows"
    }
  ));
}
CrewBreakWindowCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CrewBreakWindowCreate as default
};
