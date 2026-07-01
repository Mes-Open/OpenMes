import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { TRACKING_LABELS } from "./fields";
import { __ } from "../../../lib/i18n";
function MaterialsIndex() {
  const { counts = {}, materialTypeNames = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "type", label: __("Type"), className: "text-om-muted", render: (r) => materialTypeNames[r.material_type_id] ?? "\u2014" },
    { key: "unit_of_measure", label: __("UoM"), className: "text-om-muted" },
    { key: "tracking_type", label: __("Tracking"), className: "text-om-muted", render: (r) => TRACKING_LABELS[r.tracking_type] ?? r.tracking_type ?? "\u2014" },
    { key: "stock_quantity", label: __("Stock"), className: "text-om-muted", render: (r) => r.stock_quantity ?? "\u2014" },
    { key: "bom", label: __("In BOMs"), render: (r) => counts[r.id] ?? 0 },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/materials/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      icon: r.is_active ? "deactivate" : "activate",
      onClick: () => router.post(`/admin/materials/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete material ":name"?', { name: r.name }))) {
          router.delete(`/admin/materials/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Materials") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "materials",
      title: __("Materials"),
      createHref: "/admin/materials/create",
      createLabel: __("+ New Material"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No materials yet.")
    }
  ));
}
MaterialsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaterialsIndex as default
};
