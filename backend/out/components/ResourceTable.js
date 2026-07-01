import { useMemo } from "react";
import { Link } from "@inertiajs/react";
import { useLiveQuery } from "@tanstack/react-db";
import { StatusPill } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import { realtimeCollection } from "../lib/realtimeCollection";
import { __ } from "../lib/i18n";
const ICON_PATH = {
  edit: "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z",
  delete: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16",
  deactivate: "M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636",
  activate: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
};
const ICON_COLOR = {
  edit: "text-om-muted hover:text-om-ink hover:bg-om-chip",
  delete: "text-om-blocked hover:bg-om-blocked-bg",
  deactivate: "text-om-muted hover:text-om-ink hover:bg-om-chip",
  activate: "text-om-muted hover:text-om-ink hover:bg-om-chip"
};
const ACTION_BASE = "inline-flex items-center justify-center rounded-om-sm px-3 py-1.5 text-[12.5px] font-semibold transition-colors";
const ACTION_CLASS = {
  primary: `${ACTION_BASE} bg-om-ink text-om-on-ink hover:bg-om-ink-hover`,
  secondary: `${ACTION_BASE} bg-om-chip text-om-ink hover:bg-om-line2`,
  danger: `${ACTION_BASE} bg-om-blocked-bg text-om-blocked hover:bg-[#f8ddd6]`,
  warning: `${ACTION_BASE} bg-om-downtime-bg text-om-downtime hover:brightness-95`
};
const actionClass = (a) => a.className ?? ACTION_CLASS[a.variant] ?? ACTION_CLASS.secondary;
function RowActions({ actions, row }) {
  return /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-end gap-2" }, actions(row).map((a, i) => {
    if (a.icon && ICON_PATH[a.icon]) {
      const cls = `p-1.5 rounded-om-sm transition-colors ${ICON_COLOR[a.icon]}`;
      const glyph = /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: ICON_PATH[a.icon] }));
      return a.href ? /* @__PURE__ */ React.createElement(Link, { key: i, href: a.href, className: cls, title: __(a.label), "aria-label": __(a.label), "data-action": a.label }, glyph) : /* @__PURE__ */ React.createElement("button", { key: i, onClick: a.onClick, className: cls, title: __(a.label), "aria-label": __(a.label), "data-action": a.label }, glyph);
    }
    return a.href ? /* @__PURE__ */ React.createElement(Link, { key: i, href: a.href, className: actionClass(a), "data-action": a.label }, __(a.label)) : /* @__PURE__ */ React.createElement("button", { key: i, onClick: a.onClick, className: actionClass(a), "data-action": a.label }, __(a.label));
  }));
}
function ResourceTable({
  shape,
  title,
  createHref,
  createLabel = "+ New",
  columns,
  orderBy = "name",
  orderDir = "asc",
  getKey = (row) => row.id,
  actions,
  emptyText = "Nothing here yet.",
  filterFn,
  subtitle,
  pageSize = 12,
  enableSelection = false,
  bulkActions,
  selectionLabel
}) {
  const collection = useMemo(() => realtimeCollection(shape, getKey), [shape]);
  const { data: rows } = useLiveQuery(
    (q) => q.from({ r: collection }).orderBy(({ r }) => r[orderBy], orderDir)
  );
  const visibleRows = filterFn ? (rows ?? []).filter(filterFn) : rows ?? [];
  const tableColumns = useMemo(() => {
    const flexKey = ["description", "name", "title", "label"].find((k) => columns.some((c) => c.key === k)) ?? columns.find((c) => c.align !== "right")?.key;
    const defs = columns.map((c) => ({
      id: c.key,
      accessorFn: (row) => row[c.key],
      header: __(c.label),
      enableSorting: c.sortable !== false,
      cell: ({ row }) => {
        const content = c.render ? c.render(row.original) : row.original[c.key];
        return c.className ? /* @__PURE__ */ React.createElement("span", { className: c.className }, content) : content;
      },
      meta: {
        align: c.align === "right" ? "right" : "left",
        flex: c.flex || c.key === flexKey,
        filter: c.filter,
        options: c.options,
        allLabel: c.allLabel,
        filterPlaceholder: c.filterPlaceholder,
        menuLabel: __(c.label)
      }
    }));
    if (actions) {
      defs.push({
        id: "_actions",
        header: __("Actions"),
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => /* @__PURE__ */ React.createElement(RowActions, { actions, row: row.original }),
        meta: { align: "right", menuLabel: __("Actions") }
      });
    }
    return defs;
  }, [columns, actions]);
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __(title)), subtitle && /* @__PURE__ */ React.createElement("div", { className: "mt-1" }, subtitle)), createHref && /* @__PURE__ */ React.createElement(
    Link,
    {
      href: createHref,
      className: "inline-flex items-center justify-center rounded-om-sm bg-om-ink px-4 py-2.5 text-[13px] font-semibold text-om-on-ink transition-colors hover:bg-om-ink-hover"
    },
    __(createLabel)
  )), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: visibleRows,
      columns: tableColumns,
      searchPlaceholder: __("Search\u2026"),
      columnsLabel: __("Columns"),
      columnsMenuLabel: __("Toggle columns"),
      emptyLabel: __(emptyText),
      rangeLabel: (start, end, total) => total === 0 ? __("0 results") : `${start}\u2013${end} / ${total}`,
      pageSize,
      enableSelection,
      bulkActions,
      selectionLabel
    }
  ));
}
function ActiveBadge({ active }) {
  return /* @__PURE__ */ React.createElement(
    StatusPill,
    {
      status: active ? "running" : "pending",
      pulse: false,
      label: __(active ? "Active" : "Inactive")
    }
  );
}
export {
  ActiveBadge,
  ResourceTable as default
};
