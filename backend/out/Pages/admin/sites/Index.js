import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function SitesIndex() {
  const { counts = {}, companyNames = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "company", label: __("Company"), className: "text-om-muted", render: (r) => companyNames[r.company_id] ?? "\u2014" },
    { key: "city", label: __("City"), className: "text-om-muted" },
    { key: "areas", label: __("Areas"), render: (r) => counts[r.id]?.areas ?? 0 },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/sites/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      icon: r.is_active ? "deactivate" : "activate",
      onClick: () => router.post(`/admin/sites/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete site ":name"?', { name: r.name }))) {
          router.delete(`/admin/sites/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Sites") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "sites",
      title: __("Sites"),
      createHref: "/admin/sites/create",
      createLabel: __("+ New Site"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No sites yet.")
    }
  ));
}
SitesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SitesIndex as default
};
