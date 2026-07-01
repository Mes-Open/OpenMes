import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ResourceForm from "../../../components/ResourceForm";
import { webhookFields } from "./fields";
import { __ } from "../../../lib/i18n";
function WebhookCreate() {
  const { events = [], generatedSecret = "" } = usePage().props;
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Webhook") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Webhook")), /* @__PURE__ */ React.createElement(
    ResourceForm,
    {
      action: "/admin/webhooks",
      method: "post",
      fields: webhookFields(events),
      initial: { name: "", url: "", events: [], secret: generatedSecret, is_active: true },
      submitLabel: __("Create"),
      cancelHref: "/admin/webhooks"
    }
  ));
}
WebhookCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WebhookCreate as default
};
