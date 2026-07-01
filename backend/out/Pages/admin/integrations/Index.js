import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function IntegrationsIndex() {
  const { counts = {} } = usePage().props;
  const columns = [
    { key: "system_type", label: __("Type"), className: "font-mono text-om-muted" },
    { key: "system_name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "materials", label: __("Materials"), render: (r) => counts[r.id] ?? 0 },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/integrations/${r.id}/edit` },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete integration ":name"?', { name: r.system_name }))) {
          router.delete(`/admin/integrations/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Integrations") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "integration_configs",
      title: __("Integrations"),
      createHref: "/admin/integrations/create",
      createLabel: __("+ New Integration"),
      columns,
      orderBy: "system_name",
      actions,
      emptyText: __("No integrations configured.")
    }
  ));
}
IntegrationsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  IntegrationsIndex as default
};
