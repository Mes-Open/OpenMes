import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { SEVERITY_LABELS } from "./fields";
import { __ } from "../../../lib/i18n";
function severityBadgeClass(severity) {
  if (severity === "CRITICAL" || severity === "HIGH") {
    return "bg-om-blocked-bg text-om-blocked";
  }
  if (severity === "MEDIUM") {
    return "bg-om-downtime-bg text-om-downtime";
  }
  return "bg-om-line2 text-om-muted";
}
function IssueTypesIndex() {
  const { counts = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    {
      key: "severity",
      label: __("Severity"),
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: `text-xs px-2 py-0.5 rounded font-medium ${severityBadgeClass(r.severity)}` }, SEVERITY_LABELS[r.severity] ?? r.severity)
    },
    { key: "is_blocking", label: __("Blocking"), render: (r) => r.is_blocking ? __("Yes") : __("No") },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/issue-types/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      icon: r.is_active ? "deactivate" : "activate",
      onClick: () => router.post(`/admin/issue-types/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete issue type ":name"?', { name: r.name }))) {
          router.delete(`/admin/issue-types/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Issue Types") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "issue_types_all",
      title: __("Issue Types"),
      createHref: "/admin/issue-types/create",
      createLabel: __("+ New Issue Type"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No issue types yet.")
    }
  ));
}
IssueTypesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  IssueTypesIndex as default
};
