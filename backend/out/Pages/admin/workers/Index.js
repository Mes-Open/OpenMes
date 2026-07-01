import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function WorkersIndex() {
  const { crewNames = {}, wageGroupNames = {}, personnelClassNames = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "email", label: __("Email"), className: "text-om-muted" },
    { key: "crew", label: __("Crew"), className: "text-om-muted", render: (r) => crewNames[r.crew_id] ?? "\u2014" },
    { key: "class", label: __("Class"), className: "text-om-muted", render: (r) => personnelClassNames[r.personnel_class_id] ?? "\u2014" },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/workers/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      icon: r.is_active ? "deactivate" : "activate",
      onClick: () => router.post(`/admin/workers/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete worker ":name"?', { name: r.name }))) {
          router.delete(`/admin/workers/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Workers") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "workers",
      title: __("Workers"),
      createHref: "/admin/workers/create",
      createLabel: __("+ New Worker"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No workers yet.")
    }
  ));
}
WorkersIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkersIndex as default
};
