import { useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import { Button, Modal, TextField, Dropdown, InlineAlert } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function initialRows(triggerInfo) {
  const params = triggerInfo?.parameters ?? [];
  if (!params.length) {
    return [{ parameter_name: __("Result"), parameter_type: "pass_fail", value_numeric: "", is_passed: true }];
  }
  return params.map((p) => ({
    parameter_name: p.name,
    parameter_type: p.type === "measurement" ? "measurement" : "pass_fail",
    unit: p.unit,
    value_numeric: "",
    is_passed: true
  }));
}
function QualityTasksIndex() {
  const {
    triggers = {},
    workOrderNos = {},
    lineNames = {},
    batchNumbers = {},
    roamingTriggers = [],
    activeBatches = [],
    pallets = []
  } = usePage().props;
  const base = typeof window !== "undefined" && window.location.pathname.startsWith("/admin") ? "/admin" : "/supervisor";
  const [performFor, setPerformFor] = useState(null);
  const [rows, setRows] = useState([]);
  const [notes, setNotes] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [palletId, setPalletId] = useState("");
  const [roamingOpen, setRoamingOpen] = useState(false);
  const [roamingTrigger, setRoamingTrigger] = useState("");
  const [roamingBatch, setRoamingBatch] = useState("");
  const openPerform = (task) => {
    setRows(initialRows(triggers[task.quality_control_trigger_id]));
    setNotes("");
    setPalletId("");
    setPerformFor(task);
  };
  const palletOptions = performFor ? pallets.filter((p) => !performFor.work_order_id || p.work_order_id === performFor.work_order_id) : [];
  const setRow = (i, patch) => setRows((rs) => rs.map((r, idx) => idx === i ? { ...r, ...patch } : r));
  const submitPerform = () => {
    const samples = rows.map((r, i) => ({
      sample_number: i + 1,
      parameter_name: r.parameter_name,
      parameter_type: r.parameter_type,
      value_numeric: r.value_numeric === "" ? null : Number(r.value_numeric),
      is_passed: !!r.is_passed
    }));
    setSubmitting(true);
    router.post(`${base}/quality-tasks/${performFor.id}/perform`, { samples, notes, pallet_id: palletId || null }, {
      preserveScroll: true,
      onSuccess: () => setPerformFor(null),
      onFinish: () => setSubmitting(false)
    });
  };
  const skip = (task) => {
    if (confirm(__("Skip this quality control?"))) {
      router.post(`${base}/quality-tasks/${task.id}/skip`, {}, { preserveScroll: true });
    }
  };
  const submitRoaming = () => {
    if (!roamingTrigger || !roamingBatch) return;
    router.post(`${base}/quality-tasks`, { quality_control_trigger_id: roamingTrigger, batch_id: roamingBatch }, {
      preserveScroll: true,
      onSuccess: () => {
        setRoamingOpen(false);
        setRoamingTrigger("");
        setRoamingBatch("");
      }
    });
  };
  const columns = [
    {
      key: "quality_control_trigger_id",
      label: __("Control"),
      className: "font-medium text-om-ink",
      render: (r) => triggers[r.quality_control_trigger_id]?.name ?? `#${r.quality_control_trigger_id}`
    },
    { key: "due_reason", label: __("Reason"), className: "text-om-muted" },
    {
      key: "work_order_id",
      label: __("Work order"),
      className: "text-om-muted",
      render: (r) => r.work_order_id ? workOrderNos[r.work_order_id] ?? `#${r.work_order_id}` : "\u2014"
    },
    {
      key: "batch_id",
      label: __("Batch"),
      className: "text-om-muted",
      render: (r) => r.batch_id ? batchNumbers[r.batch_id] ?? `#${r.batch_id}` : "\u2014"
    },
    {
      key: "line_id",
      label: __("Line"),
      className: "text-om-muted",
      render: (r) => r.line_id ? lineNames[r.line_id] ?? `#${r.line_id}` : "\u2014"
    },
    {
      key: "fired_at",
      label: __("Due since"),
      className: "text-om-faint",
      render: (r) => r.fired_at ? String(r.fired_at).slice(0, 16).replace("T", " ") : "\u2014"
    }
  ];
  const actions = (r) => {
    const list = [];
    if (r.batch_id) {
      list.push({ label: __("Perform"), variant: "primary", onClick: () => openPerform(r) });
    }
    list.push({ label: __("Skip"), onClick: () => skip(r) });
    return list;
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Quality Controls") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "quality_control_tasks_due",
      title: __("Quality Controls"),
      columns,
      orderBy: "fired_at",
      actions,
      emptyText: __("No outstanding quality controls."),
      subtitle: roamingTriggers.length ? /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => setRoamingOpen(true) }, __("Raise roaming check")) : null
    }
  ), /* @__PURE__ */ React.createElement(
    Modal,
    {
      open: performFor != null,
      onClose: () => setPerformFor(null),
      title: __("Perform quality control"),
      subtitle: performFor ? triggers[performFor.quality_control_trigger_id]?.name : "",
      closeLabel: __("Close"),
      footer: /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => setPerformFor(null) }, __("Cancel")), /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: submitPerform, disabled: submitting }, __("Record result")))
    },
    /* @__PURE__ */ React.createElement("div", { className: "space-y-[14px]" }, rows.map((row, i) => /* @__PURE__ */ React.createElement("div", { key: i, className: "flex items-end gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement("div", { className: "text-[12.5px] font-medium text-om-ink mb-1" }, row.parameter_name, row.unit ? /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, " (", row.unit, ")") : null), row.parameter_type === "measurement" && /* @__PURE__ */ React.createElement(
      TextField,
      {
        label: __("Measured value"),
        inputMode: "decimal",
        value: row.value_numeric,
        onChange: (v) => setRow(i, { value_numeric: v })
      }
    )), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        label: __("Result"),
        value: row.is_passed ? "pass" : "fail",
        onChange: (v) => setRow(i, { is_passed: v === "pass" }),
        options: [
          { value: "pass", label: __("Pass") },
          { value: "fail", label: __("Fail") }
        ]
      }
    ))), palletOptions.length > 0 && /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        label: __("Link to pallet (optional)"),
        value: palletId,
        onChange: setPalletId,
        options: [
          { value: "", label: __("\u2014 None \u2014") },
          ...palletOptions.map((p) => ({ value: String(p.id), label: p.pallet_no }))
        ]
      }
    ), /* @__PURE__ */ React.createElement(
      TextField,
      {
        label: __("Notes"),
        multiline: true,
        value: notes,
        onChange: setNotes,
        placeholder: __("Optional")
      }
    ), /* @__PURE__ */ React.createElement(InlineAlert, { severity: "info" }, __("A failing result raises a non-conformance and, for blocking controls, halts the work order.")))
  ), /* @__PURE__ */ React.createElement(
    Modal,
    {
      open: roamingOpen,
      onClose: () => setRoamingOpen(false),
      title: __("Raise roaming check"),
      closeLabel: __("Close"),
      footer: /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => setRoamingOpen(false) }, __("Cancel")), /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: submitRoaming }, __("Raise")))
    },
    /* @__PURE__ */ React.createElement("div", { className: "space-y-[14px]" }, /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        label: __("Roaming trigger"),
        value: roamingTrigger,
        onChange: setRoamingTrigger,
        options: [
          { value: "", label: __("Select\u2026") },
          ...roamingTriggers.map((t) => ({ value: String(t.id), label: t.name }))
        ]
      }
    ), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        label: __("Batch"),
        value: roamingBatch,
        onChange: setRoamingBatch,
        options: [
          { value: "", label: __("Select\u2026") },
          ...activeBatches.map((b) => ({ value: String(b.id), label: b.label }))
        ]
      }
    ))
  ));
}
QualityTasksIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  QualityTasksIndex as default
};
