import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { absenceFields } from "./fields";
function WorkerAbsenceCreate() {
  const { workers = [], types = [], statuses = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "New Absence" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "New Absence"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/worker-absences",
      method: "post",
      fields: absenceFields(workers, types, statuses),
      initial: {
        worker_id: "",
        type: "vacation",
        starts_on: "",
        ends_on: "",
        all_day: true,
        start_time: "",
        end_time: "",
        status: "approved",
        reason: ""
      },
      submitLabel: "Create",
      cancelHref: "/admin/worker-absences"
    }
  ));
}
WorkerAbsenceCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkerAbsenceCreate as default
};
