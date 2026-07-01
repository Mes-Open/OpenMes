import { Head, router, usePage } from "@inertiajs/react";
import { useMemo } from "react";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
function PersonnelClassShow() {
  const { personnelClass, workers = [], requiredSkills = [] } = usePage().props;
  const requiredSkillsColumns = useMemo(() => [
    {
      id: "name",
      accessorKey: "name",
      header: __("Skill"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, row.original.name)
    },
    {
      id: "code",
      accessorKey: "code",
      header: __("Code"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs text-om-muted" }, row.original.code)
    },
    {
      id: "min_level",
      accessorKey: "min_level",
      header: __("Minimum cert level"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium" }, capitalize(row.original.min_level))
    }
  ], []);
  const workersColumns = useMemo(() => [
    {
      id: "code",
      accessorKey: "code",
      header: __("Code"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs text-om-muted" }, row.original.code)
    },
    {
      id: "name",
      accessorKey: "name",
      header: __("Name"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("a", { href: `/admin/workers/${row.original.id}`, className: "text-om-accent hover:underline" }, row.original.name)
    },
    {
      id: "qualified",
      accessorKey: "qualified",
      header: __("Qualified"),
      cell: ({ row }) => row.original.qualified ? /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded-full text-xs bg-om-running-bg text-om-running" }, __("Yes")) : /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded-full text-xs bg-om-blocked-bg text-om-blocked" }, __("Gap"))
    }
  ], []);
  const handleDelete = () => {
    if (!confirm(__("Delete this personnel class?"))) return;
    router.delete(`/admin/personnel-classes/${personnelClass.id}`, { preserveScroll: false });
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Personnel Class \u2014 :name", { name: personnelClass.name }) }), /* @__PURE__ */ React.createElement("div", { className: "max-w-5xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 flex-wrap" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-sm text-om-muted" }, personnelClass.code), personnelClass.is_active ? /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded-full text-xs bg-om-running-bg text-om-running" }, __("Active")) : /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded-full text-xs bg-om-chip text-om-muted" }, __("Inactive"))), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mt-1" }, personnelClass.name), personnelClass.description && /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, personnelClass.description)), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement("a", { href: `/admin/personnel-classes/${personnelClass.id}/edit`, className: "btn-touch btn-secondary" }, __("Edit")), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: handleDelete,
      className: "btn-touch bg-om-blocked-bg text-om-blocked hover:bg-om-blocked-bg"
    },
    __("Delete")
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-2 space-y-4" }, /* @__PURE__ */ React.createElement("section", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-3" }, __("Required skills")), requiredSkills.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint" }, __("No required skills configured.")) : /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: requiredSkills,
      columns: requiredSkillsColumns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  )), /* @__PURE__ */ React.createElement("section", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-3" }, __("Workers in this class")), workers.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint italic" }, __("No workers assigned yet.")) : /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: workers,
      columns: workersColumns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement("section", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-3" }, __("Metadata")), /* @__PURE__ */ React.createElement("ul", { className: "space-y-2 text-sm" }, /* @__PURE__ */ React.createElement("li", { className: "flex justify-between gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Workers")), /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, workers.length)), /* @__PURE__ */ React.createElement("li", { className: "flex justify-between gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Required skills")), /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, requiredSkills.length)), /* @__PURE__ */ React.createElement("li", { className: "flex justify-between gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Created")), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, personnelClass.created_at)), /* @__PURE__ */ React.createElement("li", { className: "flex justify-between gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Updated")), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, personnelClass.updated_at))))))));
}
PersonnelClassShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function capitalize(str) {
  if (!str) return "";
  return str.charAt(0).toUpperCase() + str.slice(1);
}
export {
  PersonnelClassShow as default
};
