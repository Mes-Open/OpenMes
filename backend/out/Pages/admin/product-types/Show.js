import { Head, Link, router } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import CustomFieldsDisplay from "../../../components/CustomFieldsDisplay";
import { __ } from "../../../lib/i18n";
const WO_STATUS_LABELS = {
  PENDING: "Pending",
  ACCEPTED: "Accepted",
  IN_PROGRESS: "In Progress",
  BLOCKED: "Blocked",
  PAUSED: "Paused",
  DONE: "Done",
  REJECTED: "Rejected",
  CANCELLED: "Cancelled"
};
const WO_STATUS_STYLES = {
  PENDING: "bg-om-downtime-bg text-om-downtime",
  IN_PROGRESS: "bg-om-chip text-om-accent",
  COMPLETED: "bg-om-running-bg text-om-running",
  BLOCKED: "bg-om-blocked-bg text-om-blocked",
  DONE: "bg-om-running-bg text-om-running",
  REJECTED: "bg-om-blocked-bg text-om-blocked",
  CANCELLED: "bg-om-line2 text-om-muted",
  ACCEPTED: "bg-om-chip text-om-accent",
  PAUSED: "bg-om-downtime-bg text-om-downtime"
};
const SERIAL_STATUS_STYLES = {
  in_production: "bg-om-chip text-om-accent",
  completed: "bg-om-running-bg text-om-running",
  scrapped: "bg-om-blocked-bg text-om-blocked",
  shipped: "bg-om-line2 text-om-muted"
};
function trimQty(val) {
  if (val == null) return "0";
  return parseFloat(Number(val).toFixed(4)).toString();
}
function ucWords(str) {
  if (!str) return "\u2014";
  return str.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}
function ProductTypeShow({
  productType,
  recentWorkOrders = [],
  componentsUsed = [],
  serials = { total: 0, status_counts: {}, recent: [] },
  customFields = []
}) {
  const templateCount = productType.process_templates?.length ?? 0;
  const workOrderCount = productType.work_order_count ?? recentWorkOrders.length;
  const totalWorkOrders = productType.total_work_order_count ?? workOrderCount;
  const serialStatusCounts = serials.status_counts ?? {};
  const handleToggleActive = () => {
    router.post(`/admin/product-types/${productType.id}/toggle-active`, {}, { preserveScroll: true });
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Product Type Details") }), /* @__PURE__ */ React.createElement("nav", { className: "text-sm text-om-muted mb-4 flex items-center gap-1" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:underline" }, __("Dashboard")), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/product-types", className: "hover:underline" }, "Product Types"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, productType.name)), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/product-types", className: "text-om-accent hover:text-om-accent flex items-center gap-2 mb-4" }, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M15 19l-7-7 7-7" })), "Back"), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-4" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, productType.name), productType.is_active ? /* @__PURE__ */ React.createElement("span", { className: "px-3 py-1 bg-om-running-bg text-om-running rounded-full text-sm font-medium" }, __("Active")) : /* @__PURE__ */ React.createElement("span", { className: "px-3 py-1 bg-om-chip text-om-muted rounded-full text-sm font-medium" }, __("Inactive"))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/product-types/${productType.id}/edit`,
      className: "btn-touch btn-secondary"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 inline-block mr-2", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" })),
    "Edit Product Type"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: handleToggleActive,
      className: `btn-touch ${productType.is_active ? "btn-secondary" : "btn-primary"}`
    },
    productType.is_active ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 inline-block mr-2", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" })), "Deactivate") : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 inline-block mr-2", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" })), "Activate")
  ))), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted font-mono mt-1" }, productType.code), productType.description && /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-2" }, productType.description), productType.unit_of_measure && /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, "Unit: ", /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, productType.unit_of_measure))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 gap-6 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, __("Process Templates")), /* @__PURE__ */ React.createElement("p", { className: "text-3xl font-bold text-om-accent" }, templateCount)), /* @__PURE__ */ React.createElement("div", { className: "bg-om-chip rounded-full p-3" }, /* @__PURE__ */ React.createElement("svg", { className: "w-8 h-8 text-om-accent", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" }))))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, __("Work Orders")), /* @__PURE__ */ React.createElement("p", { className: "text-3xl font-bold text-om-ink" }, totalWorkOrders)), /* @__PURE__ */ React.createElement("div", { className: "bg-om-chip rounded-full p-3" }, /* @__PURE__ */ React.createElement("svg", { className: "w-8 h-8 text-om-ink", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" })))))), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(CustomFieldsDisplay, { definitions: customFields, values: productType.custom_fields ?? {} })), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-2 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink" }, __("Process Templates")), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/product-types/${productType.id}/process-templates`,
      className: "btn-touch btn-secondary text-sm"
    },
    "View All"
  ), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/product-types/${productType.id}/process-templates/create`,
      className: "btn-touch btn-primary text-sm"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4 inline-block mr-1", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M12 4v16m8-8H4" })),
    "Create"
  ))), productType.process_templates && productType.process_templates.length > 0 ? /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, productType.process_templates.map((template) => /* @__PURE__ */ React.createElement(
    Link,
    {
      key: template.id,
      href: `/admin/product-types/${productType.id}/process-templates/${template.id}`,
      className: "block p-3 bg-om-panel rounded-om-sm hover:bg-om-chip transition-colors"
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 mb-1" }, /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, template.name), template.is_active ? /* @__PURE__ */ React.createElement("span", { className: "px-2 py-1 bg-om-running-bg text-om-running rounded-full text-xs font-medium" }, __("Active")) : /* @__PURE__ */ React.createElement("span", { className: "px-2 py-1 bg-om-chip text-om-muted rounded-full text-xs font-medium" }, __("Inactive"))), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, "Version ", template.version, " \u2022 ", template.steps?.length ?? 0, " steps")), /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 text-om-faint", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M9 5l7 7-7 7" })))
  ))) : /* @__PURE__ */ React.createElement("div", { className: "text-center py-8 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement("svg", { className: "mx-auto h-12 w-12 text-om-faint mb-2", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" })), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mb-2" }, __("No process templates yet")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, __("Process templates define how this product is manufactured.")))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink mb-4" }, __("Recent Work Orders")), recentWorkOrders.length > 0 ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, recentWorkOrders.map((wo) => /* @__PURE__ */ React.createElement("div", { key: wo.id, className: "p-3 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, wo.work_order_number), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, wo.product_name), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-1" }, "Quantity: ", wo.planned_qty, " | ", wo.created_at ? wo.created_at.substring(0, 16).replace("T", " ") : "\u2014")), /* @__PURE__ */ React.createElement("span", { className: `px-2 py-1 text-xs font-medium rounded-full ${WO_STATUS_STYLES[wo.status] ?? "bg-om-chip text-om-ink"}` }, WO_STATUS_LABELS[wo.status] ?? wo.status))))), totalWorkOrders > 10 && /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted text-center mt-4" }, "Showing 10 most recent of ", totalWorkOrders, " total work orders")) : /* @__PURE__ */ React.createElement("div", { className: "text-center py-8 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement("svg", { className: "mx-auto h-12 w-12 text-om-faint mb-2", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" })), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("No work orders yet"))))), /* @__PURE__ */ React.createElement("div", { className: "mt-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink mb-1" }, __("Components & serials used")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-4" }, __("Materials actually consumed and serialized units produced across this product's work orders.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-2 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-4" }, __("Components consumed"), " (", componentsUsed.length, ")"), componentsUsed.length > 0 ? /* @__PURE__ */ React.createElement("div", { className: "overflow-x-auto" }, /* @__PURE__ */ React.createElement("table", { className: "w-full text-sm" }, /* @__PURE__ */ React.createElement("thead", null, /* @__PURE__ */ React.createElement("tr", { className: "text-left text-xs text-om-muted uppercase border-b border-om-line2" }, /* @__PURE__ */ React.createElement("th", { className: "py-2 pr-3 font-medium" }, __("Material")), /* @__PURE__ */ React.createElement("th", { className: "py-2 px-3 font-medium text-right" }, __("Consumed")), /* @__PURE__ */ React.createElement("th", { className: "py-2 pl-3 font-medium text-right" }, __("Lots")))), /* @__PURE__ */ React.createElement("tbody", null, componentsUsed.map((c) => /* @__PURE__ */ React.createElement("tr", { key: c.id, className: "border-b border-om-line2 last:border-0" }, /* @__PURE__ */ React.createElement("td", { className: "py-2 pr-3" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/materials/${c.id}`,
      className: "font-medium text-om-accent hover:underline"
    },
    c.name
  ), /* @__PURE__ */ React.createElement("span", { className: "block text-xs text-om-muted font-mono" }, c.code)), /* @__PURE__ */ React.createElement("td", { className: "py-2 px-3 text-right font-mono" }, trimQty(c.total_consumed), " ", /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, c.unit_of_measure)), /* @__PURE__ */ React.createElement("td", { className: "py-2 pl-3 text-right font-mono text-om-muted" }, c.lot_count)))))) : /* @__PURE__ */ React.createElement("div", { className: "text-center py-8 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("No material consumption recorded yet")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Components appear here once lots are consumed against this product's batches.")))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide" }, __("Serialized units"), " (", serials.total ?? 0, ")"), Object.keys(serialStatusCounts).length > 0 && /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1 justify-end" }, Object.entries(serialStatusCounts).map(([status, count]) => /* @__PURE__ */ React.createElement(
    "span",
    {
      key: status,
      className: `px-2 py-0.5 rounded-full text-xs font-medium ${SERIAL_STATUS_STYLES[status] ?? "bg-om-chip text-om-muted"}`
    },
    __(ucWords(status)),
    ": ",
    count
  )))), serials.recent && serials.recent.length > 0 ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, serials.recent.map((s) => /* @__PURE__ */ React.createElement(
    Link,
    {
      key: s.id,
      href: `/admin/traceability?q=${encodeURIComponent(s.serial_no)}`,
      className: "block p-3 bg-om-panel rounded-om-sm hover:bg-om-chip transition-colors"
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0" }, /* @__PURE__ */ React.createElement("p", { className: "font-mono font-medium text-om-ink truncate" }, s.serial_no), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-0.5" }, s.work_order ?? "\u2014", s.batch && /* @__PURE__ */ React.createElement(React.Fragment, null, " \u2022 ", s.batch))), /* @__PURE__ */ React.createElement("span", { className: `px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap ${SERIAL_STATUS_STYLES[s.status] ?? "bg-om-chip text-om-muted"}` }, __(ucWords(s.status))))
  ))), (serials.total ?? 0) > serials.recent.length && /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted text-center mt-4" }, __("Showing :count most recent of :total units", { count: serials.recent.length, total: serials.total }))) : /* @__PURE__ */ React.createElement("div", { className: "text-center py-8 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("No serialized units yet")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Units registered against this product's work orders will be listed here."))))))));
}
ProductTypeShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProductTypeShow as default
};
