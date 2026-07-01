import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function CostSourcesIndex() {
  const { counts = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "unit_cost", label: __("Unit Cost"), render: (r) => `${r.unit_cost ?? "\u2014"} ${r.currency ?? ""}`.trim() },
    { key: "unit", label: __("Unit"), className: "text-om-muted" },
    { key: "used", label: __("Used"), render: (r) => counts[r.id] ?? 0 },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/cost-sources/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      icon: r.is_active ? "deactivate" : "activate",
      onClick: () => router.post(`/admin/cost-sources/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete cost source ":name"?', { name: r.name }))) {
          router.delete(`/admin/cost-sources/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Cost Sources") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "cost_sources",
      title: __("Cost Sources"),
      createHref: "/admin/cost-sources/create",
      createLabel: __("+ New Cost Source"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No cost sources yet.")
    }
  ));
}
CostSourcesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CostSourcesIndex as default
};
