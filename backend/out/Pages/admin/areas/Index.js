import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function AreasIndex() {
  const { counts = {}, siteNames = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "site", label: __("Site"), className: "text-om-muted", render: (r) => siteNames[r.site_id] ?? "\u2014" },
    { key: "lines", label: __("Lines"), render: (r) => counts[r.id] ?? 0 },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/areas/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      icon: r.is_active ? "deactivate" : "activate",
      onClick: () => router.post(`/admin/areas/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete area ":name"?', { name: r.name }))) {
          router.delete(`/admin/areas/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Areas") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "areas",
      title: __("Areas"),
      createHref: "/admin/areas/create",
      createLabel: __("+ New Area"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No areas yet.")
    }
  ));
}
AreasIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  AreasIndex as default
};
