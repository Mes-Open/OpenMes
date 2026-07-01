import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function asArray(v) {
  if (Array.isArray(v)) return v;
  if (typeof v === "string" && v) {
    try {
      const p = JSON.parse(v);
      return Array.isArray(p) ? p : [];
    } catch {
      return [];
    }
  }
  return [];
}
function planStatus(r) {
  if (!r.published_at) return { label: __("Draft"), cls: "bg-om-downtime-bg text-om-downtime" };
  if (r.is_active) return { label: __("Published"), cls: "bg-om-running-bg text-om-running" };
  return { label: __("Archived"), cls: "bg-om-chip text-om-muted" };
}
function InspectionPlansIndex() {
  const { materialNames = {}, materialTypeNames = {} } = usePage().props;
  const columns = [
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    {
      key: "version",
      label: __("Version"),
      className: "text-om-muted font-mono",
      render: (r) => `v${r.version ?? 1}`
    },
    {
      key: "scope",
      label: __("Scope"),
      className: "text-om-muted",
      render: (r) => r.material_id ? `${__("Material")}: ${materialNames[r.material_id] ?? "?"}` : r.material_type_id ? `${__("Type")}: ${materialTypeNames[r.material_type_id] ?? "?"}` : __("Generic")
    },
    { key: "criteria", label: __("Criteria"), render: (r) => asArray(r.criteria).length },
    {
      key: "status",
      label: __("Status"),
      render: (r) => {
        const s = planStatus(r);
        return /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded-full text-xs font-medium ${s.cls}` }, s.label);
      }
    }
  ];
  const actions = (r) => {
    const items = [{ label: __("Edit"), icon: "edit", href: `/admin/inspection-plans/${r.id}/edit` }];
    if (!r.published_at) {
      items.push({
        label: __("Publish"),
        className: "btn-touch text-sm bg-om-running-bg text-om-running hover:bg-om-running-bg",
        onClick: () => {
          if (confirm(__("Publish this version? It becomes the live plan used for new inspections."))) {
            router.post(`/admin/inspection-plans/${r.id}/publish`, {}, { preserveScroll: true });
          }
        }
      });
    }
    items.push({
      label: __("Delete"),
      icon: "delete",
      onClick: () => {
        if (confirm(__('Delete inspection plan ":name"?', { name: r.name }))) {
          router.delete(`/admin/inspection-plans/${r.id}`, { preserveScroll: true });
        }
      }
    });
    return items;
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Inspection Plans") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "inspection_plans",
      title: __("Inspection Plans"),
      createHref: "/admin/inspection-plans/create",
      createLabel: `+ ${__("New Plan")}`,
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No inspection plans yet.")
    }
  ));
}
InspectionPlansIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  InspectionPlansIndex as default
};
