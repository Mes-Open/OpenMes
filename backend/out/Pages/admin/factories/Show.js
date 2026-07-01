import { useMemo } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
function FactoryShow() {
  const { factory } = usePage().props;
  const { divisions = [] } = factory;
  const divisionColumns = useMemo(() => [
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
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.name)
    },
    {
      id: "crews",
      accessorKey: "crews_count",
      header: "Crews",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.crews_count)
    },
    {
      id: "status",
      accessorFn: (r) => r.is_active,
      header: "Status",
      cell: ({ row }) => row.original.is_active ? /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 bg-om-running-bg text-om-running rounded-full text-xs" }, "Active") : /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 bg-om-chip text-om-muted rounded-full text-xs" }, "Inactive")
    },
    {
      id: "actions",
      header: "",
      enableSorting: false,
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement(
        Link,
        {
          href: `/admin/divisions/${row.original.id}/edit`,
          className: "text-sm text-om-accent hover:text-om-accent"
        },
        "Edit"
      )
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: factory.name }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("nav", { className: "flex flex-wrap gap-1 items-center text-sm text-om-muted mb-4" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-accent" }, "Dashboard"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/factories", className: "hover:text-om-accent" }, "Factories"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, factory.name)), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-start mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, factory.name), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 font-mono text-sm" }, factory.code)), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/factories/${factory.id}/edit`,
      className: "btn-touch btn-primary"
    },
    "Edit Factory"
  )), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "card p-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs uppercase tracking-wide text-om-muted" }, "Status"), factory.is_active ? /* @__PURE__ */ React.createElement("span", { className: "inline-block mt-2 px-2 py-1 bg-om-running-bg text-om-running rounded-full text-xs font-medium" }, "Active") : /* @__PURE__ */ React.createElement("span", { className: "inline-block mt-2 px-2 py-1 bg-om-chip text-om-muted rounded-full text-xs font-medium" }, "Inactive")), /* @__PURE__ */ React.createElement("div", { className: "card p-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs uppercase tracking-wide text-om-muted" }, "Description"), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 text-sm" }, factory.description || "\u2014"))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line2" }, /* @__PURE__ */ React.createElement("h2", { className: "font-semibold text-om-ink" }, "Divisions ", /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "(", divisions.length, ")"))), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: divisions,
      columns: divisionColumns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: "No divisions assigned to this factory yet."
    }
  ))));
}
FactoryShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  FactoryShow as default
};
