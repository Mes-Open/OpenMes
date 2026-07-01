import { useMemo, useState } from "react";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { Badge, Button, Checkbox, Dropdown, ProgressBar, StatusPill } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import OperatorLayout from "../../layouts/OperatorLayout";
import LineSync from "../../components/LineSync";
import LabelPrintMenu from "../../components/LabelPrintMenu";
import CustomFields from "../../components/CustomFields";
import { customFieldInitial, customFieldProps, submitForm } from "../../lib/customFieldForm";
import { formatDate, formatDateTime, formatNumber } from "../../lib/i18n";
function fmtQty(n, decimals = 2) {
  if (n == null) return "\u2014";
  return formatNumber(Number(n), { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}
function pillStatus(status) {
  const map = {
    PENDING: "pending",
    IN_PROGRESS: "running",
    DONE: "done",
    BLOCKED: "blocked",
    CANCELLED: "done"
  };
  return map[status] ?? "pending";
}
function issuePillStatus(status) {
  const map = {
    OPEN: "blocked",
    ACKNOWLEDGED: "downtime",
    RESOLVED: "running"
  };
  return map[status] ?? "pending";
}
function statusLabel(status) {
  if (status === "PENDING") return "Not Started";
  return (status ?? "").replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}
function bomTypeBadge(type) {
  const map = {
    raw_material: "text-om-downtime bg-om-downtime-bg",
    semi_finished: "text-om-accent bg-om-selected",
    packaging: "text-om-muted bg-om-chip"
  };
  return map[type] ?? "text-om-muted bg-om-chip";
}
const cardCls = "bg-om-card border border-om-line rounded-om p-6";
const sectionLabelCls = "font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint";
const fieldLabelCls = "block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint";
const inputCls = "w-full text-[13px] text-om-ink placeholder:text-om-faint bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 outline-none transition-colors focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]";
const errorCls = "mt-[5px] text-[11.5px] text-om-blocked";
function ChevronIcon({ open }) {
  return /* @__PURE__ */ React.createElement(
    "svg",
    {
      className: `w-5 h-5 text-om-faint transition-transform ${open ? "rotate-180" : ""}`,
      fill: "none",
      stroke: "currentColor",
      viewBox: "0 0 24 24"
    },
    /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 9l-7 7-7-7" })
  );
}
function ModalShell({ title, subtitle, onClose, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-50 overflow-y-auto" }, /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 bg-[rgba(10,9,8,0.4)]", onClick: onClose }), /* @__PURE__ */ React.createElement("div", { className: "flex min-h-full items-center justify-center p-4" }, /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "relative w-full max-w-md overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]",
      onClick: (e) => e.stopPropagation()
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between border-b border-om-line2 px-[18px] py-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "text-[15px] font-semibold text-om-ink" }, title), subtitle != null && /* @__PURE__ */ React.createElement("div", { className: "mt-[3px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, subtitle)), /* @__PURE__ */ React.createElement(
      "button",
      {
        type: "button",
        onClick: onClose,
        className: "cursor-pointer text-[18px] leading-none text-om-faint hover:text-om-muted"
      },
      "\xD7"
    )),
    children
  )));
}
const modalFooterCls = "flex justify-end gap-[9px] border-t border-om-line2 bg-om-panel px-[18px] py-[14px]";
function BomSection({ workOrder }) {
  const [open, setOpen] = useState(false);
  const bom = workOrder.process_snapshot?.bom;
  const columns = useMemo(() => [
    {
      id: "material",
      accessorFn: (r) => r.material_name,
      header: "Material",
      meta: { align: "left", flex: true },
      cell: ({ row }) => /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.material_name), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint font-mono ml-1" }, row.original.material_code))
    },
    {
      id: "type",
      accessorFn: (r) => r.material_type,
      header: "Type",
      meta: { align: "left" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded-[20px] font-mono text-[10px] uppercase tracking-[0.06em] ${bomTypeBadge(row.original.material_type)}` }, row.original.material_type.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase()))
    },
    {
      id: "per_unit",
      accessorFn: (r) => r.quantity_per_unit,
      header: "Per Unit",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-ink" }, row.original.quantity_per_unit, " ", row.original.unit_of_measure)
    },
    {
      id: "total",
      accessorFn: (r) => {
        const base = r.quantity_per_unit * workOrder.planned_qty;
        return base + base * (r.scrap_percentage / 100);
      },
      header: `Total (${Math.round(workOrder.planned_qty)} pcs)`,
      meta: { align: "right" },
      cell: ({ row }) => {
        const item = row.original;
        const base = item.quantity_per_unit * workOrder.planned_qty;
        const scrap = base * (item.scrap_percentage / 100);
        const total = base + scrap;
        return /* @__PURE__ */ React.createElement("span", { className: "font-mono font-medium text-om-ink" }, fmtQty(total), " ", item.unit_of_measure, item.scrap_percentage > 0 && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint ml-1" }, "(+", item.scrap_percentage, "% scrap)"));
      }
    },
    {
      id: "step",
      accessorFn: (r) => r.step_number,
      header: "Step",
      meta: { align: "left" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[12px] text-om-muted" }, row.original.step_number ? `#${row.original.step_number}` : "General")
    }
  ], [workOrder.planned_qty]);
  if (!bom || bom.length === 0) return null;
  return /* @__PURE__ */ React.createElement("div", { className: cardCls }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      className: "flex justify-between items-center w-full text-left cursor-pointer",
      onClick: () => setOpen((v) => !v)
    },
    /* @__PURE__ */ React.createElement("h2", { className: sectionLabelCls }, "Recipe / Materials"),
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(Badge, { variant: "neutral" }, bom.length, " items"), /* @__PURE__ */ React.createElement(ChevronIcon, { open }))
  ), open && /* @__PURE__ */ React.createElement("div", { className: "mt-4" }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: bom,
      columns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  )));
}
function ProcessPhotosSection({ photos = [] }) {
  const [open, setOpen] = useState(true);
  const [lightbox, setLightbox] = useState(null);
  if (!photos || photos.length === 0) return null;
  return /* @__PURE__ */ React.createElement("div", { className: cardCls }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      className: "flex justify-between items-center w-full text-left cursor-pointer",
      onClick: () => setOpen((v) => !v)
    },
    /* @__PURE__ */ React.createElement("h2", { className: sectionLabelCls }, "Work Instructions"),
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(Badge, { variant: "neutral" }, photos.length, " photos"), /* @__PURE__ */ React.createElement(ChevronIcon, { open }))
  ), open && /* @__PURE__ */ React.createElement("div", { className: "mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4" }, photos.map((photo) => /* @__PURE__ */ React.createElement("figure", { key: photo.id, className: "m-0" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setLightbox(photo),
      className: "block w-full cursor-pointer",
      title: photo.caption || ""
    },
    /* @__PURE__ */ React.createElement(
      "img",
      {
        src: photo.url,
        alt: photo.caption || "Work instruction",
        loading: "lazy",
        className: "w-full h-32 object-cover rounded-om-sm border border-om-line bg-om-chip"
      }
    )
  ), photo.caption && /* @__PURE__ */ React.createElement("figcaption", { className: "mt-1 text-xs text-om-muted truncate" }, photo.caption)))), lightbox && /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-6",
      onClick: () => setLightbox(null)
    },
    /* @__PURE__ */ React.createElement("figure", { className: "max-w-4xl max-h-full m-0", onClick: (e) => e.stopPropagation() }, /* @__PURE__ */ React.createElement(
      "img",
      {
        src: lightbox.url,
        alt: lightbox.caption || "Work instruction",
        className: "max-w-full max-h-[80vh] rounded-om shadow-2xl"
      }
    ), lightbox.caption && /* @__PURE__ */ React.createElement("figcaption", { className: "text-white/90 text-sm mt-3 text-center" }, lightbox.caption)),
    /* @__PURE__ */ React.createElement(
      "button",
      {
        type: "button",
        onClick: () => setLightbox(null),
        className: "absolute top-5 right-5 text-white/80 hover:text-white text-3xl leading-none cursor-pointer",
        title: "Close"
      },
      "\xD7"
    )
  ));
}
function QualityCheckForm({ batch, onClose }) {
  const [productionQty, setProductionQty] = useState("");
  const [samples, setSamples] = useState(
    () => [1, 2, 3].flatMap((s) => [
      { sample_number: s, parameter_name: "Dimension", parameter_type: "measurement", value_numeric: "", is_passed: "1" },
      { sample_number: s, parameter_name: "Fit check", parameter_type: "pass_fail", value_boolean: "1", is_passed: "1" }
    ])
  );
  const [processing, setProcessing] = useState(false);
  const updateSample = (idx, key, val) => {
    setSamples((prev) => prev.map((s, i) => i === idx ? { ...s, [key]: val } : s));
  };
  const submit = (e) => {
    e.preventDefault();
    setProcessing(true);
    const payload = {
      production_quantity: productionQty || void 0,
      samples: samples.map((s) => ({
        sample_number: s.sample_number,
        parameter_name: s.parameter_name,
        parameter_type: s.parameter_type,
        ...s.parameter_type === "measurement" ? { value_numeric: parseFloat(s.value_numeric), is_passed: 1 } : { value_boolean: parseInt(s.value_boolean, 10), is_passed: 1 }
      }))
    };
    router.post(`/operator/batch/${batch.id}/quality-check`, payload, {
      onFinish: () => setProcessing(false)
    });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "mt-3 p-4 bg-om-panel border border-om-line2 rounded-om-sm" }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "mb-3" }, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Production Quantity"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      step: "0.01",
      value: productionQty,
      onChange: (e) => setProductionQty(e.target.value),
      className: `${inputCls} font-mono`,
      placeholder: "Current production qty"
    }
  )), [1, 2, 3].map((s) => {
    const dimIdx = (s - 1) * 2;
    const fitIdx = (s - 1) * 2 + 1;
    return /* @__PURE__ */ React.createElement("div", { key: s, className: "mb-2 p-2 bg-om-card border border-om-line2 rounded-om-sm" }, /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1" }, "Sample #", s), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-2" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "number",
        step: "0.01",
        value: samples[dimIdx].value_numeric,
        onChange: (e) => updateSample(dimIdx, "value_numeric", e.target.value),
        className: `${inputCls} font-mono`,
        placeholder: "Dimension",
        required: true
      }
    )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: [
          { value: "1", label: "Pass" },
          { value: "0", label: "Fail" }
        ],
        value: samples[fitIdx].value_boolean == null ? "" : String(samples[fitIdx].value_boolean),
        onChange: (v) => updateSample(fitIdx, "value_boolean", v),
        className: "w-full"
      }
    ))));
  }), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 mt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", disabled: processing, className: "px-5 py-3 text-[14px]" }, "Submit QC"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose, className: "px-5 py-3 text-[14px]" }, "Cancel"))));
}
function PackagingChecklistForm({ batch, onClose }) {
  const form = useForm({
    udi_readable: false,
    packaging_condition: false,
    labels_readable: false,
    label_matches_product: false,
    notes: ""
  });
  const submit = (e) => {
    e.preventDefault();
    form.post(`/operator/batch/${batch.id}/packaging-checklist`);
  };
  const checks = [
    ["udi_readable", "UDI code readable"],
    ["packaging_condition", "Packaging in good condition"],
    ["labels_readable", "Labels readable"],
    ["label_matches_product", "Label matches product"]
  ];
  return /* @__PURE__ */ React.createElement("div", { className: "mt-3 p-4 bg-om-panel border border-om-line2 rounded-om-sm" }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, checks.map(([field, label]) => /* @__PURE__ */ React.createElement("div", { key: field, className: "mb-2" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: form.data[field],
      onChange: (next) => form.setData(field, next),
      label
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 mt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", disabled: form.processing, className: "px-5 py-3 text-[14px]" }, "Submit Checklist"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose, className: "px-5 py-3 text-[14px]" }, "Cancel"))));
}
function ReleaseForm({ batch, onClose }) {
  const form = useForm({ scrap_qty: "", release_type: "" });
  const submitWith = (releaseType) => {
    form.setData("release_type", releaseType);
    form.post(`/operator/batch/${batch.id}/release`, {
      data: { ...form.data, release_type: releaseType }
    });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "mt-3 p-4 bg-om-panel border border-om-line2 rounded-om-sm" }, /* @__PURE__ */ React.createElement("div", { className: "mb-3" }, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Scrap quantity (optional)"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      step: "0.01",
      min: "0",
      value: form.data.scrap_qty,
      onChange: (e) => form.setData("scrap_qty", e.target.value),
      className: `${inputCls} w-32 font-mono`,
      placeholder: "0"
    }
  )), /* @__PURE__ */ React.createElement("p", { className: "text-sm mb-3 text-om-muted" }, "Release this batch?"), /* @__PURE__ */ React.createElement("div", { className: "flex gap-3 flex-wrap" }, /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "secondary",
      disabled: form.processing,
      onClick: () => submitWith("for_production"),
      className: "px-5 py-3 text-[14px]"
    },
    "For Production"
  ), /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "accent",
      disabled: form.processing,
      onClick: () => submitWith("for_sale"),
      className: "px-5 py-3 text-[14px]"
    },
    "For Sale"
  ), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose, className: "px-5 py-3 text-[14px]" }, "Cancel")));
}
function ConfirmParametersRow({ batch }) {
  const [processing, setProcessing] = useState(false);
  const lastConfirm = (batch.process_confirmations ?? []).filter((c) => c.confirmation_type === "parameters" && c.confirmed_at).sort((a, b) => new Date(b.confirmed_at) - new Date(a.confirmed_at))[0];
  const handleClick = () => {
    setProcessing(true);
    router.post(
      `/operator/batch/${batch.id}/confirm`,
      { confirmation_type: "parameters" },
      { onFinish: () => setProcessing(false) }
    );
  };
  return /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "secondary",
      disabled: processing,
      onClick: handleClick,
      className: "px-5 py-3 text-[14px]"
    },
    "Confirm Parameters"
  ), lastConfirm && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-running" }, "Last: ", formatDateTime(new Date(lastConfirm.confirmed_at), { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" })));
}
function ProductionControls({ batch }) {
  const [showQc, setShowQc] = useState(false);
  const [showChecklist, setShowChecklist] = useState(false);
  const [showRelease, setShowRelease] = useState(false);
  const qcCount = (batch.quality_checks ?? []).length;
  const hasChecklist = !!batch.packaging_checklist;
  const isReleased = !!batch.released_at;
  return /* @__PURE__ */ React.createElement("div", { className: "border-t border-om-line2 pt-4 space-y-3" }, /* @__PURE__ */ React.createElement("h4", { className: sectionLabelCls }, "Production Controls"), /* @__PURE__ */ React.createElement(ConfirmParametersRow, { batch }), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "secondary",
      onClick: () => setShowQc((v) => !v),
      className: "px-5 py-3 text-[14px]"
    },
    "Quality Check (",
    qcCount,
    ")"
  ), qcCount < 3 ? /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-downtime" }, 3 - qcCount, " more needed") : /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-running" }, "Min. requirement met")), showQc && /* @__PURE__ */ React.createElement(QualityCheckForm, { batch, onClose: () => setShowQc(false) })), !hasChecklist ? /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "secondary",
      onClick: () => setShowChecklist((v) => !v),
      className: "px-5 py-3 text-[14px]"
    },
    "Packaging Checklist"
  ), showChecklist && /* @__PURE__ */ React.createElement(PackagingChecklistForm, { batch, onClose: () => setShowChecklist(false) })) : /* @__PURE__ */ React.createElement("div", { className: "text-sm" }, /* @__PURE__ */ React.createElement("span", { className: batch.packaging_checklist.all_passed ? "text-om-running" : "text-om-blocked" }, "Packaging: ", batch.packaging_checklist.all_passed ? "All passed" : "Some items failed")), batch.status === "DONE" && !isReleased && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "accent",
      onClick: () => setShowRelease((v) => !v),
      className: "px-6 py-3.5 text-[15px]"
    },
    "Release Batch"
  ), showRelease && /* @__PURE__ */ React.createElement(ReleaseForm, { batch, onClose: () => setShowRelease(false) })), isReleased && usePage().props.auth?.user?.roles?.includes("Admin") && /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/admin/batches/${batch.id}/report`,
      target: "_blank",
      rel: "noreferrer",
      className: "inline-flex items-center justify-center rounded-om-sm border border-om-line bg-transparent px-5 py-3 text-[14px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
    },
    "Series Report"
  ));
}
function BatchCard({ batch, defaultOpen, labelTemplates = [], stepPhotos = {} }) {
  const [expanded, setExpanded] = useState(defaultOpen);
  const showControls = batch.status === "IN_PROGRESS" || batch.status === "DONE";
  const releaseLabel = batch.release_type === "for_sale" ? "For Sale" : "For Production";
  const isReleased = !!(batch.released_at || batch.released);
  return /* @__PURE__ */ React.createElement("div", { className: "border border-om-line rounded-om p-4 bg-om-card" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      className: "flex flex-1 justify-between items-center text-left cursor-pointer",
      onClick: () => setExpanded((v) => !v)
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-4" }, /* @__PURE__ */ React.createElement("h3", { className: "text-[16px] font-semibold tracking-[-0.01em] text-om-ink" }, "Batch #", batch.batch_number), /* @__PURE__ */ React.createElement(StatusPill, { status: pillStatus(batch.status), label: statusLabel(batch.status) }), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-muted" }, fmtQty(batch.produced_qty), " / ", fmtQty(batch.target_qty))),
    /* @__PURE__ */ React.createElement(
      "svg",
      {
        className: `w-6 h-6 text-om-faint transition-transform ${expanded ? "rotate-180" : ""}`,
        fill: "none",
        stroke: "currentColor",
        viewBox: "0 0 24 24"
      },
      /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 9l-7 7-7-7" })
    )
  ), isReleased && /* @__PURE__ */ React.createElement("div", { onClick: (e) => e.stopPropagation() }, /* @__PURE__ */ React.createElement(
    LabelPrintMenu,
    {
      kind: "finished-goods",
      id: batch.id,
      templates: labelTemplates,
      label: "FG Label"
    }
  ))), expanded && /* @__PURE__ */ React.createElement("div", { className: "mt-4 space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-4 text-sm bg-om-panel border border-om-line2 p-3 rounded-om-sm" }, batch.lot_number && /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, "LOT: ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-accent" }, batch.lot_number)), batch.workstation && /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, "Workstation: ", batch.workstation.name), isReleased && /* @__PURE__ */ React.createElement("span", { className: "text-om-running font-medium" }, "Released (", releaseLabel, ")"), batch.expiry_date && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-muted" }, "Expiry: ", batch.expiry_date)), /* @__PURE__ */ React.createElement(BatchStepList, { steps: batch.steps ?? [], labelTemplates, stepPhotos }), showControls && /* @__PURE__ */ React.createElement(ProductionControls, { batch })));
}
function BatchStepList({ steps, labelTemplates = [], stepPhotos = {} }) {
  const [inflightStepId, setInflightStepId] = useState(null);
  const [photoZoom, setPhotoZoom] = useState(null);
  const [pickModal, setPickModal] = useState(null);
  if (!steps || steps.length === 0) return null;
  const handleStepAction = (step, action) => {
    setInflightStepId(step.id);
    router.post(
      `/operator/batch-step/${step.id}/${action}`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setInflightStepId(null)
      }
    );
  };
  const handleStart = async (step) => {
    setInflightStepId(step.id);
    try {
      const res = await fetch(`/operator/batch-step/${step.id}/pick-preview`, {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin"
      });
      if (res.ok) {
        const data = await res.json();
        if (data.materials && data.materials.length > 0) {
          setInflightStepId(null);
          setPickModal({ step, materials: data.materials });
          return;
        }
      }
    } catch {
    }
    router.post(
      `/operator/batch-step/${step.id}/start`,
      {},
      { preserveScroll: true, onFinish: () => setInflightStepId(null) }
    );
  };
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h4", { className: `${sectionLabelCls} mb-2` }, "Steps"), /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, steps.map((step) => {
    const isInflight = inflightStepId === step.id;
    const photo = stepPhotos[step.step_number];
    return /* @__PURE__ */ React.createElement("div", { key: step.id, className: "flex items-center gap-3 p-3 bg-om-panel border border-om-line2 rounded-om-sm" }, /* @__PURE__ */ React.createElement("span", { className: "w-7 h-7 flex-shrink-0 flex items-center justify-center rounded-full font-mono text-[11px] bg-om-chip text-om-muted" }, step.step_number), photo && /* @__PURE__ */ React.createElement(
      "button",
      {
        type: "button",
        onClick: () => setPhotoZoom(photo),
        className: "flex-shrink-0 cursor-pointer",
        title: photo.caption || "Step photo"
      },
      /* @__PURE__ */ React.createElement(
        "img",
        {
          src: photo.url,
          alt: photo.caption || "Step photo",
          loading: "lazy",
          className: "w-12 h-12 object-cover rounded-om-sm border border-om-line bg-om-chip"
        }
      )
    ), /* @__PURE__ */ React.createElement("span", { className: "flex-1 text-sm font-medium text-om-ink" }, step.name), step.status === "DONE" && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-done whitespace-nowrap" }, "Done", step.completed_by ? ` by ${step.completed_by.name}` : ""), step.status === "SKIPPED" && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-faint whitespace-nowrap" }, "Skipped"), step.status === "IN_PROGRESS" && !inflightStepId && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-running whitespace-nowrap" }, "In progress", step.started_by ? ` by ${step.started_by.name}` : ""), !step.status && step.completed_at && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-done whitespace-nowrap" }, "Done", step.completed_by ? ` by ${step.completed_by.name}` : ""), !step.status && !step.completed_at && step.started_at && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-running whitespace-nowrap" }, "In progress", step.started_by ? ` by ${step.started_by.name}` : ""), (step.status === "PENDING" || step.status === "READY") && /* @__PURE__ */ React.createElement(
      Button,
      {
        variant: "accent",
        disabled: isInflight,
        onClick: () => handleStart(step),
        className: "px-6 py-3.5 text-[15px] whitespace-nowrap"
      },
      isInflight ? "\u2026" : "Start"
    ), step.status === "IN_PROGRESS" && /* @__PURE__ */ React.createElement(
      Button,
      {
        variant: "primary",
        disabled: isInflight,
        onClick: () => handleStepAction(step, "complete"),
        className: "px-6 py-3.5 text-[15px] whitespace-nowrap"
      },
      isInflight ? "\u2026" : "Complete"
    ), /* @__PURE__ */ React.createElement(
      LabelPrintMenu,
      {
        kind: "workstation-step",
        id: step.id,
        templates: labelTemplates,
        label: "Label"
      }
    ));
  })), photoZoom && /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-6", onClick: () => setPhotoZoom(null) }, /* @__PURE__ */ React.createElement("figure", { className: "max-w-3xl max-h-full m-0", onClick: (e) => e.stopPropagation() }, /* @__PURE__ */ React.createElement("img", { src: photoZoom.url, alt: photoZoom.caption || "Step photo", className: "max-w-full max-h-[80vh] rounded-om shadow-2xl" }), photoZoom.caption && /* @__PURE__ */ React.createElement("figcaption", { className: "text-white/90 text-sm mt-3 text-center" }, photoZoom.caption))), pickModal && /* @__PURE__ */ React.createElement(
    LotPickModal,
    {
      step: pickModal.step,
      materials: pickModal.materials,
      onClose: () => setPickModal(null)
    }
  ));
}
const EPSILON = 1e-4;
function LotPickModal({ step, materials, onClose }) {
  const [submitting, setSubmitting] = useState(false);
  const [picks, setPicks] = useState(
    () => Object.fromEntries(
      materials.map((m) => [
        m.material_id,
        m.proposed.map((p) => ({ material_lot_id: p.material_lot_id, picked_qty: String(p.picked_qty) }))
      ])
    )
  );
  const candById = useMemo(
    () => Object.fromEntries(
      materials.map((m) => [m.material_id, Object.fromEntries(m.candidates.map((c) => [c.id, c]))])
    ),
    [materials]
  );
  const setLineQty = (matId, idx, val) => setPicks((prev) => {
    const lines = [...prev[matId] ?? []];
    lines[idx] = { ...lines[idx], picked_qty: val };
    return { ...prev, [matId]: lines };
  });
  const removeLine = (matId, idx) => setPicks((prev) => ({ ...prev, [matId]: (prev[matId] ?? []).filter((_, i) => i !== idx) }));
  const addLine = (matId, lotId, required) => setPicks((prev) => {
    const lines = prev[matId] ?? [];
    if (lines.some((ln) => ln.material_lot_id === lotId)) return prev;
    const allocated = lines.reduce((s, ln) => s + (Number(ln.picked_qty) || 0), 0);
    const cand = candById[matId][lotId];
    const want = Math.max(required - allocated, 0);
    const qty = Math.min(want > 0 ? want : cand.quantity_available, cand.quantity_available);
    return { ...prev, [matId]: [...lines, { material_lot_id: lotId, picked_qty: String(round4(qty)) }] };
  });
  const materialValid = (m) => {
    const lines = picks[m.material_id] ?? [];
    if (lines.length === 0) return false;
    let sum = 0;
    for (const ln of lines) {
      const q = Number(ln.picked_qty);
      const cand = candById[m.material_id][ln.material_lot_id];
      if (!(q > 0) || !cand || q > cand.quantity_available + EPSILON) return false;
      sum += q;
    }
    return Math.abs(sum - m.required_qty) < EPSILON;
  };
  const allValid = materials.every(materialValid);
  const submit = (e) => {
    e.preventDefault();
    if (!allValid) return;
    setSubmitting(true);
    const payload = {
      picks: materials.map((m) => ({
        material_id: m.material_id,
        lots: (picks[m.material_id] ?? []).map((ln) => ({
          material_lot_id: ln.material_lot_id,
          picked_qty: Number(ln.picked_qty)
        }))
      }))
    };
    router.post(`/operator/batch-step/${step.id}/start`, payload, {
      preserveScroll: true,
      onSuccess: onClose,
      onFinish: () => setSubmitting(false)
    });
  };
  return /* @__PURE__ */ React.createElement(ModalShell, { title: "Pick material lots", subtitle: step.name, onClose }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "max-h-[60vh] space-y-5 overflow-y-auto px-[18px] py-4" }, materials.map((m) => {
    const lines = picks[m.material_id] ?? [];
    const allocated = lines.reduce((s, ln) => s + (Number(ln.picked_qty) || 0), 0);
    const balanced = Math.abs(allocated - m.required_qty) < EPSILON;
    const remaining = m.candidates.filter((c) => !lines.some((ln) => ln.material_lot_id === c.id));
    return /* @__PURE__ */ React.createElement("div", { key: m.material_id, className: "rounded-om-sm border border-om-line2 bg-om-panel p-3" }, /* @__PURE__ */ React.createElement("div", { className: "mb-2 flex items-start justify-between gap-2" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-medium text-om-ink" }, m.material_name), /* @__PURE__ */ React.createElement("span", { className: "ml-1 font-mono text-[11px] text-om-faint" }, m.material_code)), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] uppercase tracking-[0.08em] text-om-muted" }, m.strategy)), lines.length === 0 && /* @__PURE__ */ React.createElement("p", { className: errorCls }, m.candidates.length === 0 ? "No lots available for this material" : "Add a lot to allocate the required quantity."), /* @__PURE__ */ React.createElement("div", { className: "space-y-1.5" }, lines.map((ln, idx) => {
      const cand = candById[m.material_id][ln.material_lot_id];
      const over = Number(ln.picked_qty) > (cand?.quantity_available ?? 0) + EPSILON;
      return /* @__PURE__ */ React.createElement("div", { key: ln.material_lot_id, className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "min-w-0 flex-1" }, /* @__PURE__ */ React.createElement("span", { className: "block truncate font-mono text-[12px] text-om-ink" }, cand?.lot_number ?? `#${ln.material_lot_id}`), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] text-om-faint" }, "avail ", fmtQty(cand?.quantity_available, 4), cand?.expiry_date ? ` \xB7 exp ${formatDate(cand.expiry_date)}` : "")), /* @__PURE__ */ React.createElement(
        "input",
        {
          type: "number",
          step: "0.0001",
          min: "0",
          inputMode: "decimal",
          value: ln.picked_qty,
          onChange: (e) => setLineQty(m.material_id, idx, e.target.value),
          className: `${inputCls} w-28 text-right ${over ? "border-om-blocked" : ""}`
        }
      ), /* @__PURE__ */ React.createElement(
        "button",
        {
          type: "button",
          onClick: () => removeLine(m.material_id, idx),
          className: "cursor-pointer px-1 text-[18px] leading-none text-om-faint hover:text-om-blocked",
          title: "Remove lot"
        },
        "\xD7"
      ));
    })), remaining.length > 0 && /* @__PURE__ */ React.createElement(
      "select",
      {
        value: "",
        onChange: (e) => {
          if (e.target.value) addLine(m.material_id, Number(e.target.value), m.required_qty);
        },
        className: `${inputCls} mt-2`
      },
      /* @__PURE__ */ React.createElement("option", { value: "" }, "+ Add lot\u2026"),
      remaining.map((c) => /* @__PURE__ */ React.createElement("option", { key: c.id, value: c.id }, c.lot_number, " \u2014 avail ", fmtQty(c.quantity_available, 4), c.expiry_date ? ` (exp ${formatDate(c.expiry_date)})` : ""))
    ), /* @__PURE__ */ React.createElement("div", { className: "mt-2 flex justify-between font-mono text-[11px]" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "Required ", fmtQty(m.required_qty, 4), " ", m.unit_of_measure), /* @__PURE__ */ React.createElement("span", { className: balanced ? "text-om-done" : "text-om-blocked" }, "Allocated ", fmtQty(allocated, 4))));
  })), /* @__PURE__ */ React.createElement("div", { className: modalFooterCls }, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", type: "button", onClick: onClose }, "Cancel"), /* @__PURE__ */ React.createElement(Button, { variant: "accent", type: "submit", disabled: !allValid || submitting }, submitting ? "\u2026" : "Confirm picks & start"))));
}
function round4(n) {
  return Math.round((Number(n) + Number.EPSILON) * 1e4) / 1e4;
}
function CreateBatchModal({ workOrder, workstations, defaultWorkstationId, onClose }) {
  const remaining = Math.max((workOrder.planned_qty ?? 0) - (workOrder.produced_qty ?? 0), 0);
  const form = useForm({
    work_order_id: workOrder.id,
    target_qty: String(remaining),
    workstation_id: defaultWorkstationId ? String(defaultWorkstationId) : workstations.length === 1 ? String(workstations[0].id) : "",
    lot_number: "",
    auto_lot: false
  });
  const submit = (e) => {
    e.preventDefault();
    form.post("/operator/batch", { onSuccess: onClose });
  };
  return /* @__PURE__ */ React.createElement(ModalShell, { title: "Create New Batch", subtitle: workOrder.order_no, onClose }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "px-[18px] py-4" }, /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Quantity"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      step: "0.01",
      min: "0.01",
      max: remaining,
      value: form.data.target_qty,
      onChange: (e) => form.setData("target_qty", e.target.value),
      className: `${inputCls} font-mono text-[15px]`,
      required: true
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "mt-1 font-mono text-[11px] text-om-faint" }, "Remaining: ", fmtQty(remaining)), form.errors.target_qty && /* @__PURE__ */ React.createElement("p", { className: errorCls }, form.errors.target_qty)), workstations.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Workstation"), workstations.length === 1 ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("input", { type: "hidden", value: workstations[0].id }), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted py-2" }, workstations[0].name)) : /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: workstations.map((ws) => ({ value: String(ws.id), label: ws.name })),
      value: form.data.workstation_id == null ? "" : String(form.data.workstation_id),
      onChange: (v) => form.setData("workstation_id", v),
      placeholder: "\u2014 Select workstation \u2014",
      className: "w-full"
    }
  ), form.errors.workstation_id && /* @__PURE__ */ React.createElement("p", { className: errorCls }, form.errors.workstation_id)), /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: form.data.auto_lot,
      onChange: (next) => form.setData("auto_lot", next),
      label: "Auto-generate LOT number"
    }
  )), !form.data.auto_lot && /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "LOT Number (manual)"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.lot_number,
      onChange: (e) => form.setData("lot_number", e.target.value),
      className: `${inputCls} font-mono`,
      placeholder: "Leave empty for no LOT"
    }
  ), form.errors.lot_number && /* @__PURE__ */ React.createElement("p", { className: errorCls }, form.errors.lot_number))), /* @__PURE__ */ React.createElement("div", { className: modalFooterCls }, /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "secondary",
      onClick: onClose,
      className: "px-6 py-4 text-[15px] font-semibold"
    },
    "Cancel"
  ), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "accent",
      disabled: form.processing,
      className: "px-6 py-4 text-[15px] font-semibold"
    },
    "Create Batch"
  ))));
}
function ReportIssueModal({ workOrder, issueTypes, customFields = [], onClose }) {
  const form = useForm({
    work_order_id: workOrder.id,
    issue_type_id: "",
    title: "",
    description: "",
    ...customFieldInitial()
  });
  const submit = (e) => {
    e.preventDefault();
    submitForm(form, "post", "/operator/issue", { onSuccess: onClose });
  };
  return /* @__PURE__ */ React.createElement(ModalShell, { title: "Report Issue", subtitle: workOrder.order_no, onClose }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "px-[18px] py-4 space-y-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Issue Type ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: issueTypes.map((type) => ({
        value: String(type.id),
        label: `${type.name}${type.is_blocking ? " \u26A0 Blocking" : ""}`
      })),
      value: form.data.issue_type_id == null ? "" : String(form.data.issue_type_id),
      onChange: (v) => form.setData("issue_type_id", v),
      placeholder: "\u2014 Select type \u2014",
      className: "w-full"
    }
  ), form.errors.issue_type_id && /* @__PURE__ */ React.createElement("p", { className: errorCls }, form.errors.issue_type_id)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Title ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.title,
      onChange: (e) => form.setData("title", e.target.value),
      className: inputCls,
      placeholder: "Brief summary of the issue",
      required: true,
      maxLength: 255
    }
  ), form.errors.title && /* @__PURE__ */ React.createElement("p", { className: errorCls }, form.errors.title)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Description"), /* @__PURE__ */ React.createElement(
    "textarea",
    {
      value: form.data.description,
      onChange: (e) => form.setData("description", e.target.value),
      rows: 3,
      className: `${inputCls} resize-none`,
      placeholder: "Additional details\u2026",
      maxLength: 2e3
    }
  ), form.errors.description && /* @__PURE__ */ React.createElement("p", { className: errorCls }, form.errors.description)), customFields.length > 0 && /* @__PURE__ */ React.createElement(CustomFields, { ...customFieldProps(form, customFields) })), /* @__PURE__ */ React.createElement("div", { className: modalFooterCls }, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose, className: "px-6 py-4 text-[15px] font-semibold" }, "Cancel"), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "danger",
      disabled: form.processing,
      className: "px-6 py-4 text-[15px] font-semibold"
    },
    "Report Issue"
  ))));
}
function ReportScrapModal({ workOrder, scrapReasons, onClose }) {
  const form = useForm({
    work_order_id: workOrder.id,
    scrap_reason_id: "",
    quantity: "",
    notes: ""
  });
  const submit = (e) => {
    e.preventDefault();
    form.post("/operator/scrap", { onSuccess: onClose });
  };
  return /* @__PURE__ */ React.createElement(ModalShell, { title: "Report Scrap", subtitle: workOrder.order_no, onClose }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "px-[18px] py-4 space-y-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Reason ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: scrapReasons.map((reason) => ({
        value: String(reason.id),
        label: `${reason.code} \u2014 ${reason.name}`
      })),
      value: form.data.scrap_reason_id == null ? "" : String(form.data.scrap_reason_id),
      onChange: (v) => form.setData("scrap_reason_id", v),
      placeholder: "\u2014 Select reason \u2014",
      className: "w-full"
    }
  ), form.errors.scrap_reason_id && /* @__PURE__ */ React.createElement("p", { className: errorCls }, form.errors.scrap_reason_id)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Quantity ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      step: "0.01",
      min: "0.01",
      value: form.data.quantity,
      onChange: (e) => form.setData("quantity", e.target.value),
      className: `${inputCls} font-mono text-[15px]`,
      placeholder: "0",
      required: true
    }
  ), form.errors.quantity && /* @__PURE__ */ React.createElement("p", { className: errorCls }, form.errors.quantity)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Notes"), /* @__PURE__ */ React.createElement(
    "textarea",
    {
      value: form.data.notes,
      onChange: (e) => form.setData("notes", e.target.value),
      rows: 3,
      className: `${inputCls} resize-none`,
      placeholder: "Additional details\u2026",
      maxLength: 2e3
    }
  ), form.errors.notes && /* @__PURE__ */ React.createElement("p", { className: errorCls }, form.errors.notes))), /* @__PURE__ */ React.createElement("div", { className: modalFooterCls }, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose, className: "px-6 py-4 text-[15px] font-semibold" }, "Cancel"), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "danger",
      disabled: form.processing,
      className: "px-6 py-4 text-[15px] font-semibold"
    },
    "Report Scrap"
  ))));
}
function WorkOrderDetail() {
  const { workOrder, issueTypes = [], scrapReasons = [], workstations = [], issueCustomFields = [], defaultWorkstationId, line, labelTemplates = [], processPhotos = [], stepPhotos = {} } = usePage().props;
  const [createBatchOpen, setCreateBatchOpen] = useState(false);
  const [reportIssueOpen, setReportIssueOpen] = useState(false);
  const [reportScrapOpen, setReportScrapOpen] = useState(false);
  const plannedQty = workOrder.planned_qty ?? 0;
  const producedQty = workOrder.produced_qty ?? 0;
  const remaining = Math.max(plannedQty - producedQty, 0);
  const pct = plannedQty > 0 ? Math.min(producedQty / plannedQty * 100, 100) : 0;
  const canCreateBatch = !["DONE", "CANCELLED", "BLOCKED"].includes(workOrder.status);
  const canReportIssue = !["DONE", "CANCELLED"].includes(workOrder.status);
  const canReportScrap = scrapReasons.length > 0 && !["DONE", "CANCELLED"].includes(workOrder.status);
  const scrapEntries = workOrder.scrap_entries ?? [];
  const totalScrap = scrapEntries.reduce((sum, e) => sum + Number(e.quantity ?? 0), 0);
  const qualityPct = producedQty > 0 ? Math.max(0, producedQty - totalScrap) / producedQty * 100 : null;
  const dueDateStr = workOrder.due_date;
  const dueDatePast = dueDateStr && new Date(dueDateStr) < /* @__PURE__ */ new Date() && workOrder.status !== "DONE";
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `Work Order ${workOrder.order_no}` }), line && /* @__PURE__ */ React.createElement(LineSync, { lineId: line.id, reloadOnly: ["workOrder"] }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("h1", { className: "font-mono text-[28px] font-semibold tracking-[-0.02em] text-om-ink" }, workOrder.order_no), /* @__PURE__ */ React.createElement(StatusPill, { status: pillStatus(workOrder.status), label: statusLabel(workOrder.status) })), workOrder.product_type && /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-2 text-[15px]" }, workOrder.product_type.name)), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement(
    LabelPrintMenu,
    {
      kind: "work-order",
      id: workOrder.id,
      templates: labelTemplates,
      label: "Print WO Label"
    }
  ), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/operator/queue",
      className: "inline-flex items-center justify-center rounded-om-sm border border-om-line bg-om-card px-5 py-3 text-sm font-semibold text-om-ink hover:bg-om-chip transition-colors"
    },
    "\u2190 Back to Queue"
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-2 space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: cardCls }, /* @__PURE__ */ React.createElement("h2", { className: `${sectionLabelCls} mb-4` }, "Work Order Details"), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: fieldLabelCls }, "Order Number"), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[15px] font-medium text-om-ink" }, workOrder.order_no)), workOrder.product_type && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: fieldLabelCls }, "Product Type"), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.product_type.name)), workOrder.line && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: fieldLabelCls }, "Line"), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.line.name)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: fieldLabelCls }, "Priority"), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[15px] font-medium text-om-ink" }, workOrder.priority)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: fieldLabelCls }, "Planned Quantity"), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink" }, fmtQty(plannedQty))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: fieldLabelCls }, "Produced Quantity"), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink" }, fmtQty(producedQty), plannedQty > 0 && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-faint ml-1" }, "(", fmtQty(producedQty / plannedQty * 100, 1), "%)"))), dueDateStr && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: fieldLabelCls }, "Due Date"), /* @__PURE__ */ React.createElement("p", { className: `font-mono text-[15px] font-medium ${dueDatePast ? "text-om-blocked" : "text-om-ink"}` }, formatDate(new Date(dueDateStr), { day: "2-digit", month: "short", year: "numeric" }))), workOrder.description && /* @__PURE__ */ React.createElement("div", { className: "md:col-span-2" }, /* @__PURE__ */ React.createElement("p", { className: fieldLabelCls }, "Description"), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.description)))), /* @__PURE__ */ React.createElement(BomSection, { workOrder }), /* @__PURE__ */ React.createElement(ProcessPhotosSection, { photos: processPhotos }), /* @__PURE__ */ React.createElement("div", { className: cardCls }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-4" }, /* @__PURE__ */ React.createElement("h2", { className: sectionLabelCls }, "Batches"), canCreateBatch && /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "accent",
      onClick: () => setCreateBatchOpen(true),
      className: "px-5 py-3 text-[14px]"
    },
    "+ Create Batch"
  )), !workOrder.batches || workOrder.batches.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "text-center py-8 bg-om-panel border border-om-line2 rounded-om-sm" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint" }, "No batches created yet.")) : /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, workOrder.batches.map((batch, idx) => /* @__PURE__ */ React.createElement(
    BatchCard,
    {
      key: batch.id,
      batch,
      defaultOpen: idx === 0,
      labelTemplates,
      stepPhotos
    }
  ))))), /* @__PURE__ */ React.createElement("div", { className: "space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: cardCls }, /* @__PURE__ */ React.createElement("h3", { className: `${sectionLabelCls} mb-4` }, "Progress"), /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-baseline mb-2" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint" }, "Completion"), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-ink" }, fmtQty(pct, 1), "%")), /* @__PURE__ */ React.createElement(ProgressBar, { value: pct, color: pct >= 100 ? "var(--color-om-running)" : void 0 })), /* @__PURE__ */ React.createElement("div", { className: "space-y-2 text-sm" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-baseline" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint" }, "Planned:"), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink" }, fmtQty(plannedQty))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-baseline" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint" }, "Produced:"), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink" }, fmtQty(producedQty))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-baseline" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint" }, "Remaining:"), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[22px] font-medium tracking-[-0.02em] text-om-accent" }, fmtQty(remaining))))), /* @__PURE__ */ React.createElement("div", { className: cardCls }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-4" }, /* @__PURE__ */ React.createElement("h3", { className: sectionLabelCls }, "Issues"), canReportIssue && /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "danger",
      onClick: () => setReportIssueOpen(true),
      className: "px-4 py-2.5 text-[13px]"
    },
    "+ Report"
  )), !workOrder.issues || workOrder.issues.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint text-center py-4" }, "No issues reported.") : /* @__PURE__ */ React.createElement("div", { className: "space-y-3" }, workOrder.issues.slice(0, 5).map((issue) => /* @__PURE__ */ React.createElement(
    "div",
    {
      key: issue.id,
      className: `p-3 rounded-om-sm border ${issue.issue_type?.is_blocking ? "bg-om-blocked-bg/60 border-om-blocked/20" : "bg-om-panel border-om-line2"}`
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-1" }, /* @__PURE__ */ React.createElement("span", { className: "text-xs font-semibold text-om-ink" }, issue.issue_type?.name), /* @__PURE__ */ React.createElement(StatusPill, { status: issuePillStatus(issue.status), label: issue.status })),
    /* @__PURE__ */ React.createElement("p", { className: "text-sm font-medium text-om-ink" }, issue.title),
    issue.description && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-1" }, issue.description.length > 80 ? `${issue.description.slice(0, 80)}\u2026` : issue.description),
    /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[10px] text-om-faint mt-1" }, issue.reported_at ? formatDateTime(new Date(issue.reported_at)) : "", issue.reported_by ? ` by ${issue.reported_by.name}` : "")
  )), workOrder.issues.length > 5 && /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[10px] text-om-faint text-center" }, "+", workOrder.issues.length - 5, " more issues"))), /* @__PURE__ */ React.createElement("div", { className: cardCls }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-4" }, /* @__PURE__ */ React.createElement("h3", { className: sectionLabelCls }, "Scrap"), canReportScrap && /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setReportScrapOpen(true),
      className: "inline-flex items-center justify-center rounded-om-sm bg-om-downtime-bg px-4 py-2.5 text-[13px] font-semibold text-om-downtime hover:bg-[#f5e7c8] transition-colors cursor-pointer"
    },
    "+ Report"
  )), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-baseline text-sm mb-2" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint" }, "Total scrap:"), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink" }, fmtQty(totalScrap))), qualityPct !== null && /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-baseline text-sm mb-3" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] uppercase tracking-[0.08em] text-om-faint" }, "Quality:"), /* @__PURE__ */ React.createElement("span", { className: `font-mono text-[15px] font-medium ${qualityPct < 100 ? "text-om-downtime" : "text-om-running"}` }, qualityPct.toFixed(1), "%")), scrapEntries.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint text-center py-4" }, "No scrap reported.") : /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, scrapEntries.slice(0, 5).map((entry) => /* @__PURE__ */ React.createElement("div", { key: entry.id, className: "p-3 rounded-om-sm bg-om-panel border border-om-line2" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-1" }, /* @__PURE__ */ React.createElement("span", { className: "text-xs font-semibold text-om-ink" }, entry.scrap_reason?.name ?? "Unknown"), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] font-medium text-om-ink" }, fmtQty(entry.quantity))), entry.notes && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, entry.notes.length > 80 ? `${entry.notes.slice(0, 80)}\u2026` : entry.notes), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[10px] text-om-faint mt-1" }, entry.reported_at ? new Date(entry.reported_at).toLocaleString() : "", entry.reported_by ? ` by ${entry.reported_by.name}` : ""))), scrapEntries.length > 5 && /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[10px] text-om-faint text-center" }, "+", scrapEntries.length - 5, " more")))))), createBatchOpen && /* @__PURE__ */ React.createElement(
    CreateBatchModal,
    {
      workOrder,
      workstations,
      defaultWorkstationId,
      onClose: () => setCreateBatchOpen(false)
    }
  ), reportIssueOpen && /* @__PURE__ */ React.createElement(
    ReportIssueModal,
    {
      workOrder,
      issueTypes,
      customFields: issueCustomFields,
      onClose: () => setReportIssueOpen(false)
    }
  ), reportScrapOpen && /* @__PURE__ */ React.createElement(
    ReportScrapModal,
    {
      workOrder,
      scrapReasons,
      onClose: () => setReportScrapOpen(false)
    }
  ));
}
WorkOrderDetail.layout = (page) => /* @__PURE__ */ React.createElement(OperatorLayout, null, page);
export {
  WorkOrderDetail as default
};
