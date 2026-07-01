import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { ABSENCE_TYPE_LABELS, ABSENCE_STATUS_STYLES } from "./fields";
function WorkerAbsencesIndex() {
  const { workerNames = {} } = usePage().props;
  const fmt = (d) => d ?? "\u2014";
  const time = (t) => t ? String(t).slice(0, 5) : "";
  const columns = [
    {
      key: "worker",
      label: "Worker",
      className: "font-medium text-om-ink",
      render: (r) => workerNames[r.worker_id] ?? `#${r.worker_id}`
    },
    { key: "type", label: "Type", render: (r) => ABSENCE_TYPE_LABELS[r.type] ?? r.type },
    {
      key: "range",
      label: "Dates",
      render: (r) => r.starts_on === r.ends_on ? fmt(r.starts_on) : `${fmt(r.starts_on)} \u2192 ${fmt(r.ends_on)}`
    },
    {
      key: "span",
      label: "Span",
      className: "text-om-muted",
      render: (r) => r.all_day ? "All day" : `${time(r.start_time)}\u2013${time(r.end_time)}`
    },
    {
      key: "status",
      label: "Status",
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded text-xs ${ABSENCE_STATUS_STYLES[r.status] ?? "bg-om-chip text-om-muted"}` }, r.status)
    }
  ];
  const actions = (r) => [
    { label: "Edit", icon: "edit", href: `/admin/worker-absences/${r.id}/edit` },
    {
      label: "Delete",
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm("Delete this absence?")) {
          router.delete(`/admin/worker-absences/${r.id}`, {
            preserveScroll: true,
            onError: (e) => alert(e?.message || "Failed to delete.")
          });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: "Worker Absences" }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "worker_absences",
      title: "Worker Absences",
      createHref: "/admin/worker-absences/create",
      createLabel: "+ New Absence",
      columns,
      orderBy: "starts_on",
      actions,
      emptyText: "No absences recorded yet."
    }
  ));
}
WorkerAbsencesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkerAbsencesIndex as default
};
