import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { absenceFields } from "./fields";
function WorkerAbsenceEdit({ absence }) {
  const { workers = [], types = [], statuses = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "Edit Absence" }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, "Edit Absence"), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/worker-absences/${absence.id}`,
      method: "put",
      fields: absenceFields(workers, types, statuses),
      initial: {
        worker_id: absence.worker_id ? String(absence.worker_id) : "",
        type: absence.type ?? "vacation",
        starts_on: absence.starts_on ?? "",
        ends_on: absence.ends_on ?? "",
        all_day: !!absence.all_day,
        start_time: absence.start_time ?? "",
        end_time: absence.end_time ?? "",
        status: absence.status ?? "approved",
        reason: absence.reason ?? ""
      },
      submitLabel: "Save Changes",
      cancelHref: "/admin/worker-absences"
    }
  ));
}
WorkerAbsenceEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkerAbsenceEdit as default
};
