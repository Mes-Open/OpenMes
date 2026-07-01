import { useMemo, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
function CustomFieldsIndex() {
  const { definitions = [], entities = [] } = usePage().props;
  const [entity, setEntity] = useState("");
  const rows = entity ? definitions.filter((d) => d.entity_type === entity) : definitions;
  const toggle = (d) => router.post(`/admin/custom-fields/${d.id}/toggle-active`, {}, { preserveScroll: true });
  const destroy = (d) => {
    if (confirm(__('Delete custom field ":label"? Stored values on existing records are left untouched.', { label: d.label }))) {
      router.delete(`/admin/custom-fields/${d.id}`, { preserveScroll: true });
    }
  };
  const columns = useMemo(() => [
    {
      id: "entity",
      accessorKey: "entity_label",
      header: __("Entity"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.entity_label)
    },
    {
      id: "key",
      accessorKey: "key",
      header: __("Key"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, row.original.key)
    },
    {
      id: "label",
      accessorKey: "label",
      header: __("Label"),
      meta: { flex: true },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.label)
    },
    {
      id: "type",
      accessorKey: "type_label",
      header: __("Type"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.type_label, row.original.options_count > 0 && /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, " (", row.original.options_count, ")"))
    },
    {
      id: "required",
      accessorFn: (r) => r.required ? 1 : 0,
      header: __("Required"),
      meta: { align: "center" },
      cell: ({ row }) => row.original.required ? __("Yes") : "\u2014"
    },
    {
      id: "position",
      accessorKey: "position",
      header: __("Position"),
      meta: { align: "center" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.position)
    },
    {
      id: "status",
      accessorFn: (r) => r.is_active ? 1 : 0,
      header: __("Status"),
      meta: { align: "center" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded-full text-xs font-medium ${row.original.is_active ? "bg-om-running-bg text-om-running" : "bg-om-chip text-om-muted"}` }, row.original.is_active ? __("Active") : __("Inactive"))
    },
    {
      id: "actions",
      header: __("Actions"),
      enableSorting: false,
      meta: { align: "right" },
      cell: ({ row }) => {
        const d = row.original;
        return /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-end gap-3 text-sm" }, /* @__PURE__ */ React.createElement(Link, { href: `/admin/custom-fields/${d.id}/edit`, className: "text-om-accent hover:text-om-accent" }, __("Edit")), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: () => toggle(d), className: "text-om-muted hover:text-om-ink" }, d.is_active ? __("Deactivate") : __("Activate")), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: () => destroy(d), className: "text-om-blocked hover:text-om-blocked" }, __("Delete")));
      }
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Custom Fields") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-6xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Custom Fields")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Admin-defined fields attached to records across the system."))), /* @__PURE__ */ React.createElement(Link, { href: "/admin/custom-fields/create", className: "btn-touch btn-primary text-sm" }, __("+ New Custom Field"))), /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: entity == null ? "" : String(entity),
      onChange: (v) => setEntity(v),
      options: [
        { value: "", label: __("All entities") },
        ...entities.map((o) => ({ value: String(o.value), label: o.label }))
      ],
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: rows,
      columns,
      searchPlaceholder: __("Search custom fields\u2026"),
      emptyLabel: __("No custom fields yet.")
    }
  )));
}
CustomFieldsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CustomFieldsIndex as default
};
