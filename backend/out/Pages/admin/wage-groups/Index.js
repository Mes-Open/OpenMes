import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function WageGroupsIndex() {
  const { counts = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "rate", label: __("Base Rate"), render: (r) => `${r.base_hourly_rate ?? "\u2014"} ${r.currency ?? ""}`.trim() },
    { key: "workers", label: __("Workers"), render: (r) => counts[r.id] ?? 0 },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/wage-groups/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      icon: r.is_active ? "deactivate" : "activate",
      onClick: () => router.post(`/admin/wage-groups/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete wage group ":name"?', { name: r.name }))) {
          router.delete(`/admin/wage-groups/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Wage Groups") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "wage_groups",
      title: __("Wage Groups"),
      createHref: "/admin/wage-groups/create",
      createLabel: __("+ New Wage Group"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No wage groups yet.")
    }
  ));
}
WageGroupsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WageGroupsIndex as default
};
