import { useMemo } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import CustomFieldsDisplay from "../../../components/CustomFieldsDisplay";
function AreaShow() {
  const { area, customFields = [] } = usePage().props;
  const { site, lines = [] } = area;
  const lineColumns = useMemo(() => [
    {
      id: "code",
      accessorKey: "code",
      header: "Code",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, row.original.code)
    },
    {
      id: "name",
      accessorKey: "name",
      header: "Name",
      cell: ({ row }) => /* @__PURE__ */ React.createElement(
        Link,
        {
          href: `/admin/lines/${row.original.id}`,
          className: "text-om-accent hover:text-om-accent"
        },
        row.original.name
      )
    },
    {
      id: "workstations",
      accessorKey: "workstations_count",
      header: "Workstations",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.workstations_count)
    },
    {
      id: "status",
      accessorFn: (r) => r.is_active ? "Active" : "Inactive",
      header: "Status",
      cell: ({ row }) => row.original.is_active ? /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 bg-om-running-bg text-om-running rounded-full text-xs" }, "Active") : /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 bg-om-chip text-om-muted rounded-full text-xs" }, "Inactive")
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: area.name }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("nav", { className: "flex flex-wrap gap-1 items-center text-sm text-om-muted mb-4" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-accent" }, "Dashboard"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/sites", className: "hover:text-om-accent" }, "Sites"), site && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: `/admin/sites/${site.id}`, className: "hover:text-om-accent" }, site.name)), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, area.name)), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-start mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, area.name), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 font-mono text-sm" }, area.code), site && /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, "Site:", " ", /* @__PURE__ */ React.createElement(Link, { href: `/admin/sites/${site.id}`, className: "text-om-accent hover:text-om-accent" }, site.name))), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/areas/${area.id}/edit`,
      className: "btn-touch btn-primary"
    },
    "Edit Area"
  )), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "card p-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs uppercase tracking-wide text-om-muted" }, "Status"), area.is_active ? /* @__PURE__ */ React.createElement("span", { className: "inline-block mt-2 px-2 py-1 bg-om-running-bg text-om-running rounded-full text-xs font-medium" }, "Active") : /* @__PURE__ */ React.createElement("span", { className: "inline-block mt-2 px-2 py-1 bg-om-chip text-om-muted rounded-full text-xs font-medium" }, "Inactive")), /* @__PURE__ */ React.createElement("div", { className: "card p-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs uppercase tracking-wide text-om-muted" }, "Description"), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 text-sm" }, area.description || "\u2014"))), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(CustomFieldsDisplay, { definitions: customFields, values: area.custom_fields ?? {} })), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line2" }, /* @__PURE__ */ React.createElement("h2", { className: "font-semibold text-om-ink" }, "Lines ", /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "(", lines.length, ")"))), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: lines,
      columns: lineColumns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: "No lines assigned to this area yet."
    }
  ))));
}
AreaShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  AreaShow as default
};
