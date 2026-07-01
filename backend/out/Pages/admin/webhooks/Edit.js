import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { webhookFields } from "./fields";
import { __ } from "../../../lib/i18n";
function WebhookEdit() {
  const { webhook, events = [] } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit Webhook") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Webhook")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: `/admin/webhooks/${webhook.id}`,
      method: "put",
      fields: webhookFields(events, { isEdit: true }),
      initial: {
        name: webhook.name ?? "",
        url: webhook.url ?? "",
        events: webhook.events ?? [],
        secret: "",
        is_active: !!webhook.is_active
      },
      submitLabel: __("Save"),
      cancelHref: "/admin/webhooks"
    }
  ));
}
WebhookEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WebhookEdit as default
};
