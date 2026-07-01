import { useMemo, useState } from "react";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { Button, ConfirmDialog, Dropdown, StatusPill, TextField } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../layouts/AppLayout";
import { formatDateTime, formatNumber, __ } from "../../lib/i18n";
function statusPill(status) {
  const map = {
    pass: "running",
    conditional_pass: "downtime",
    fail: "blocked",
    pending: "pending"
  };
  return map[status] ?? "pending";
}
const DISPOSITION_OPTIONS = [
  { value: "accept", label: "Accept", desc: "Accept \u2014 pass to production" },
  { value: "accept_with_deviation", label: "Accept with deviation", desc: "Accept with deviation \u2014 minor issue, documented" },
  { value: "rework", label: "Rework", desc: "Rework \u2014 fix and re-inspect" },
  { value: "quarantine", label: "Quarantine", desc: "Quarantine \u2014 hold pending decision" },
  { value: "scrap", label: "Scrap", desc: "Scrap \u2014 discard" },
  { value: "return_to_supplier", label: "Return to supplier", desc: "Return to supplier" },
  { value: "reject", label: "Reject", desc: "Reject (no further action)" }
];
function dispositionPill(disposition) {
  const map = {
    accept: "running",
    accept_with_deviation: "running",
    rework: "downtime",
    quarantine: "pending",
    scrap: "blocked",
    reject: "blocked",
    return_to_supplier: "downtime"
  };
  return map[disposition] ?? "pending";
}
const CARD_CLASS = "bg-om-card border border-om-line rounded-om p-5";
const SECTION_HEADING_CLASS = "text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-3";
const TH_CLASS = "text-left p-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint";
const INPUT_CLASS = "bg-om-bg border border-om-line rounded-om-sm px-3 py-2 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]";
function fmtDateTime(str) {
  if (!str) return "\u2014";
  return formatDateTime(new Date(str), {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit"
  });
}
function fmtNum(n, decimals = 2) {
  if (n == null) return "\u2014";
  return formatNumber(Number(n), { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}
function DispositionSection({ inspection }) {
  const [modalOpen, setModalOpen] = useState(false);
  const { auth } = usePage().props;
  const canDispose = auth?.user?.roles?.some((r) => ["Admin", "Supervisor"].includes(r));
  const hasDecision = inspection.disposition && inspection.disposition !== "pending";
  return /* @__PURE__ */ React.createElement("div", { className: `${CARD_CLASS} mb-4` }, /* @__PURE__ */ React.createElement("h3", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-3" }, __("Disposition")), hasDecision ? /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement(
    StatusPill,
    {
      status: dispositionPill(inspection.disposition),
      pulse: false,
      label: (inspection.disposition ?? "").replace(/_/g, " ")
    }
  ), /* @__PURE__ */ React.createElement("span", { className: "text-[12.5px] text-om-muted" }, "by ", inspection.disposition_by?.name ?? "\u2014", inspection.disposition_at ? ` \xB7 ${fmtDateTime(inspection.disposition_at)}` : "")), inspection.disposition_notes && /* @__PURE__ */ React.createElement("p", { className: "mt-2 text-[12.5px] text-om-muted" }, inspection.disposition_notes)) : /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted" }, __("No disposition recorded yet.")), canDispose && /* @__PURE__ */ React.createElement("div", { className: "mt-3" }, /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: () => setModalOpen(true) }, __("Record Disposition")))), modalOpen && /* @__PURE__ */ React.createElement(
    DispositionModal,
    {
      inspection,
      onClose: () => setModalOpen(false)
    }
  ));
}
function DispositionModal({ inspection, onClose }) {
  const form = useForm({ disposition: "", notes: "" });
  const submit = (e) => {
    e.preventDefault();
    form.post(`/inspections/${inspection.id}/disposition`, {
      onSuccess: onClose
    });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 bg-[rgba(10,9,8,0.4)] z-50 flex items-center justify-center p-4" }, /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "bg-om-card border border-om-line rounded-om shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)] max-w-lg w-full p-6",
      onClick: (e) => e.stopPropagation()
    },
    /* @__PURE__ */ React.createElement("h3", { className: "text-[17px] font-semibold tracking-[-0.01em] text-om-ink mb-3" }, __("Record Disposition")),
    /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "space-y-2 mb-4" }, DISPOSITION_OPTIONS.map(({ value, label, desc }) => /* @__PURE__ */ React.createElement(
      "label",
      {
        key: value,
        className: "flex items-start gap-2 p-2 rounded-om-sm border border-om-line hover:bg-om-chip cursor-pointer transition-colors"
      },
      /* @__PURE__ */ React.createElement(
        "input",
        {
          type: "radio",
          name: "disposition",
          value,
          required: true,
          checked: form.data.disposition === value,
          onChange: () => form.setData("disposition", value),
          className: "sr-only"
        }
      ),
      /* @__PURE__ */ React.createElement(
        "span",
        {
          "aria-hidden": true,
          className: `mt-0.5 flex size-[18px] shrink-0 items-center justify-center rounded-full border-2 ${form.data.disposition === value ? "border-om-accent" : "border-om-faintest"}`
        },
        form.data.disposition === value && /* @__PURE__ */ React.createElement("span", { className: "size-2 rounded-full bg-om-accent" })
      ),
      /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "font-medium text-[13px] text-om-ink capitalize" }, label), /* @__PURE__ */ React.createElement("div", { className: "text-[11.5px] text-om-muted" }, desc))
    ))), form.errors.disposition && /* @__PURE__ */ React.createElement("p", { className: "text-[11.5px] text-om-blocked mb-2" }, form.errors.disposition), /* @__PURE__ */ React.createElement(
      TextField,
      {
        multiline: true,
        value: form.data.notes,
        onChange: (v) => form.setData("notes", v),
        placeholder: __("Notes (optional)"),
        error: form.errors.notes
      }
    ), /* @__PURE__ */ React.createElement("div", { className: "flex justify-end gap-2 mt-3" }, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose }, __("Cancel")), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: form.processing }, __("Save"))))
  ));
}
function ResultsEntryForm({ inspection }) {
  const [rows, setRows] = useState(
    () => (inspection.results ?? []).map((r) => ({
      id: r.id,
      criterion_name: r.criterion_name,
      criterion_type: r.criterion_type,
      spec_min: r.spec_min,
      spec_max: r.spec_max,
      unit: r.unit,
      required: r.required,
      value_numeric: r.value_numeric ?? "",
      value_boolean: r.value_boolean === true ? "1" : r.value_boolean === false ? "0" : "",
      notes: r.notes ?? ""
    }))
  );
  const [saving, setSaving] = useState(false);
  const updateRow = (idx, key, val) => {
    setRows((prev) => prev.map((row, i) => i === idx ? { ...row, [key]: val } : row));
  };
  const saveProgress = (e) => {
    e.preventDefault();
    setSaving(true);
    const payload = rows.map((row) => {
      const entry = { id: row.id };
      if (row.criterion_type === "measurement") {
        entry.value_numeric = row.value_numeric !== "" ? row.value_numeric : null;
      } else {
        entry.value_boolean = row.value_boolean !== "" ? row.value_boolean : null;
      }
      entry.notes = row.notes || null;
      return entry;
    });
    router.post(
      `/inspections/${inspection.id}/results`,
      { results: payload },
      { onFinish: () => setSaving(false) }
    );
  };
  if (rows.length === 0) return null;
  return /* @__PURE__ */ React.createElement("form", { onSubmit: saveProgress, className: `${CARD_CLASS} mb-4` }, /* @__PURE__ */ React.createElement("h2", { className: SECTION_HEADING_CLASS }, __("Record measurements")), /* @__PURE__ */ React.createElement("div", { className: "overflow-x-auto" }, /* @__PURE__ */ React.createElement("table", { className: "w-full text-[13px]" }, /* @__PURE__ */ React.createElement("thead", null, /* @__PURE__ */ React.createElement("tr", { className: "border-b border-om-line" }, /* @__PURE__ */ React.createElement("th", { className: TH_CLASS }, __("Criterion")), /* @__PURE__ */ React.createElement("th", { className: TH_CLASS }, __("Type")), /* @__PURE__ */ React.createElement("th", { className: TH_CLASS }, __("Spec")), /* @__PURE__ */ React.createElement("th", { className: TH_CLASS }, __("Value")), /* @__PURE__ */ React.createElement("th", { className: TH_CLASS }, __("Notes")))), /* @__PURE__ */ React.createElement("tbody", { className: "divide-y divide-om-line" }, rows.map((row, idx) => /* @__PURE__ */ React.createElement("tr", { key: row.id }, /* @__PURE__ */ React.createElement("td", { className: "p-2 font-medium text-om-ink" }, row.criterion_name, row.required && /* @__PURE__ */ React.createElement("span", { className: "text-om-accent ml-0.5", title: __("Required") }, "*")), /* @__PURE__ */ React.createElement("td", { className: "p-2 text-om-muted" }, row.criterion_type), /* @__PURE__ */ React.createElement("td", { className: "p-2 text-om-muted font-mono text-[12px]" }, row.criterion_type === "measurement" ? `${row.spec_min ?? "\u2212\u221E"} \u2026 ${row.spec_max ?? "+\u221E"} ${row.unit ?? ""}` : "\u2014"), /* @__PURE__ */ React.createElement("td", { className: "p-2" }, row.criterion_type === "measurement" ? /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      step: "0.0001",
      value: row.value_numeric,
      onChange: (e) => updateRow(idx, "value_numeric", e.target.value),
      className: `${INPUT_CLASS} w-32 font-mono`
    }
  ) : /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: row.value_boolean == null ? "" : String(row.value_boolean),
      onChange: (v) => updateRow(idx, "value_boolean", v),
      options: [
        { value: "", label: "\u2014" },
        { value: "1", label: __("Pass") },
        { value: "0", label: __("Fail") }
      ],
      className: "w-28"
    }
  )), /* @__PURE__ */ React.createElement("td", { className: "p-2" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: row.notes,
      onChange: (e) => updateRow(idx, "notes", e.target.value),
      maxLength: 1e3,
      className: `${INPUT_CLASS} w-full`
    }
  ))))))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 justify-end mt-3" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "secondary", loading: saving }, __("Save progress"))));
}
function CompleteForm({ inspection }) {
  const form = useForm({ notes: inspection.notes ?? "" });
  const [confirmOpen, setConfirmOpen] = useState(false);
  const submit = (e) => {
    e.preventDefault();
    setConfirmOpen(true);
  };
  const confirmComplete = () => {
    setConfirmOpen(false);
    form.post(`/inspections/${inspection.id}/complete`);
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: SECTION_HEADING_CLASS }, __("Complete inspection")), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted mb-2" }, "Pass/fail is computed from the recorded results above. If any required criterion fails, a non-conformance issue is created automatically."), /* @__PURE__ */ React.createElement(
    TextField,
    {
      multiline: true,
      rows: 2,
      value: form.data.notes,
      onChange: (v) => form.setData("notes", v),
      placeholder: __("Optional notes\u2026"),
      error: form.errors.notes,
      className: "mb-3"
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "text-right" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", loading: form.processing }, __("Complete"))), /* @__PURE__ */ React.createElement(
    ConfirmDialog,
    {
      open: confirmOpen,
      onClose: () => setConfirmOpen(false),
      onConfirm: confirmComplete,
      destructive: false,
      title: "Complete this inspection?",
      confirmLabel: __("Complete"),
      cancelLabel: __("Cancel")
    },
    "It cannot be edited afterwards."
  ));
}
function ResultsTable({ results, notes }) {
  function resultValue(r) {
    if (r.value_numeric != null) return r.value_numeric;
    if (r.value_boolean === true) return "pass";
    if (r.value_boolean === false) return "fail";
    return r.value_text ?? "\u2014";
  }
  function passBadge(isPassed) {
    if (isPassed === true) return /* @__PURE__ */ React.createElement(StatusPill, { status: "running", pulse: false, label: "\u2713 Pass" });
    if (isPassed === false) return /* @__PURE__ */ React.createElement(StatusPill, { status: "blocked", label: "\u2717 Fail" });
    return /* @__PURE__ */ React.createElement(StatusPill, { status: "pending", label: "\u2014" });
  }
  const columns = useMemo(() => [
    {
      id: "criterion_name",
      accessorKey: "criterion_name",
      header: __("Criterion"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.criterion_name)
    },
    {
      id: "criterion_type",
      accessorKey: "criterion_type",
      header: __("Type"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.criterion_type)
    },
    {
      id: "spec",
      accessorFn: (r) => r.criterion_type === "measurement" ? `${r.spec_min ?? "\u2212\u221E"} \u2026 ${r.spec_max ?? "+\u221E"} ${r.unit ?? ""}` : "\u2014",
      header: __("Spec"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted font-mono text-[12px]" }, row.original.criterion_type === "measurement" ? `${row.original.spec_min ?? "\u2212\u221E"} \u2026 ${row.original.spec_max ?? "+\u221E"} ${row.original.unit ?? ""}` : "\u2014")
    },
    {
      id: "value",
      accessorFn: (r) => resultValue(r),
      header: __("Value"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-ink" }, resultValue(row.original))
    },
    {
      id: "result",
      accessorKey: "is_passed",
      header: __("Result"),
      cell: ({ row }) => passBadge(row.original.is_passed)
    }
  ], []);
  return /* @__PURE__ */ React.createElement("div", { className: CARD_CLASS }, /* @__PURE__ */ React.createElement("h2", { className: SECTION_HEADING_CLASS }, __("Results")), results.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-[13px] text-om-muted" }, __("No results recorded.")) : /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: results,
      columns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: __("No results recorded.")
    }
  ), notes && /* @__PURE__ */ React.createElement("div", { className: "mt-3 text-[12.5px] text-om-muted" }, /* @__PURE__ */ React.createElement("strong", { className: "text-om-ink" }, __("Notes:")), " ", notes), /* @__PURE__ */ React.createElement("div", { className: "text-right mt-3" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/inspections",
      className: "inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
    },
    __("Back")
  )));
}
function InspectionsShow() {
  const { inspection } = usePage().props;
  const isPending = inspection.status === "pending";
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Inspection #:id", { id: inspection.id }) }), /* @__PURE__ */ React.createElement("div", { className: "max-w-4xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-start mb-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[22px] font-semibold tracking-[-0.02em] text-om-ink" }, "Inspection #", inspection.id, " \u2014 ", inspection.material?.name ?? "\u2014"), /* @__PURE__ */ React.createElement("p", { className: "text-[13px] text-om-muted mt-1" }, "Lot: ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-ink" }, inspection.lot_number), inspection.quantity_received != null && /* @__PURE__ */ React.createElement(React.Fragment, null, " \xB7 ", __("Qty"), ": ", fmtNum(inspection.quantity_received)), " \xB7 ", __("Inspector"), ": ", inspection.inspector?.name ?? "\u2014", " \xB7 ", __("Started"), ": ", fmtDateTime(inspection.started_at))), /* @__PURE__ */ React.createElement(
    StatusPill,
    {
      status: statusPill(inspection.status),
      pulse: false,
      label: (inspection.status ?? "").replace(/_/g, " ")
    }
  )), /* @__PURE__ */ React.createElement(DispositionSection, { inspection }), inspection.issue_id && /* @__PURE__ */ React.createElement("div", { className: "mb-4 rounded-om border border-om-line border-l-4 border-l-om-blocked bg-om-blocked-bg p-5" }, /* @__PURE__ */ React.createElement("strong", { className: "text-[13px] text-om-blocked" }, "Non-conformance created: Issue #", inspection.issue_id), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-blocked mt-1" }, __("A non-conformance issue was auto-generated because this inspection failed."))), isPending && (inspection.results ?? []).length > 0 && /* @__PURE__ */ React.createElement(ResultsEntryForm, { inspection }), isPending && /* @__PURE__ */ React.createElement(CompleteForm, { inspection }), !isPending && /* @__PURE__ */ React.createElement(
    ResultsTable,
    {
      results: inspection.results ?? [],
      notes: inspection.notes
    }
  )));
}
InspectionsShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  InspectionsShow as default
};
