import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable, { ActiveBadge } from "../../../components/ResourceTable";
import { scrapCategoryOptions } from "./fields";
import { __ } from "../../../lib/i18n";
function ScrapReasonsIndex() {
  const { counts = {} } = usePage().props;
  const categoryLabels = Object.fromEntries(scrapCategoryOptions().map((c) => [c.value, c.label]));
  const columns = [
    { key: "code", label: __("Code"), className: "font-mono text-om-muted" },
    { key: "name", label: __("Name"), className: "font-medium text-om-ink" },
    { key: "category", label: __("Category"), className: "text-om-muted", render: (r) => categoryLabels[r.category] ?? r.category },
    { key: "scrap_entries", label: __("Used"), align: "right", render: (r) => counts[r.id] ?? 0 },
    { key: "is_active", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(ActiveBadge, { active: r.is_active }) }
  ];
  const actions = (r) => [
    { label: __("Edit"), href: `/admin/scrap-reasons/${r.id}/edit` },
    {
      label: r.is_active ? __("Deactivate") : __("Activate"),
      onClick: () => router.post(`/admin/scrap-reasons/${r.id}/toggle-active`, {}, { preserveScroll: true })
    },
    {
      label: __("Delete"),
      className: "text-om-blocked hover:underline",
      onClick: () => {
        if (confirm(__('Delete scrap reason ":name"?', { name: r.name }))) {
          router.delete(`/admin/scrap-reasons/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Scrap Reasons") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "scrap_reasons",
      title: __("Scrap Reasons"),
      createHref: "/admin/scrap-reasons/create",
      createLabel: __("+ New Reason"),
      columns,
      orderBy: "sort_order",
      actions,
      emptyText: __("No scrap reasons yet.")
    }
  ));
}
ScrapReasonsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ScrapReasonsIndex as default
};
