import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function LabelTemplatesIndex() {
  const { typeLabels = {} } = usePage().props;
  const columns = [
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "type", label: __("Type"), className: "text-om-muted", render: (r) => typeLabels[r.type] ?? r.type },
    { key: "size", label: __("Size"), className: "text-om-muted" },
    { key: "barcode_format", label: __("Barcode"), className: "font-mono text-om-muted" },
    { key: "is_default", label: __("Default"), render: (r) => r.is_default ? "\u2605" : "" },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/packaging/label-templates/${r.id}/edit` },
    ...r.is_default ? [] : [{ label: __("Make default"), onClick: () => router.post(`/packaging/label-templates/${r.id}/set-default`, {}, { preserveScroll: true }) }],
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete label template ":name"?', { name: r.name }))) router.delete(`/packaging/label-templates/${r.id}`, { preserveScroll: true });
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Label Templates") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "label_templates",
      title: __("Label Templates"),
      createHref: "/packaging/label-templates/create",
      createLabel: __("+ New Template"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No label templates yet.")
    }
  ));
}
LabelTemplatesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  LabelTemplatesIndex as default
};
