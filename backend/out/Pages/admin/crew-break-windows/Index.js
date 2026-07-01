import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { formatDays } from "./fields";
import { __ } from "../../../lib/i18n";
function CrewBreakWindowsIndex() {
  const { crewNames = {} } = usePage().props;
  const time = (t) => t ? String(t).slice(0, 5) : "";
  const columns = [
    {
      key: "crew",
      label: "Crew",
      className: "font-medium text-om-ink",
      render: (r) => crewNames[r.crew_id] ?? `#${r.crew_id}`
    },
    { key: "name", label: "Name" },
    {
      key: "time",
      label: "Time",
      render: (r) => `${time(r.start_time)}\u2013${time(r.end_time)}`
    },
    {
      key: "days",
      label: "Days",
      className: "text-om-muted",
      render: (r) => formatDays(r.days_of_week ?? [])
    },
    {
      key: "is_active",
      label: "Status",
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded text-xs ${r.is_active ? "bg-om-running-bg text-om-running" : "bg-om-chip text-om-muted"}` }, r.is_active ? __("Active") : __("Inactive"))
    }
  ];
  const actions = (r) => [
    { label: "Edit", icon: "edit", href: `/admin/crew-break-windows/${r.id}/edit` },
    {
      label: "Delete",
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__("Delete this break window?"))) {
          router.delete(`/admin/crew-break-windows/${r.id}`, {
            preserveScroll: true,
            onError: (e) => alert(e?.message || __("Failed to delete."))
          });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Crew Break Windows") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "crew_break_windows",
      title: __("Crew Break Windows"),
      createHref: "/admin/crew-break-windows/create",
      createLabel: __("+ New Break Window"),
      columns,
      orderBy: "start_time",
      actions,
      emptyText: __("No break windows defined yet.")
    }
  ));
}
CrewBreakWindowsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CrewBreakWindowsIndex as default
};
