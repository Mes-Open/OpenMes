import { Head, Link, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
const STATUS_CLASS = {
  success: "text-om-done bg-om-done-bg",
  failed: "text-om-blocked bg-om-blocked-bg",
  pending: "text-om-muted bg-om-chip"
};
function StatusBadge({ status }) {
  const cls = STATUS_CLASS[status] ?? STATUS_CLASS.pending;
  return /* @__PURE__ */ React.createElement("span", { className: `inline-block rounded-om-sm px-2 py-0.5 text-[11.5px] font-semibold ${cls}` }, __(status));
}
function WebhookDeliveries() {
  const { webhook } = usePage().props;
  const columns = [
    {
      key: "created_at",
      label: __("Created"),
      render: (r) => r.created_at ? new Date(r.created_at).toLocaleString() : "\u2014"
    },
    { key: "event_type", label: __("Event"), className: "font-mono text-[12px] text-om-muted" },
    { key: "status", label: __("Status"), render: (r) => /* @__PURE__ */ React.createElement(StatusBadge, { status: r.status }) },
    { key: "attempts", label: __("Attempts") },
    { key: "response_code", label: __("Response"), render: (r) => r.response_code ?? "\u2014" },
    { key: "error", label: __("Error"), className: "text-[12px] text-om-blocked", render: (r) => r.error ?? "\u2014" }
  ];
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(Head, { title: __("Deliveries") }), /* @__PURE__ */ React.createElement(Link, { href: "/admin/webhooks", className: "text-[13px] text-om-muted hover:text-om-ink mb-4 inline-block" }, "\u2039 ", __("Webhooks")), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "webhook_deliveries",
      title: __("Deliveries for :name", { name: webhook.name }),
      columns,
      orderBy: "created_at",
      orderDir: "desc",
      filterFn: (r) => Number(r.webhook_id) === Number(webhook.id),
      emptyText: __("No deliveries yet.")
    }
  ));
}
WebhookDeliveries.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WebhookDeliveries as default
};
