import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { TRIGGER_TYPE_LABELS } from "./fields";
import { __ } from "../../../lib/i18n";
function QualityControlTriggersIndex() {
  const {
    templateNames = {},
    lineNames = {},
    workstationNames = {},
    productTypeNames = {}
  } = usePage().props;
  const scope = (r) => {
    const parts = [];
    if (r.line_id) parts.push(lineNames[r.line_id] ?? `#${r.line_id}`);
    if (r.workstation_id) parts.push(workstationNames[r.workstation_id] ?? `#${r.workstation_id}`);
    if (r.product_type_id) parts.push(productTypeNames[r.product_type_id] ?? `#${r.product_type_id}`);
    return parts.length ? parts.join(" \xB7 ") : __("Any");
  };
  const columns = [
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    {
      key: "trigger_type",
      label: __("Type"),
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: "text-xs px-2 py-0.5 rounded font-medium bg-om-line2 text-om-muted" }, TRIGGER_TYPE_LABELS[r.trigger_type] ?? r.trigger_type)
    },
    {
      key: "threshold_n",
      label: __("N"),
      className: "text-om-muted",
      render: (r) => r.threshold_n ?? "\u2014"
    },
    {
      key: "quality_check_template_id",
      label: __("Control"),
      className: "text-om-muted",
      render: (r) => templateNames[r.quality_check_template_id] ?? "\u2014"
    },
    { key: "scope", label: __("Scope"), className: "text-om-muted", render: scope },
    { key: "is_blocking", label: __("Blocking"), render: (r) => r.is_blocking ? __("Yes") : __("No") },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/quality-control-triggers/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      icon: r.is_active ? "deactivate" : "activate",
      onClick: () => router.post(`/admin/quality-control-triggers/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete trigger ":name"?', { name: r.name }))) {
          router.delete(`/admin/quality-control-triggers/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Quality Control Triggers") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "quality_control_triggers",
      title: __("Quality Control Triggers"),
      createHref: "/admin/quality-control-triggers/create",
      createLabel: __("+ New Trigger"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No quality control triggers yet.")
    }
  ));
}
QualityControlTriggersIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  QualityControlTriggersIndex as default
};
