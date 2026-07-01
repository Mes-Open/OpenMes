import { Head, router, usePage } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { Button, ConfirmDialog, Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __, formatDateTime } from "../../../lib/i18n";
function TrashIndex() {
  const { items = [], counts = {}, selectedType = null } = usePage().props;
  const [toRestore, setToRestore] = useState(null);
  const typeLabel = (type) => type.replaceAll("_", " ").replace(/^./, (c) => c.toUpperCase());
  const restore = () => {
    if (toRestore) {
      router.post(`/admin/trash/${toRestore.type}/${toRestore.id}/restore`, {}, { preserveScroll: true });
    }
    setToRestore(null);
  };
  const columns = useMemo(
    () => [
      {
        id: "type",
        accessorFn: (r) => typeLabel(r.type),
        header: __("Type"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, typeLabel(row.original.type))
      },
      {
        id: "item",
        accessorKey: "label",
        header: __("Item"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono font-medium text-om-ink" }, row.original.label)
      },
      {
        id: "deleted_by",
        accessorKey: "deleted_by",
        header: __("Deleted by"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.deleted_by ?? "\u2014")
      },
      {
        id: "deleted_at",
        accessorFn: (r) => r.deleted_at,
        header: __("Deleted at"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "whitespace-nowrap text-om-muted" }, formatDateTime(row.original.deleted_at))
      },
      {
        id: "_restore",
        header: "",
        enableSorting: false,
        enableHiding: false,
        meta: { align: "right" },
        cell: ({ row }) => /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => setToRestore(row.original) }, __("Restore"))
      }
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    []
  );
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Trash") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-5xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row sm:items-end justify-between gap-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Trash")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Deleted items are kept here and can be restored. Restoring also brings back records deleted together with the item."))), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      className: "w-full sm:w-72",
      value: selectedType ?? "",
      onChange: (v) => router.get("/admin/trash", v ? { type: v } : {}, { preserveState: true }),
      options: [
        { value: "", label: __("All types") },
        ...Object.entries(counts).map(([type, count]) => ({
          value: type,
          label: `${typeLabel(type)} (${count})`
        }))
      ]
    }
  )), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: items,
      columns,
      searchPlaceholder: __("Search\u2026"),
      columnsLabel: __("Columns"),
      columnsMenuLabel: __("Toggle columns"),
      emptyLabel: __("Trash is empty."),
      rangeLabel: (start, end, total) => total === 0 ? __("0 results") : `${start}\u2013${end} / ${total}`,
      pageSize: 15
    }
  )), /* @__PURE__ */ React.createElement(
    ConfirmDialog,
    {
      open: !!toRestore,
      onClose: () => setToRestore(null),
      onConfirm: restore,
      title: __("Restore this item?"),
      confirmLabel: __("Restore"),
      cancelLabel: __("Cancel")
    },
    __("Restoring also brings back records deleted together with it.")
  ));
}
TrashIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  TrashIndex as default
};
