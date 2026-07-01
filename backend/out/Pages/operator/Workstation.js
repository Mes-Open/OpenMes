import { useState, useEffect, useRef, useCallback } from "react";
import { __ } from "../../lib/i18n";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Button, Checkbox, IconButton, StatusPill } from "@openmes/ui";
import OperatorLayout from "../../layouts/OperatorLayout";
import LineSync from "../../components/LineSync";
import LabelPrintMenu from "../../components/LabelPrintMenu";
import { formatDate, formatNumber } from "../../lib/i18n";
function fmt(n) {
  return formatNumber(Number(n ?? 0), { maximumFractionDigits: 0 });
}
function weekLabel(wk) {
  return "W" + String(wk).padStart(2, "0");
}
function statusLabel(status) {
  if (status === "PENDING") return "Not Started";
  if (status === "IN_PROGRESS") return "In Progress";
  if (status === "DONE") return "Done";
  if (status === "BLOCKED") return "Blocked";
  return status.replace(/_/g, " ").toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
}
function getCellValue(wo, col) {
  if (col.source === "extra_data") {
    const val = wo.extra_data?.[col.key];
    return val !== void 0 && val !== null ? String(val) : "\u2014";
  }
  if (col.source === "product_type") {
    return wo.product_type?.name ?? "\u2014";
  }
  if (col.key === "due_date") {
    if (!wo.due_date) return "\u2014";
    const d = new Date(wo.due_date);
    return formatDate(d, { day: "2-digit", month: "short" });
  }
  if (col.key === "week_number") {
    return wo.week_number ? weekLabel(wo.week_number) : "\u2014";
  }
  const v = wo[col.key];
  return v !== void 0 && v !== null ? String(v) : "\u2014";
}
const fieldLabelCls = "block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint";
const inputCls = "w-full text-[13px] text-om-ink placeholder:text-om-faint bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 outline-none transition-colors focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]";
const modalFooterCls = "flex gap-3 border-t border-om-line2 bg-om-panel px-[18px] py-[14px]";
function useVisibleColumns(allColumns, lineId) {
  const storageKey = `ws_cols_${lineId}`;
  const defaultVisible = allColumns.filter((c) => c.default).map((c) => c.key);
  const [visibleKeys, setVisibleKeys] = useState(() => {
    try {
      const saved = localStorage.getItem(storageKey);
      return saved ? JSON.parse(saved) : defaultVisible;
    } catch {
      return defaultVisible;
    }
  });
  const toggleColumn = useCallback(
    (key) => {
      setVisibleKeys((prev) => {
        const next = prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key];
        try {
          localStorage.setItem(storageKey, JSON.stringify(next));
        } catch {
        }
        return next;
      });
    },
    [storageKey]
  );
  const resetColumns = useCallback(() => {
    setVisibleKeys(defaultVisible);
    try {
      localStorage.removeItem(storageKey);
    } catch {
    }
  }, [storageKey]);
  return { visibleKeys, toggleColumn, resetColumns };
}
function TimedCorrectLink({ entry, qtyEditPolicy, qtyEditWindowMinutes }) {
  const [visible, setVisible] = useState(true);
  useEffect(() => {
    if (qtyEditPolicy !== "timed") return;
    const deadline = new Date(entry.updated_at).getTime() + qtyEditWindowMinutes * 60 * 1e3;
    const tick = setInterval(() => {
      if (Date.now() > deadline) {
        setVisible(false);
        clearInterval(tick);
      }
    }, 5e3);
    return () => clearInterval(tick);
  }, [entry.updated_at, qtyEditPolicy, qtyEditWindowMinutes]);
  if (!visible) return null;
  return /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/operator/shift-entry/${entry.id}/correct`,
      className: "w-6 h-6 flex items-center justify-center rounded-[6px] text-om-faint hover:text-om-accent hover:bg-om-selected transition-colors",
      title: "Correct quantity",
      onClick: (e) => e.stopPropagation()
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-3.5 h-3.5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
      "path",
      {
        strokeLinecap: "round",
        strokeLinejoin: "round",
        strokeWidth: "2",
        d: "M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
      }
    ))
  );
}
function ShiftCell({ wo, shift, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes }) {
  const isDone = wo.status === "DONE";
  const entryKey = `${wo.id}_${shift.id}`;
  const entriesForCell = shiftEntries[entryKey] ?? [];
  const firstEntry = entriesForCell[0] ?? null;
  const entryQty = firstEntry ? parseFloat(firstEntry.quantity) : 0;
  const defaultVal = entryQty > 0 ? String(Math.round(entryQty)) : "";
  const [inputVal, setInputVal] = useState(defaultVal);
  const prevEntryQty = useRef(entryQty);
  useEffect(() => {
    if (prevEntryQty.current !== entryQty) {
      prevEntryQty.current = entryQty;
      setInputVal(entryQty > 0 ? String(Math.round(entryQty)) : "");
    }
  }, [entryQty]);
  const submit = useCallback(() => {
    const qty = parseInt(inputVal, 10);
    if (!qty || qty <= 0) return;
    router.post(`/operator/workstation/${wo.id}/shift-entry`, { shift_id: shift.id, quantity: qty });
  }, [inputVal, wo.id, shift.id]);
  if (isDone) {
    return /* @__PURE__ */ React.createElement("td", { className: "px-2 py-1 text-center", onClick: (e) => e.stopPropagation() }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-faint" }, entryQty > 0 ? Math.round(entryQty) : 0));
  }
  const canCorrect = firstEntry && entryQty > 0 && qtyEditPolicy !== "none" && (qtyEditPolicy === "full" || qtyEditPolicy === "timed" && new Date(firstEntry.updated_at).getTime() + qtyEditWindowMinutes * 60 * 1e3 > Date.now());
  return /* @__PURE__ */ React.createElement("td", { className: "px-2 py-1 text-center", onClick: (e) => e.stopPropagation() }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-center gap-1" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      value: inputVal,
      onChange: (e) => setInputVal(e.target.value),
      onFocus: (e) => e.target.select(),
      onKeyDown: (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          submit();
        }
      },
      onBlur: () => {
        const qty = parseInt(inputVal, 10);
        if (qty > 0 && inputVal !== defaultVal) submit();
      },
      className: `w-16 text-center font-mono text-[15px] font-medium border rounded-om-sm px-1 py-2 outline-none transition-colors focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)] ${entryQty > 0 ? "text-om-accent bg-om-selected border-om-line" : "bg-om-bg text-om-ink border-om-line"}`,
      placeholder: "\u2014",
      min: "1",
      step: "1",
      inputMode: "numeric"
    }
  ), canCorrect && /* @__PURE__ */ React.createElement(
    TimedCorrectLink,
    {
      entry: firstEntry,
      qtyEditPolicy,
      qtyEditWindowMinutes
    }
  )));
}
function ModalHeader({ title, subtitle, onClose }) {
  return /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between border-b border-om-line2 px-[18px] py-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "text-[15px] font-semibold text-om-ink" }, title), subtitle != null && /* @__PURE__ */ React.createElement("div", { className: "mt-[3px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, subtitle)), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: onClose,
      className: "cursor-pointer text-[18px] leading-none text-om-faint hover:text-om-muted"
    },
    "\xD7"
  ));
}
function StartModal({ modal, onClose }) {
  if (!modal.open) return null;
  return /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-50 flex items-center justify-center p-4" }, /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 bg-[rgba(10,9,8,0.4)]", onClick: onClose }), /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "relative w-full max-w-sm overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]",
      onClick: (e) => e.stopPropagation()
    },
    /* @__PURE__ */ React.createElement(ModalHeader, { title: "Start Production", subtitle: modal.orderNo, onClose }),
    /* @__PURE__ */ React.createElement("div", { className: "px-[18px] py-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-ink mb-2 text-[17px] font-semibold tracking-[-0.01em]" }, modal.product), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-1" }, "Order: ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-ink" }, modal.orderNo)), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, "Planned: ", /* @__PURE__ */ React.createElement("strong", { className: "font-mono text-[15px] text-om-ink" }, fmt(modal.qty)), " units")),
    /* @__PURE__ */ React.createElement("div", { className: modalFooterCls }, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose, className: "flex-1 px-6 py-4 text-[15px] font-semibold" }, "Cancel"), /* @__PURE__ */ React.createElement(
      Button,
      {
        variant: "accent",
        onClick: () => {
          onClose();
          router.post(`/operator/workstation/${modal.id}/start`);
        },
        className: "flex-1 px-6 py-4 text-[15px] font-semibold"
      },
      "Start"
    ))
  ));
}
function CompleteModal({ modal, onClose }) {
  const [qty, setQty] = useState("");
  useEffect(() => {
    if (modal.open) setQty("");
  }, [modal.open, modal.id]);
  if (!modal.open) return null;
  const handleSubmit = (e) => {
    e.preventDefault();
    const n = parseInt(qty, 10);
    if (isNaN(n) || n < 0) return;
    onClose();
    router.post(`/operator/workstation/${modal.id}/complete`, { produced_qty: n });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-50 flex items-center justify-center p-4" }, /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 bg-[rgba(10,9,8,0.4)]", onClick: onClose }), /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "relative w-full max-w-sm overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]",
      onClick: (e) => e.stopPropagation()
    },
    /* @__PURE__ */ React.createElement(ModalHeader, { title: "Add Produced Quantity", subtitle: modal.orderNo, onClose }),
    /* @__PURE__ */ React.createElement("form", { onSubmit: handleSubmit }, /* @__PURE__ */ React.createElement("div", { className: "px-[18px] py-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-ink mb-1 text-[17px] font-semibold tracking-[-0.01em]" }, modal.product), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-1" }, "Order: ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-ink" }, modal.orderNo)), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-4" }, "Planned: ", /* @__PURE__ */ React.createElement("strong", { className: "font-mono text-om-ink" }, fmt(modal.planned)), " | Already produced: ", /* @__PURE__ */ React.createElement("strong", { className: "font-mono text-om-ink" }, fmt(modal.produced))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Quantity ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "number",
        value: qty,
        onChange: (e) => setQty(e.target.value),
        className: "w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-4 font-mono text-[30px] font-medium tracking-[-0.02em] text-center text-om-ink placeholder:text-om-faintest outline-none transition-colors focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]",
        placeholder: "0",
        min: "0",
        step: "1",
        required: true,
        autoFocus: true,
        inputMode: "numeric"
      }
    ))), /* @__PURE__ */ React.createElement("div", { className: modalFooterCls }, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose, className: "flex-1 px-6 py-4 text-[15px] font-semibold" }, "Cancel"), /* @__PURE__ */ React.createElement(
      Button,
      {
        type: "submit",
        variant: "accent",
        disabled: qty === "" || parseInt(qty, 10) < 0,
        className: "flex-1 px-6 py-4 text-[15px] font-semibold"
      },
      "Confirm"
    )))
  ));
}
function InfoModal({ info, onClose }) {
  if (!info) return null;
  return /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-50 flex items-center justify-center p-4" }, /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 bg-[rgba(10,9,8,0.4)]", onClick: onClose }), /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "relative w-full max-w-md overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]",
      onClick: (e) => e.stopPropagation()
    },
    /* @__PURE__ */ React.createElement(ModalHeader, { title: "Order Details", subtitle: info.orderNo, onClose }),
    /* @__PURE__ */ React.createElement("div", { className: "px-[18px] py-4 space-y-3" }, /* @__PURE__ */ React.createElement(InfoRow, { label: __("Order #") }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] font-medium text-om-ink" }, info.orderNo)), /* @__PURE__ */ React.createElement(InfoRow, { label: __("Product") }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-medium text-om-ink" }, info.product)), /* @__PURE__ */ React.createElement(InfoRow, { label: __("Line") }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-medium text-om-ink" }, info.line)), /* @__PURE__ */ React.createElement(InfoRow, { label: __("Status") }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-semibold text-om-ink" }, info.status)), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-3 py-2" }, /* @__PURE__ */ React.createElement("div", { className: "text-center" }, /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint mb-1" }, "Planned"), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink" }, info.planned)), /* @__PURE__ */ React.createElement("div", { className: "text-center" }, /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint mb-1" }, "Produced"), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[22px] font-medium tracking-[-0.02em] text-om-running" }, info.produced)), /* @__PURE__ */ React.createElement("div", { className: "text-center" }, /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint mb-1" }, "Remaining"), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[22px] font-medium tracking-[-0.02em] text-om-accent" }, info.remaining))), /* @__PURE__ */ React.createElement(InfoRow, { label: __("Priority") }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] font-medium text-om-ink" }, info.priority)), /* @__PURE__ */ React.createElement(InfoRow, { label: __("Due Date") }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] font-medium text-om-ink" }, info.dueDate)), info.description && info.description !== "-" && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1" }, "Description"), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-ink bg-om-panel border border-om-line2 rounded-om-sm p-2" }, info.description))),
    /* @__PURE__ */ React.createElement("div", { className: modalFooterCls }, /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: onClose, className: "w-full px-6 py-4 text-[15px] font-semibold" }, "Close"))
  ));
}
function InfoRow({ label, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-baseline border-b border-om-line2 pb-2" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, label), children);
}
function ReportModal({ report, issueTypes, onClose }) {
  const [typeId, setTypeId] = useState("");
  const [title, setTitle] = useState("");
  const [desc, setDesc] = useState("");
  useEffect(() => {
    if (report) {
      setTypeId("");
      setTitle("");
      setDesc("");
    }
  }, [report?.woId]);
  if (!report) return null;
  const handleSubmit = (e) => {
    e.preventDefault();
    if (!typeId || !title) return;
    onClose();
    router.post("/operator/issue", {
      work_order_id: report.woId,
      issue_type_id: typeId,
      title,
      description: desc
    });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-50 flex items-center justify-center p-4" }, /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 bg-[rgba(10,9,8,0.4)]", onClick: onClose }), /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "relative w-full max-w-lg overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]",
      onClick: (e) => e.stopPropagation()
    },
    /* @__PURE__ */ React.createElement(ModalHeader, { title: "Report Issue", subtitle: report.woNo, onClose }),
    /* @__PURE__ */ React.createElement("form", { onSubmit: handleSubmit }, /* @__PURE__ */ React.createElement("div", { className: "px-[18px] py-4 space-y-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Type ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-2" }, issueTypes.map((t) => /* @__PURE__ */ React.createElement(
      "label",
      {
        key: t.id,
        className: `flex items-center gap-2 p-3 rounded-om-sm border cursor-pointer transition-colors ${String(typeId) === String(t.id) ? "border-om-accent bg-om-selected shadow-[0_0_0_3px_rgba(234,90,43,0.12)]" : "border-om-line bg-om-bg hover:border-om-faintest"}`
      },
      /* @__PURE__ */ React.createElement(
        "input",
        {
          type: "radio",
          name: "issue_type_id",
          value: t.id,
          checked: String(typeId) === String(t.id),
          onChange: () => {
            setTypeId(String(t.id));
            if (!title) setTitle(t.name);
          },
          className: "sr-only",
          required: true
        }
      ),
      /* @__PURE__ */ React.createElement("span", { className: "text-sm font-medium text-om-ink" }, t.name)
    )))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Title ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "text",
        value: title,
        onChange: (e) => setTitle(e.target.value),
        className: inputCls,
        required: true,
        maxLength: 255
      }
    )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: fieldLabelCls }, "Details ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faint normal-case tracking-normal" }, "(optional)")), /* @__PURE__ */ React.createElement(
      "textarea",
      {
        value: desc,
        onChange: (e) => setDesc(e.target.value),
        rows: 3,
        className: `${inputCls} resize-none`,
        maxLength: 2e3
      }
    ))), /* @__PURE__ */ React.createElement("div", { className: modalFooterCls }, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose, className: "flex-1 px-6 py-4 text-[15px] font-semibold" }, "Cancel"), /* @__PURE__ */ React.createElement(
      Button,
      {
        type: "submit",
        variant: "danger",
        disabled: !typeId || !title,
        className: "flex-1 px-6 py-4 text-[15px] font-semibold"
      },
      "Submit Report"
    )))
  ));
}
function ColumnPicker({ allColumns, visibleKeys, toggleColumn, resetColumns }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    const handler = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);
  const systemCols = allColumns.filter((c) => c.source !== "extra_data");
  const extraCols = allColumns.filter((c) => c.source === "extra_data");
  return /* @__PURE__ */ React.createElement("div", { className: "relative", ref }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setOpen((v) => !v),
      className: "p-2.5 rounded-om-sm border border-om-line bg-om-card hover:bg-om-chip transition-colors cursor-pointer",
      title: "Configure columns"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 text-om-muted", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
      "path",
      {
        strokeLinecap: "round",
        strokeLinejoin: "round",
        strokeWidth: "2",
        d: "M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
      }
    ), /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 12a3 3 0 11-6 0 3 3 0 016 0z" }))
  ), open && /* @__PURE__ */ React.createElement("div", { className: "absolute right-0 mt-2 w-64 bg-om-card rounded-om shadow-[0_18px_44px_-18px_rgba(0,0,0,.3)] border border-om-line z-50 p-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-semibold text-om-ink" }, "Columns"), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: resetColumns,
      className: "font-mono text-[10px] uppercase tracking-[0.08em] text-om-accent hover:underline cursor-pointer"
    },
    "Reset"
  )), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] uppercase tracking-[0.12em] text-om-faint mb-1 mt-2" }, "System fields"), systemCols.map((col) => /* @__PURE__ */ React.createElement("div", { key: col.key, className: "py-1 px-1 rounded-[6px] hover:bg-om-chip" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: visibleKeys.includes(col.key),
      onChange: () => toggleColumn(col.key),
      label: col.label
    }
  ))), extraCols.length > 0 && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] uppercase tracking-[0.12em] text-om-faint mb-1 mt-3" }, "Import data"), extraCols.map((col) => /* @__PURE__ */ React.createElement("div", { key: col.key, className: "flex items-center gap-2 py-1 px-1 rounded-[6px] hover:bg-om-chip" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: visibleKeys.includes(col.key),
      onChange: () => toggleColumn(col.key),
      label: col.label
    }
  ), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] text-om-faint ml-auto" }, col.key))))));
}
function StatusBadge({ status }) {
  if (status === "DONE") {
    return /* @__PURE__ */ React.createElement(StatusPill, { status: "done", label: __("Done") });
  }
  if (status === "IN_PROGRESS") {
    return /* @__PURE__ */ React.createElement(StatusPill, { status: "running", label: __("In Progress") });
  }
  if (status === "BLOCKED") {
    return /* @__PURE__ */ React.createElement(StatusPill, { status: "blocked", label: __("Blocked") });
  }
  return /* @__PURE__ */ React.createElement(StatusPill, { status: "pending", label: statusLabel(status) });
}
function WorkOrderRow({ wo, allColumns, visibleKeys, lineShifts, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes, onStart, onComplete, onInfo, onReport, labelTemplates = [] }) {
  const isDone = wo.status === "DONE";
  const isActive = wo.status === "IN_PROGRESS";
  const planned = parseFloat(wo.planned_qty ?? 0);
  const produced = parseFloat(wo.produced_qty ?? 0);
  const remaining = Math.max(0, planned - produced);
  const handleRowClick = () => {
    if (isDone) return;
    if (!isActive) {
      onStart({
        open: true,
        id: wo.id,
        orderNo: wo.order_no,
        product: wo.product_type?.name ?? wo.order_no,
        qty: planned
      });
    } else {
      onComplete({
        open: true,
        id: wo.id,
        orderNo: wo.order_no,
        product: wo.product_type?.name ?? wo.order_no,
        planned,
        produced
      });
    }
  };
  const rowClass = [
    "border-b border-om-line2 transition-colors border-l-[3px]",
    isDone ? "bg-om-done-bg/60 border-l-om-done" : isActive ? "bg-om-running-bg/50 border-l-om-running" : wo.status === "BLOCKED" ? "bg-om-blocked-bg/50 border-l-om-blocked" : "hover:bg-om-panel border-l-transparent",
    !isDone ? "cursor-pointer active:bg-om-chip" : ""
  ].join(" ").trim();
  return /* @__PURE__ */ React.createElement("tr", { className: rowClass, onClick: handleRowClick }, allColumns.map(
    (col) => visibleKeys.includes(col.key) ? /* @__PURE__ */ React.createElement("td", { key: col.key, className: "px-3 py-3 text-sm text-om-ink" }, col.key === "status" ? /* @__PURE__ */ React.createElement(StatusBadge, { status: wo.status }) : getCellValue(wo, col)) : null
  ), /* @__PURE__ */ React.createElement("td", { className: "px-3 py-3 text-center font-mono text-[15px] font-medium text-om-ink border-l border-om-line" }, fmt(planned)), /* @__PURE__ */ React.createElement("td", { className: "px-3 py-3 text-center font-mono text-[15px] text-om-muted" }, fmt(produced)), /* @__PURE__ */ React.createElement("td", { className: `px-3 py-3 text-center font-mono text-[15px] font-semibold ${remaining <= 0 ? "bg-om-running-bg text-om-running" : "bg-om-accent text-white"}` }, fmt(remaining)), lineShifts.map((shift) => /* @__PURE__ */ React.createElement(
    ShiftCell,
    {
      key: shift.id,
      wo,
      shift,
      shiftEntries,
      qtyEditPolicy,
      qtyEditWindowMinutes
    }
  )), /* @__PURE__ */ React.createElement("td", { className: "px-2 py-3 text-center", onClick: (e) => e.stopPropagation() }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-center gap-1" }, !isDone && /* @__PURE__ */ React.createElement(
    IconButton,
    {
      variant: "primary",
      onClick: () => onComplete({
        open: true,
        id: wo.id,
        orderNo: wo.order_no,
        product: wo.product_type?.name ?? wo.order_no,
        planned,
        produced
      }),
      className: "bg-om-accent hover:bg-om-accent hover:brightness-95",
      title: "Add produced quantity"
    },
    "+"
  ), /* @__PURE__ */ React.createElement(
    IconButton,
    {
      variant: "danger",
      onClick: () => onReport({ woId: wo.id, woNo: wo.order_no }),
      title: "Report problem"
    },
    "!"
  ), /* @__PURE__ */ React.createElement(
    IconButton,
    {
      variant: "default",
      onClick: () => onInfo({
        orderNo: wo.order_no,
        product: wo.product_type?.name ?? "-",
        line: wo.line?.name ?? "-",
        status: statusLabel(wo.status),
        planned: fmt(planned),
        produced: fmt(produced),
        remaining: fmt(remaining),
        priority: wo.priority ?? "-",
        dueDate: wo.due_date ? wo.due_date.substring(0, 10) : "-",
        description: wo.description ?? "-"
      }),
      title: "Details"
    },
    "?"
  ), labelTemplates.some((t) => t.type === "work_order") && /* @__PURE__ */ React.createElement(LabelPrintMenu, { kind: "work-order", id: wo.id, templates: labelTemplates, label: __("Label") }))));
}
function Workstation() {
  const {
    workOrders = [],
    line,
    availableWeeks = [],
    weekFilter,
    search: searchProp,
    issueTypes = [],
    allColumns = [],
    shifts = [],
    shiftEntries = {},
    qtyEditPolicy = "none",
    qtyEditWindowMinutes = 60,
    labelTemplates = [],
    machineStates = [],
    machineStateOptions = []
  } = usePage().props;
  const { visibleKeys, toggleColumn, resetColumns } = useVisibleColumns(allColumns, line?.id ?? 0);
  const [searchVal, setSearchVal] = useState(searchProp ?? "");
  const [startModal, setStartModal] = useState({ open: false });
  const [completeModal, setCompleteModal] = useState({ open: false });
  const [infoModal, setInfoModal] = useState(null);
  const [reportModal, setReportModal] = useState(null);
  const lineShifts = shifts.filter((s) => s.line_id === line?.id || String(s.line_id) === String(line?.id));
  const hasShifts = lineShifts.length > 0;
  const handleSearch = (e) => {
    e.preventDefault();
    const params = {};
    if (searchVal) params.search = searchVal;
    if (weekFilter && weekFilter !== "all") params.week = weekFilter;
    router.get("/operator/workstation", params);
  };
  const weekUrl = (wk) => {
    const params = new URLSearchParams();
    if (wk && wk !== "all") params.set("week", wk);
    if (searchProp) params.set("search", searchProp);
    const qs = params.toString();
    return "/operator/workstation" + (qs ? "?" + qs : "");
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `Workstation \u2014 ${line?.name ?? ""}` }), /* @__PURE__ */ React.createElement(LineSync, { lineId: line?.id, reloadOnly: ["workOrders", "shiftEntries"] }), /* @__PURE__ */ React.createElement("div", { className: "max-w-full mx-auto px-2 sm:px-4" }, machineStates.length > 0 && /* @__PURE__ */ React.createElement(MachineStatePanel, { machines: machineStates, options: machineStateOptions }), /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[24px] font-semibold tracking-[-0.02em] text-om-ink" }, line?.name)), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center bg-om-card border border-om-line rounded-om-sm p-1 gap-1" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/operator/queue",
      className: "px-3 py-1.5 rounded-[6px] text-sm font-medium text-om-muted hover:text-om-ink transition-colors"
    },
    "Queue"
  ), /* @__PURE__ */ React.createElement("span", { className: "px-3 py-1.5 rounded-[6px] text-sm font-semibold bg-om-ink text-om-on-ink" }, "Workstation")), /* @__PURE__ */ React.createElement(
    ColumnPicker,
    {
      allColumns,
      visibleKeys,
      toggleColumn,
      resetColumns
    }
  ), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/operator/select-line",
      className: "px-4 py-2.5 rounded-om-sm text-sm font-medium text-om-ink border border-om-line bg-om-card hover:bg-om-chip transition-colors"
    },
    "Change Line"
  ))), availableWeeks.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap items-center gap-2 mb-3" }, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 text-om-faint", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
    "path",
    {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      strokeWidth: "2",
      d: "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
    }
  )), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint" }, "Select week:"), /* @__PURE__ */ React.createElement(
    "a",
    {
      href: weekUrl("all"),
      className: `px-4 py-2.5 rounded-om-sm font-mono text-[12px] border transition-colors ${!weekFilter || weekFilter === "all" ? "bg-om-chip border-om-line text-om-ink" : "border-transparent text-om-muted hover:bg-om-chip"}`
    },
    "All weeks"
  ), availableWeeks.map((wk) => /* @__PURE__ */ React.createElement(
    "a",
    {
      key: wk,
      href: weekUrl(wk),
      className: `px-4 py-2.5 rounded-om-sm font-mono text-[12px] border transition-colors ${String(weekFilter) === String(wk) ? "bg-om-selected border-om-accent text-om-accent" : "border-transparent text-om-muted hover:bg-om-chip"}`
    },
    weekLabel(wk)
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 mb-3" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      disabled: true,
      className: "px-6 py-3.5 rounded-om-sm text-[14px] font-semibold bg-om-downtime-bg text-om-downtime opacity-60 cursor-not-allowed"
    },
    "Cleaning"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      disabled: true,
      className: "px-6 py-3.5 rounded-om-sm text-[14px] font-semibold bg-om-blocked-bg text-om-blocked opacity-60 cursor-not-allowed"
    },
    "Failure"
  )), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mb-2" }, 'Click a row to change production status. Use "Z1" or "Z2" columns to enter produced quantities per shift.')), /* @__PURE__ */ React.createElement("form", { onSubmit: handleSearch, className: "mb-4" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: searchVal,
      onChange: (e) => setSearchVal(e.target.value),
      placeholder: "Search by order number, product or data...",
      className: `${inputCls} sm:w-96`,
      autoComplete: "off"
    }
  )), workOrders.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om text-center py-16" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-faint text-lg" }, "No work orders found")) : /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "overflow-x-auto" }, /* @__PURE__ */ React.createElement("table", { className: "min-w-full text-sm border-collapse" }, /* @__PURE__ */ React.createElement("thead", null, /* @__PURE__ */ React.createElement("tr", { className: "bg-om-panel border-b border-om-line" }, allColumns.map(
    (col) => visibleKeys.includes(col.key) ? /* @__PURE__ */ React.createElement(
      "th",
      {
        key: col.key,
        className: "px-3 py-3 text-left font-mono text-[9px] font-normal uppercase tracking-[0.1em] text-om-faint whitespace-nowrap"
      },
      col.label
    ) : null
  ), /* @__PURE__ */ React.createElement("th", { className: "px-3 py-3 text-center font-mono text-[9px] font-normal uppercase tracking-[0.1em] text-om-faint border-l border-om-line" }, "To Produce"), /* @__PURE__ */ React.createElement("th", { className: "px-3 py-3 text-center font-mono text-[9px] font-normal uppercase tracking-[0.1em] text-om-faint" }, "Produced"), /* @__PURE__ */ React.createElement("th", { className: "px-3 py-3 text-center font-mono text-[9px] font-normal uppercase tracking-[0.1em] bg-om-accent text-white" }, "Remaining"), hasShifts && lineShifts.map((shift) => /* @__PURE__ */ React.createElement(
    "th",
    {
      key: shift.id,
      className: "px-3 py-3 text-center font-mono text-[9px] font-normal uppercase tracking-[0.1em] text-om-faint",
      title: `${shift.name} (${(shift.start_time ?? "").substring(0, 5)}\u2013${(shift.end_time ?? "").substring(0, 5)})`
    },
    shift.code
  )), /* @__PURE__ */ React.createElement("th", { className: "px-3 py-3 w-10" }))), /* @__PURE__ */ React.createElement("tbody", null, workOrders.map((wo) => /* @__PURE__ */ React.createElement(
    WorkOrderRow,
    {
      key: wo.id,
      wo,
      allColumns,
      visibleKeys,
      lineShifts: hasShifts ? lineShifts : [],
      shiftEntries,
      qtyEditPolicy,
      qtyEditWindowMinutes,
      onStart: setStartModal,
      onComplete: setCompleteModal,
      onInfo: setInfoModal,
      onReport: setReportModal,
      labelTemplates
    }
  ))))))), /* @__PURE__ */ React.createElement(StartModal, { modal: startModal, onClose: () => setStartModal({ open: false }) }), /* @__PURE__ */ React.createElement(CompleteModal, { modal: completeModal, onClose: () => setCompleteModal({ open: false }) }), /* @__PURE__ */ React.createElement(InfoModal, { info: infoModal, onClose: () => setInfoModal(null) }), issueTypes.length > 0 && /* @__PURE__ */ React.createElement(
    ReportModal,
    {
      report: reportModal,
      issueTypes,
      onClose: () => setReportModal(null)
    }
  ));
}
const MACHINE_STATE_LABELS = {
  RUNNING: "Running",
  IDLE: "Idle",
  STOPPED: "Stopped",
  FAULT: "Fault",
  SETUP: "Setup",
  WAITING: "Waiting",
  CLEANING: "Cleaning",
  MAINTENANCE: "Maintenance"
};
const MACHINE_STATE_DOT = {
  RUNNING: "bg-om-running",
  IDLE: "bg-amber-400",
  SETUP: "bg-om-accent",
  STOPPED: "bg-om-faintest",
  FAULT: "bg-om-blocked",
  WAITING: "bg-yellow-400",
  CLEANING: "bg-purple-400",
  MAINTENANCE: "bg-orange-400"
};
function MachineStatePanel({ machines, options }) {
  const setState = (workstationId, state) => {
    router.post(`/operator/workstation/machine-state/${workstationId}`, { state }, { preserveScroll: true });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "mb-4 bg-om-card border border-om-line rounded-om-sm p-3" }, /* @__PURE__ */ React.createElement("p", { className: "text-[10px] uppercase tracking-[0.08em] text-om-faint mb-2" }, "Machine state"), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-3" }, machines.map((m) => /* @__PURE__ */ React.createElement("div", { key: m.id, className: "flex items-center gap-2 border border-om-line2 rounded-om-sm px-2.5 py-1.5" }, /* @__PURE__ */ React.createElement("span", { className: `w-2 h-2 rounded-full ${MACHINE_STATE_DOT[m.state] ?? "bg-slate-300"}` }), /* @__PURE__ */ React.createElement("span", { className: "text-sm font-medium text-om-ink" }, m.name), /* @__PURE__ */ React.createElement(
    "select",
    {
      value: m.state ?? "",
      onChange: (e) => setState(m.id, e.target.value),
      className: "form-input text-xs py-1",
      "aria-label": `Set state for ${m.name}`
    },
    !m.state && /* @__PURE__ */ React.createElement("option", { value: "", disabled: true }, "\u2014"),
    m.state && !options.includes(m.state) && /* @__PURE__ */ React.createElement("option", { value: m.state }, MACHINE_STATE_LABELS[m.state] ?? m.state),
    options.map((s) => /* @__PURE__ */ React.createElement("option", { key: s, value: s }, MACHINE_STATE_LABELS[s] ?? s))
  )))));
}
Workstation.layout = (page) => /* @__PURE__ */ React.createElement(OperatorLayout, null, page);
export {
  Workstation as default
};
