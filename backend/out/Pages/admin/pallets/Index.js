import { Head, Link, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
const STATUS_BADGE = {
  open: "bg-om-running-bg text-om-running",
  closed: "bg-om-chip text-om-accent",
  shipped: "bg-om-chip text-om-muted"
};
const QUALITY_BADGE = {
  pending: "bg-om-downtime-bg text-om-downtime",
  pass: "bg-om-running-bg text-om-running",
  fail: "bg-om-blocked-bg text-om-blocked"
};
const QUALITY_LABELS = { pending: "Pending", pass: "Passed", fail: "Failed" };
const PRINTER_ICON = /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" }));
function NoTemplateBanner() {
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto mb-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-om-sm border border-om-line bg-om-downtime-bg px-4 py-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-downtime" }, PRINTER_ICON), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-sm font-semibold text-om-downtime" }, __("No pallet label template configured")), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-downtime" }, __("Prepare a label template to print pallet labels.")))), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/packaging/label-templates/create",
      className: "inline-flex items-center gap-1.5 px-4 py-2 rounded-om-sm text-sm font-semibold bg-om-accent text-white hover:brightness-95 shrink-0"
    },
    PRINTER_ICON,
    " ",
    __("Prepare label")
  )));
}
function LabelCell({ palletId, templates }) {
  if (!templates.length) {
    return /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "\u2014");
  }
  const tpl = templates.find((t) => t.is_default) ?? templates[0];
  const base = `/packaging/labels/pallet/${palletId}`;
  return /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1.5" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `${base}/pdf?template=${tpl.id}`,
      target: "_blank",
      rel: "noreferrer",
      className: "inline-flex items-center gap-1.5 px-3 py-1.5 rounded-om-sm text-xs font-semibold bg-om-ink text-om-on-ink hover:bg-om-ink-hover shadow-sm"
    },
    PRINTER_ICON,
    " PDF"
  ), /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `${base}/zpl?template=${tpl.id}`,
      className: "inline-flex items-center px-2.5 py-1.5 rounded-om-sm text-xs font-medium bg-om-ink text-om-on-ink hover:bg-om-ink-hover",
      title: "Download ZPL for a Zebra printer"
    },
    "ZPL"
  ));
}
function PalletsIndex() {
  const { workOrderNumbers = {}, statusLabels = {}, labelTemplates = [] } = usePage().props;
  const columns = [
    { key: "pallet_no", label: "Pallet number", className: "font-mono font-medium text-om-ink" },
    {
      key: "work_order",
      label: "Work order",
      render: (r) => workOrderNumbers[r.work_order_id] ?? `#${r.work_order_id}`
    },
    { key: "qty", label: "Quantity" },
    {
      key: "status",
      label: "Status",
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: `inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[r.status] ?? ""}` }, statusLabels[r.status] ?? r.status)
    },
    {
      key: "quality_status",
      label: "Quality",
      filter: true,
      allLabel: __("All quality"),
      options: [
        { value: "pending", label: __("Pending") },
        { value: "pass", label: __("Passed") },
        { value: "fail", label: __("Failed") }
      ],
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: `inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${QUALITY_BADGE[r.quality_status] ?? ""}` }, __(QUALITY_LABELS[r.quality_status] ?? r.quality_status ?? "Pending"))
    },
    { key: "location", label: "Location", render: (r) => r.location || "\u2014" },
    { key: "erp_reference", label: "ERP reference", render: (r) => r.erp_reference || "\u2014" },
    {
      key: "label",
      label: "Label",
      render: (r) => /* @__PURE__ */ React.createElement(LabelCell, { palletId: r.id, templates: labelTemplates })
    }
  ];
  const actions = (r) => [
    { label: "Edit", icon: "edit", href: `/admin/pallets/${r.id}/edit` },
    {
      label: "Delete",
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(`Delete pallet "${r.pallet_no}"?`)) {
          router.delete(`/admin/pallets/${r.id}`, { preserveScroll: true });
        }
      }
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: "Pallets" }), labelTemplates.length === 0 && /* @__PURE__ */ React.createElement(NoTemplateBanner, null), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "pallets",
      title: "Pallets",
      createHref: "/admin/pallets/create",
      createLabel: "+ New Pallet",
      columns,
      orderBy: "pallet_no",
      orderDir: "desc",
      actions,
      emptyText: "No pallets yet."
    }
  ));
}
PalletsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  PalletsIndex as default
};
