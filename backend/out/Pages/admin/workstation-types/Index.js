import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function WorkstationTypesIndex() {
  const { counts = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "workstations", label: __("Workstations"), render: (r) => counts[r.id] ?? 0 },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/workstation-types/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      icon: r.is_active ? "deactivate" : "activate",
      onClick: () => router.post(`/admin/workstation-types/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete workstation type ":name"?', { name: r.name }))) {
          router.delete(`/admin/workstation-types/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Workstation Types") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "workstation_types",
      title: __("Workstation Types"),
      createHref: "/admin/workstation-types/create",
      createLabel: __("+ New Type"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No workstation types yet.")
    }
  ));
}
WorkstationTypesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkstationTypesIndex as default
};
