import { useMemo, useState, useRef } from "react";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { Button, ConfirmDialog, Dropdown, StatusPill, TextField } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
const STATUS_PILLS = {
  DONE: "done",
  IN_PROGRESS: "running",
  PENDING: "pending"
};
function pillStatus(status) {
  return STATUS_PILLS[status] ?? "pending";
}
function EansIndex() {
  const { workOrders = {} } = usePage().props;
  const rows = workOrders.data ?? [];
  const pagination = workOrders;
  const form = useForm({ work_order_id: "", ean: "" });
  const handleAddSubmit = (e) => {
    e.preventDefault();
    form.post("/packaging/eans", {
      onSuccess: () => form.reset(),
      preserveScroll: true
    });
  };
  const [eanToDelete, setEanToDelete] = useState(null);
  const confirmDelete = () => {
    if (eanToDelete) {
      router.delete(`/packaging/eans/${eanToDelete.id}`, { preserveScroll: true });
    }
    setEanToDelete(null);
  };
  const [searchVal, setSearchVal] = useState(() => {
    if (typeof window !== "undefined") {
      return new URLSearchParams(window.location.search).get("search") ?? "";
    }
    return "";
  });
  const handleSearch = (e) => {
    e.preventDefault();
    const params = {};
    if (searchVal) params.search = searchVal;
    router.get("/packaging/eans", params, { preserveState: false });
  };
  const handleClear = () => {
    setSearchVal("");
    router.get("/packaging/eans", {}, { preserveState: false });
  };
  const hasSearch = searchVal !== "" || typeof window !== "undefined" && new URLSearchParams(window.location.search).has("search");
  const columns = useMemo(() => [
    {
      id: "order_no",
      accessorKey: "order_no",
      header: "Zlecenie",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono font-semibold text-om-ink" }, row.original.order_no)
    },
    {
      id: "product",
      accessorFn: (r) => r.product_type?.name ?? "\u2014",
      header: "Produkt",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, row.original.product_type?.name ?? "\u2014")
    },
    {
      id: "status",
      accessorKey: "status",
      header: __("Status"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement(
        StatusPill,
        {
          status: pillStatus(row.original.status),
          label: (row.original.status ?? "").replace(/_/g, " ")
        }
      )
    },
    {
      id: "eans",
      header: "Kody EAN",
      enableSorting: false,
      cell: ({ row }) => (row.original.eans ?? []).length === 0 ? /* @__PURE__ */ React.createElement("span", { className: "text-[11.5px] text-om-faint" }, "Brak EAN") : (row.original.eans ?? []).map((ean) => /* @__PURE__ */ React.createElement("div", { key: ean.id, className: "flex items-center gap-2 mb-1" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-[5px]" }, ean.ean), /* @__PURE__ */ React.createElement(
        "button",
        {
          type: "button",
          onClick: () => setEanToDelete(ean),
          className: "text-[11.5px] text-om-blocked hover:underline transition-colors"
        },
        "Usu\u0144"
      )))
    },
    {
      id: "packed",
      accessorFn: (r) => r.packed_qty ?? 0,
      header: "Spakowano / Plan",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, /* @__PURE__ */ React.createElement("span", { className: "font-semibold text-om-ink" }, row.original.packed_qty ?? 0), /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, " / ", parseInt(row.original.planned_qty ?? 0, 10)))
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("EAN Codes \u2014 Management") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("nav", { className: "flex items-center gap-1 text-[13px] text-om-muted mb-4" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-ink hover:underline" }, __("Dashboard")), /* @__PURE__ */ React.createElement("span", { className: "mx-1" }, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/packaging", className: "hover:text-om-ink hover:underline" }, __("Packaging")), /* @__PURE__ */ React.createElement("span", { className: "mx-1" }, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, __("EAN Codes"))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-2xl font-semibold tracking-[-0.02em] text-om-ink" }, __("EAN Codes \u2014 Management")), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted mt-1" }, __("Assign barcodes to work orders"))), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/packaging",
      className: "inline-flex items-center justify-center rounded-om-sm bg-om-chip px-4 py-2.5 text-[13px] font-semibold text-om-ink hover:bg-om-line2 transition-colors"
    },
    "\u2190 ",
    __("Packaging Overview")
  )), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5 mb-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink border-b border-om-line pb-2.5 mb-4" }, __("Add EAN code")), /* @__PURE__ */ React.createElement("form", { onSubmit: handleAddSubmit, className: "grid grid-cols-1 sm:grid-cols-3 gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]" }, __("Production work order")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.data.work_order_id == null ? "" : String(form.data.work_order_id),
      onChange: (v) => form.setData("work_order_id", v),
      placeholder: __("\u2014 select work order \u2014"),
      options: rows.map((wo) => ({
        value: String(wo.id),
        label: `${wo.order_no}${wo.product_type ? ` \u2014 ${wo.product_type.name}` : ""}`
      })),
      className: "w-full"
    }
  ), form.errors.work_order_id && /* @__PURE__ */ React.createElement("p", { className: "text-[11.5px] text-om-blocked mt-1" }, form.errors.work_order_id)), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("EAN code"),
      mono: true,
      value: form.data.ean,
      onChange: (v) => form.setData("ean", v),
      error: form.errors.ean,
      placeholder: __("e.g. 5901234123457"),
      required: true,
      maxLength: 100
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "flex items-end" }, /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "primary",
      loading: form.processing,
      className: "w-full sm:w-auto"
    },
    form.processing ? __("Adding\u2026") : __("Add EAN")
  )))), /* @__PURE__ */ React.createElement("form", { onSubmit: handleSearch, className: "bg-om-card border border-om-line rounded-om px-5 py-3 mb-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex gap-3" }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      className: "flex-1",
      value: searchVal,
      onChange: setSearchVal,
      placeholder: __("Search by order number\u2026")
    }
  ), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "secondary" }, __("Search")), hasSearch && /* @__PURE__ */ React.createElement(Button, { variant: "ghost", onClick: handleClear }, __("Clear")))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: rows,
      columns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: __("No results found")
    }
  ), pagination.last_page > 1 && /* @__PURE__ */ React.createElement("div", { className: "mt-3 flex items-center gap-2 flex-wrap text-[13px]" }, (pagination.links ?? []).map((link, i) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key: i,
      disabled: !link.url || link.active,
      onClick: () => link.url && router.get(link.url, {}, { preserveState: false }),
      className: `px-3 py-1 rounded-om-sm border text-[13px] transition-colors ${link.active ? "bg-om-ink text-om-on-ink border-om-ink" : link.url ? "border-om-line text-om-ink hover:bg-om-chip" : "border-om-line2 text-om-faintest cursor-not-allowed"}`,
      dangerouslySetInnerHTML: { __html: link.label }
    }
  ))))), /* @__PURE__ */ React.createElement(
    ConfirmDialog,
    {
      open: !!eanToDelete,
      onClose: () => setEanToDelete(null),
      onConfirm: confirmDelete,
      title: eanToDelete ? __("Delete EAN code :ean?", { ean: eanToDelete.ean }) : "",
      confirmLabel: __("Delete"),
      cancelLabel: __("Cancel")
    }
  ));
}
EansIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  EansIndex as default
};
