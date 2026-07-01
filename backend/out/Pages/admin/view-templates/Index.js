import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function ViewTemplatesIndex() {
  const { counts = {} } = usePage().props;
  const columns = [
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "description", label: __("Description"), className: "text-om-muted" },
    { key: "lines", label: __("Lines using"), render: (r) => counts[r.id] ?? 0 }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/view-templates/${r.id}/edit` },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete view template ":name"?', { name: r.name }))) router.delete(`/admin/view-templates/${r.id}`, { preserveScroll: true });
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("View Templates") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "view_templates",
      title: __("View Templates"),
      createHref: "/admin/view-templates/create",
      createLabel: __("+ New Template"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No view templates yet.")
    }
  ));
}
ViewTemplatesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ViewTemplatesIndex as default
};
