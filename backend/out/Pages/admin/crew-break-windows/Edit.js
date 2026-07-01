import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { crewBreakWindowFields } from "./fields";
import { __ } from "../../../lib/i18n";
function CrewBreakWindowEdit({ window }) {
  const { crews = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit Break Window") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Break Window")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/crew-break-windows/${window.id}`,
      method: "put",
      fields: crewBreakWindowFields(crews),
      initial: {
        crew_id: window.crew_id ? String(window.crew_id) : "",
        name: window.name ?? "",
        start_time: window.start_time ?? "",
        end_time: window.end_time ?? "",
        days_of_week: (window.days_of_week ?? []).map(Number),
        is_active: !!window.is_active
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/crew-break-windows"
    }
  ));
}
CrewBreakWindowEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CrewBreakWindowEdit as default
};
