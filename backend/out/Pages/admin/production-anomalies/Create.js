import { useState, useMemo } from "react";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
function ProductionAnomalyCreate() {
  const { workOrders = [], anomalyReasons = [], batches = [] } = usePage().props;
  const { data, setData, post, processing, errors } = useForm({
    work_order_id: "",
    batch_id: "",
    anomaly_reason_id: "",
    product_name: "",
    planned_qty: "",
    actual_qty: "",
    comment: ""
  });
  const filteredBatches = useMemo(
    () => data.work_order_id ? batches.filter((b) => String(b.work_order_id) === String(data.work_order_id)) : [],
    [batches, data.work_order_id]
  );
  function handleWorkOrderChange(v) {
    setData((prev) => ({ ...prev, work_order_id: v, batch_id: "" }));
  }
  function submit(e) {
    e.preventDefault();
    post("/admin/production-anomalies");
  }
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Record Production Anomaly") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-2xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Record Production Anomaly")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, __("Log a deviation from the production plan"))), /* @__PURE__ */ React.createElement(Link, { href: "/admin/production-anomalies", className: "btn-touch btn-secondary" }, "\u2190 ", __("Back"))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement("div", { className: "md:col-span-2" }, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Work Order"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.work_order_id == null ? "" : String(data.work_order_id),
      onChange: handleWorkOrderChange,
      className: "w-full",
      placeholder: __("\u2014 Select work order \u2014"),
      options: workOrders.map((wo) => ({
        value: String(wo.id),
        label: `${wo.order_no}${wo.product_name ? ` \u2014 ${wo.product_name}` : ""}`
      }))
    }
  ), errors.work_order_id && /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked text-sm mt-1" }, errors.work_order_id)), /* @__PURE__ */ React.createElement("div", { className: "md:col-span-2" }, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Batch"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faint text-xs" }, "(optional)")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.batch_id == null ? "" : String(data.batch_id),
      onChange: (v) => setData("batch_id", v),
      className: "w-full",
      disabled: filteredBatches.length === 0,
      options: [
        { value: "", label: __("\u2014 No specific batch \u2014") },
        ...filteredBatches.map((b) => ({ value: String(b.id), label: b.label }))
      ]
    }
  ), data.work_order_id && filteredBatches.length === 0 && /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint mt-1" }, __("No batches available for this work order.")), errors.batch_id && /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked text-sm mt-1" }, errors.batch_id)), /* @__PURE__ */ React.createElement("div", { className: "md:col-span-2" }, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Anomaly Reason"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.anomaly_reason_id == null ? "" : String(data.anomaly_reason_id),
      onChange: (v) => setData("anomaly_reason_id", v),
      className: "w-full",
      placeholder: __("\u2014 Select reason \u2014"),
      options: anomalyReasons.map((r) => ({
        value: String(r.id),
        label: `${r.name}${r.category ? ` (${r.category})` : ""}`
      }))
    }
  ), errors.anomaly_reason_id && /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked text-sm mt-1" }, errors.anomaly_reason_id)), /* @__PURE__ */ React.createElement("div", { className: "md:col-span-2" }, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Product Name"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: data.product_name,
      onChange: (e) => setData("product_name", e.target.value),
      className: "form-input w-full",
      placeholder: __("Product being produced"),
      required: true,
      maxLength: 200
    }
  ), errors.product_name && /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked text-sm mt-1" }, errors.product_name)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Planned Quantity"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      value: data.planned_qty,
      onChange: (e) => setData("planned_qty", e.target.value),
      className: "form-input w-full",
      step: "0.01",
      min: "0",
      required: true,
      placeholder: "0.00"
    }
  ), errors.planned_qty && /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked text-sm mt-1" }, errors.planned_qty)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Actual Quantity"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      value: data.actual_qty,
      onChange: (e) => setData("actual_qty", e.target.value),
      className: "form-input w-full",
      step: "0.01",
      min: "0",
      required: true,
      placeholder: "0.00"
    }
  ), errors.actual_qty && /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked text-sm mt-1" }, errors.actual_qty)), /* @__PURE__ */ React.createElement("div", { className: "md:col-span-2" }, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Comment")), /* @__PURE__ */ React.createElement(
    "textarea",
    {
      value: data.comment,
      onChange: (e) => setData("comment", e.target.value),
      rows: 3,
      className: "form-input w-full",
      maxLength: 2e3,
      placeholder: __("Additional details about the anomaly...")
    }
  ), errors.comment && /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked text-sm mt-1" }, errors.comment))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-3 justify-end mt-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/production-anomalies", className: "btn-touch btn-secondary" }, "Cancel"), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "submit",
      disabled: processing,
      className: "btn-touch btn-primary disabled:opacity-50"
    },
    processing ? __("Saving\u2026") : __("Record Anomaly")
  ))))));
}
ProductionAnomalyCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProductionAnomalyCreate as default
};
