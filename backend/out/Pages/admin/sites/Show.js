import { Head, Link, usePage } from "@inertiajs/react";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import CustomFieldsDisplay from "../../../components/CustomFieldsDisplay";
const areaColumns = [
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
        href: `/admin/areas/${row.original.id}`,
        className: "text-om-accent hover:text-om-accent"
      },
      row.original.name
    )
  },
  {
    id: "lines",
    accessorKey: "lines_count",
    header: "Lines",
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.lines_count)
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
        href: `/admin/areas/${row.original.id}/edit`,
        className: "text-sm text-om-accent hover:text-om-accent"
      },
      "Edit"
    )
  }
];
const lineColumns = [
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
    id: "status",
    accessorFn: (r) => r.is_active,
    header: "Status",
    cell: ({ row }) => row.original.is_active ? /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 bg-om-running-bg text-om-running rounded-full text-xs" }, "Active") : /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 bg-om-chip text-om-muted rounded-full text-xs" }, "Inactive")
  }
];
function SiteShow() {
  const { site, customFields = [] } = usePage().props;
  const { company, areas = [], lines = [] } = site;
  const locationParts = [site.address, [site.city, site.country].filter(Boolean).join(", ")].filter(Boolean);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: site.name }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("nav", { className: "flex flex-wrap gap-1 items-center text-sm text-om-muted mb-4" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-accent" }, "Dashboard"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/sites", className: "hover:text-om-accent" }, "Sites"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, site.name)), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-start mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, site.name), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 font-mono text-sm" }, site.code), company && /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, "Company: ", /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, company.name))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/sites/${site.id}/areas/create`,
      className: "btn-touch btn-secondary"
    },
    "Add Area"
  ), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/sites/${site.id}/edit`,
      className: "btn-touch btn-primary"
    },
    "Edit Site"
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "card p-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs uppercase tracking-wide text-om-muted" }, "Location"), /* @__PURE__ */ React.createElement("p", { className: "text-om-ink mt-1" }, locationParts.length > 0 ? locationParts.map((part, i) => /* @__PURE__ */ React.createElement("span", { key: i }, part, i < locationParts.length - 1 && /* @__PURE__ */ React.createElement("br", null))) : "\u2014"), site.timezone && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-2" }, "Timezone: ", site.timezone)), /* @__PURE__ */ React.createElement("div", { className: "card p-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs uppercase tracking-wide text-om-muted" }, "Status"), site.is_active ? /* @__PURE__ */ React.createElement("span", { className: "inline-block mt-2 px-2 py-1 bg-om-running-bg text-om-running rounded-full text-xs font-medium" }, "Active") : /* @__PURE__ */ React.createElement("span", { className: "inline-block mt-2 px-2 py-1 bg-om-chip text-om-muted rounded-full text-xs font-medium" }, "Inactive")), /* @__PURE__ */ React.createElement("div", { className: "card p-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs uppercase tracking-wide text-om-muted" }, "Description"), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 text-sm" }, site.description || "\u2014"))), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(CustomFieldsDisplay, { definitions: customFields, values: site.custom_fields ?? {} })), /* @__PURE__ */ React.createElement("div", { className: "card mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line2 flex items-center justify-between" }, /* @__PURE__ */ React.createElement("h2", { className: "font-semibold text-om-ink" }, "Areas ", /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "(", areas.length, ")")), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/sites/${site.id}/areas/create`,
      className: "text-sm text-om-accent hover:text-om-accent"
    },
    "+ Add Area"
  )), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: areas,
      columns: areaColumns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: "No areas defined yet."
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line2" }, /* @__PURE__ */ React.createElement("h2", { className: "font-semibold text-om-ink" }, "Lines under this Site ", /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "(", lines.length, ")"))), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: lines,
      columns: lineColumns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: "No lines mapped under this site yet."
    }
  ))));
}
SiteShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SiteShow as default
};
