import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
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
function PersonnelClassesIndex() {
  const { counts = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "skills", label: __("Req. Skills"), render: (r) => asArray(r.required_skill_ids).length },
    { key: "workers", label: __("Workers"), render: (r) => counts[r.id] ?? 0 },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/personnel-classes/${r.id}/edit` },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete personnel class ":name"?', { name: r.name }))) {
          router.delete(`/admin/personnel-classes/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Personnel Classes") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "personnel_classes",
      title: __("Personnel Classes"),
      createHref: "/admin/personnel-classes/create",
      createLabel: __("+ New Class"),
      columns,
      orderBy: "code",
      actions,
      emptyText: __("No personnel classes yet.")
    }
  ));
}
PersonnelClassesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  PersonnelClassesIndex as default
};
