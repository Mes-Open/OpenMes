import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function SkillsIndex() {
  const { counts = {} } = usePage().props;
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "description", label: __("Description"), className: "text-om-muted" },
    { key: "workers", label: __("Workers"), render: (r) => counts[r.id] ?? 0 }
  ];
  const actions = (r) => [
    { label: __("Edit"), icon: "edit", href: `/admin/skills/${r.id}/edit` },
    {
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete skill ":name"?', { name: r.name }))) {
          router.delete(`/admin/skills/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Skills") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "skills",
      title: __("Skills"),
      createHref: "/admin/skills/create",
      createLabel: __("+ New Skill"),
      columns,
      orderBy: "name",
      actions,
      emptyText: __("No skills yet.")
    }
  ));
}
SkillsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SkillsIndex as default
};
