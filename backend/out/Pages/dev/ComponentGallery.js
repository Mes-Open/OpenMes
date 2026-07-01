import { useState } from "react";
import {
  ActionMenu,
  Badge,
  Button,
  Checkbox,
  Calendar,
  ConfirmDialog,
  DatePicker,
  Dropdown,
  IconButton,
  InlineAlert,
  Modal,
  OnlineDot,
  ProgressBar,
  QuantityStepper,
  RadioGroup,
  SegmentedControl,
  Skeleton,
  StatusPill,
  Switch,
  Tabs,
  TextField,
  ToastProvider,
  useToast
} from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
const SAMPLE_ROWS = [
  { id: "WO-2026-001", product: "HEPA-13 Standard", status: "running", prio: "H", plan: 250, made: 108 },
  { id: "WO-2026-002", product: "Carbon Pre-Filter", status: "running", prio: "M", plan: 400, made: 320 },
  { id: "WO-2026-003", product: "HEPA-13 Standard", status: "done", prio: "M", plan: 150, made: 150 },
  { id: "WO-2026-004", product: "HEPA Slim", status: "done", prio: "L", plan: 200, made: 200 },
  { id: "WO-2026-005", product: "HEPA Slim", status: "pending", prio: "H", plan: 120, made: 0 },
  { id: "WO-2026-006", product: "Carbon Pre-Filter", status: "blocked", prio: "H", plan: 300, made: 60 },
  { id: "WO-2026-007", product: "Polo Logo", status: "pending", prio: "L", plan: 140, made: 0 },
  { id: "WO-2026-008", product: "HEPA-13 Pro", status: "pending", prio: "M", plan: 180, made: 0 },
  { id: "WO-2026-009", product: "Carbon Deep-Bed", status: "blocked", prio: "H", plan: 220, made: 40 },
  { id: "WO-2026-010", product: "HEPA Slim", status: "running", prio: "M", plan: 160, made: 96 }
];
const TABLE_COLUMNS = [
  {
    accessorKey: "id",
    header: "Order",
    size: 130,
    meta: { filter: "text", filterPlaceholder: "Filter\u2026" },
    cell: (info) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[12px] font-medium text-om-ink" }, info.getValue())
  },
  {
    accessorKey: "product",
    header: "Product",
    meta: { filter: "text", filterPlaceholder: "Filter product\u2026", flex: true },
    cell: (info) => /* @__PURE__ */ React.createElement("span", { className: "text-[13.5px] font-medium text-om-ink" }, info.getValue())
  },
  {
    accessorKey: "status",
    header: "Status",
    size: 116,
    meta: { filter: "select", allLabel: "All status", options: ["running", "pending", "blocked", "done"] },
    cell: (info) => /* @__PURE__ */ React.createElement(StatusPill, { status: info.getValue(), label: info.getValue().toUpperCase() })
  },
  {
    accessorKey: "prio",
    header: "Pri",
    size: 60,
    meta: { filter: "select", allLabel: "All", options: ["H", "M", "L"] },
    cell: (info) => /* @__PURE__ */ React.createElement("span", { className: `font-mono text-[12px] ${info.getValue() === "H" ? "text-om-blocked" : info.getValue() === "M" ? "text-om-muted" : "text-om-faint"}` }, info.getValue())
  },
  { accessorKey: "plan", header: "Plan", size: 70, meta: { align: "right" }, cell: (info) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-ink" }, info.getValue()) },
  { accessorKey: "made", header: "Made", size: 70, meta: { align: "right" }, cell: (info) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-muted" }, info.getValue()) },
  {
    id: "remain",
    header: "Remain",
    size: 80,
    meta: { align: "right" },
    accessorFn: (row) => row.plan - row.made,
    cell: (info) => /* @__PURE__ */ React.createElement("span", { className: `font-mono text-[13px] font-semibold ${info.getValue() === 0 ? "text-om-running" : "text-om-accent"}` }, info.getValue())
  }
];
function Section({ label, children, cols = false }) {
  return /* @__PURE__ */ React.createElement("div", { className: "mt-[18px] rounded-om border border-om-line bg-om-card px-[22px] py-5" }, /* @__PURE__ */ React.createElement("div", { className: "mb-4 font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint" }, label), /* @__PURE__ */ React.createElement("div", { className: cols ? "flex flex-wrap items-start gap-9" : "" }, children));
}
function GalleryBody() {
  const toast = useToast();
  const [switches, setSwitches] = useState({ a: true, b: false });
  const [checked, setChecked] = useState(true);
  const [lot, setLot] = useState("26-0512-A");
  const [notes, setNotes] = useState("");
  const [qty, setQty] = useState(250);
  const [size, setSize] = useState("small");
  const [mode, setMode] = useState("batch");
  const [tab, setTab] = useState("details");
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [status, setStatus] = useState("all");
  const [lines, setLines] = useState(["WSZ-01", "WSZ-02"]);
  const [due, setDue] = useState("2026-05-26");
  const [day, setDay] = useState("2026-05-26");
  return /* @__PURE__ */ React.createElement("div", { className: "min-h-screen bg-[#E9E8E3] p-10 font-sans" }, /* @__PURE__ */ React.createElement("div", { className: "mx-auto max-w-[1280px]" }, /* @__PURE__ */ React.createElement("div", { className: "mb-2 flex items-end justify-between gap-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "mb-4 flex items-center gap-2.5" }, /* @__PURE__ */ React.createElement("img", { src: "/logo_open_mes.png", alt: "OpenMES", className: "h-6 w-auto" }), /* @__PURE__ */ React.createElement("span", { className: "rounded-[5px] border border-om-line px-[7px] py-0.5 font-mono text-[9.5px] text-om-faint" }, "design system")), /* @__PURE__ */ React.createElement("h1", { className: "text-[32px] font-semibold tracking-[-0.025em] text-om-ink" }, "Component gallery"), /* @__PURE__ */ React.createElement("p", { className: "mt-2 max-w-[560px] text-sm leading-normal text-om-muted" }, "Live @openmes/ui components \u2014 dev reference, mirrors the design handoff sheet.")), /* @__PURE__ */ React.createElement("div", { className: "text-right font-mono text-[10px] leading-[1.8] text-om-faint" }, /* @__PURE__ */ React.createElement("div", null, "GEIST \xB7 GEIST MONO"), /* @__PURE__ */ React.createElement("div", null, "ACCENT #EA5A2B"), /* @__PURE__ */ React.createElement("div", null, "RADIUS 12 / 8"))), /* @__PURE__ */ React.createElement(Section, { label: "02 \u2014 Buttons" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap items-center gap-2" }, /* @__PURE__ */ React.createElement(Button, { variant: "primary" }, "Primary"), /* @__PURE__ */ React.createElement(Button, { variant: "accent" }, "Accent"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary" }, "Secondary"), /* @__PURE__ */ React.createElement(Button, { variant: "ghost" }, "Ghost"), /* @__PURE__ */ React.createElement(Button, { variant: "danger" }, "Danger"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", disabled: true }, "Disabled"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", loading: true }, "Saving\u2026"), /* @__PURE__ */ React.createElement(IconButton, { variant: "primary" }, "+"), /* @__PURE__ */ React.createElement(IconButton, { variant: "danger" }, "!"), /* @__PURE__ */ React.createElement(IconButton, null, "\u22EF"))), /* @__PURE__ */ React.createElement(Section, { label: "03 \u2014 Switches & checkbox", cols: true }, /* @__PURE__ */ React.createElement(Switch, { checked: switches.a, onChange: (v) => setSwitches((s) => ({ ...s, a: v })) }), /* @__PURE__ */ React.createElement(Switch, { checked: switches.b, onChange: (v) => setSwitches((s) => ({ ...s, b: v })) }), /* @__PURE__ */ React.createElement(Switch, { checked: true, disabled: true }), /* @__PURE__ */ React.createElement(Checkbox, { checked, onChange: setChecked, label: "Checked" }), /* @__PURE__ */ React.createElement(Checkbox, { checked: false, onChange: () => {
  }, label: "Unchecked" })), /* @__PURE__ */ React.createElement(Section, { label: "04 \u2014 Inputs", cols: true }, /* @__PURE__ */ React.createElement("div", { className: "w-60" }, /* @__PURE__ */ React.createElement(TextField, { label: "Lot number", mono: true, value: lot, onChange: setLot })), /* @__PURE__ */ React.createElement("div", { className: "w-32" }, /* @__PURE__ */ React.createElement(QuantityStepper, { value: qty, onChange: setQty, min: 0 })), /* @__PURE__ */ React.createElement("div", { className: "w-60" }, /* @__PURE__ */ React.createElement(TextField, { label: "Notes", multiline: true, value: notes, onChange: setNotes, placeholder: "Typing\u2026" })), /* @__PURE__ */ React.createElement("div", { className: "w-60" }, /* @__PURE__ */ React.createElement(TextField, { label: "With error", value: "", onChange: () => {
  }, error: "Required field" }))), /* @__PURE__ */ React.createElement(Section, { label: "05 \u2014 Selection & tabs", cols: true }, /* @__PURE__ */ React.createElement("div", { className: "w-72" }, /* @__PURE__ */ React.createElement(
    SegmentedControl,
    {
      options: [{ value: "small", label: "Small" }, { value: "medium", label: "Medium" }, { value: "large", label: "Large" }],
      value: size,
      onChange: setSize
    }
  )), /* @__PURE__ */ React.createElement(
    RadioGroup,
    {
      options: [{ value: "batch", label: "Per batch" }, { value: "shift", label: "Per shift" }],
      value: mode,
      onChange: setMode
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "w-72" }, /* @__PURE__ */ React.createElement(
    Tabs,
    {
      tabs: [{ value: "details", label: "Details" }, { value: "routing", label: "Routing" }, { value: "history", label: "History" }],
      value: tab,
      onChange: setTab
    }
  ))), /* @__PURE__ */ React.createElement(Section, { label: "06 \u2014 Status & badges", cols: true }, /* @__PURE__ */ React.createElement(StatusPill, { status: "running", label: "RUNNING" }), /* @__PURE__ */ React.createElement(StatusPill, { status: "pending", label: "PENDING" }), /* @__PURE__ */ React.createElement(StatusPill, { status: "blocked", label: "BLOCKED" }), /* @__PURE__ */ React.createElement(StatusPill, { status: "downtime", label: "DOWNTIME" }), /* @__PURE__ */ React.createElement(StatusPill, { status: "done", label: "DONE" }), /* @__PURE__ */ React.createElement(Badge, { variant: "danger" }, "3"), /* @__PURE__ */ React.createElement(Badge, { variant: "neutral" }, "12"), /* @__PURE__ */ React.createElement(Badge, { variant: "outline" }, "HIGH"), /* @__PURE__ */ React.createElement(OnlineDot, { label: "ONLINE" }), /* @__PURE__ */ React.createElement("div", { className: "w-64" }, /* @__PURE__ */ React.createElement(ProgressBar, { value: 43 }))), /* @__PURE__ */ React.createElement(Section, { label: "07 \u2014 Action menu / 13 \u2014 Dropdowns", cols: true }, /* @__PURE__ */ React.createElement(
    ActionMenu,
    {
      trigger: /* @__PURE__ */ React.createElement(Button, { variant: "ghost" }, "Actions \u22EF"),
      items: [
        { key: "edit", label: "Edit order", onSelect: () => {
        } },
        { key: "dup", label: "Duplicate", onSelect: () => {
        } },
        { key: "print", label: "Print label", onSelect: () => {
        } },
        { divider: true },
        { key: "del", label: "Delete", destructive: true, onSelect: () => {
        } }
      ]
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "w-60" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [{ value: "all", label: "All status" }, { value: "running", label: "Running" }, { value: "pending", label: "Pending" }, { value: "blocked", label: "Blocked" }, { value: "done", label: "Done" }],
      value: status,
      onChange: setStatus
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "w-60" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      multiple: true,
      options: ["WSZ-01", "WSZ-02", "WSP-01", "QA-01"].map((v) => ({ value: v, label: v })),
      values: lines,
      onChange: setLines,
      label: lines.length === 4 ? "All lines" : `${lines.length} lines selected`
    }
  ))), /* @__PURE__ */ React.createElement(Section, { label: "14 \u2014 Date picker", cols: true }, /* @__PURE__ */ React.createElement("div", { className: "w-60" }, /* @__PURE__ */ React.createElement(DatePicker, { label: "Due date", value: due, onChange: setDue })), /* @__PURE__ */ React.createElement(Calendar, { value: day, onChange: setDay })), /* @__PURE__ */ React.createElement(Section, { label: "15 \u2014 Skeleton", cols: true }, /* @__PURE__ */ React.createElement("div", { className: "flex w-64 items-center gap-3" }, /* @__PURE__ */ React.createElement(Skeleton, { circle: true, height: 40 }), /* @__PURE__ */ React.createElement("div", { className: "flex-1 space-y-2" }, /* @__PURE__ */ React.createElement(Skeleton, { width: "70%", height: 13 }), /* @__PURE__ */ React.createElement(Skeleton, { width: "45%", height: 11 })))), /* @__PURE__ */ React.createElement(Section, { label: "08 \u2014 Inline alerts" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-4 gap-3" }, /* @__PURE__ */ React.createElement(InlineAlert, { severity: "success", title: "Batch #3 closed" }, "108 pcs accepted \xB7 LOT printed."), /* @__PURE__ */ React.createElement(InlineAlert, { severity: "info", title: "New work order" }, "WO-2026-008 added to queue."), /* @__PURE__ */ React.createElement(InlineAlert, { severity: "warning", title: "Material low" }, "Filter media at 12% \u2014 reorder."), /* @__PURE__ */ React.createElement(InlineAlert, { severity: "error", title: "Production blocked" }, "Media tear \u2014 WO-2026-006 halted."))), /* @__PURE__ */ React.createElement(Section, { label: "09 \u2014 Overlays", cols: true }, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => toast({ severity: "success", title: "Output saved", body: "+12 pcs logged to WO-2026-001" }) }, "Success toast"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => toast({ severity: "warning", title: "Downtime started", body: "Reason \u2014 glue station warm-up" }) }, "Warning toast"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => toast({ severity: "error", title: "Sync failed", body: "Offline \u2014 retrying in 5s\u2026" }) }, "Error toast"), /* @__PURE__ */ React.createElement(Button, { variant: "danger", onClick: () => setConfirmOpen(true) }, "Confirm dialog"), /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: () => setModalOpen(true) }, "Form modal")), /* @__PURE__ */ React.createElement(Section, { label: "12 \u2014 Data table" }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: SAMPLE_ROWS,
      columns: TABLE_COLUMNS,
      fluid: false,
      searchPlaceholder: "Search all columns",
      enableSelection: true,
      selectionLabel: (n, m) => `${n} of ${m} row(s) selected`,
      bulkActions: (rows, clear) => /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => {
      } }, "Print labels"), /* @__PURE__ */ React.createElement(Button, { variant: "danger", onClick: () => {
      } }, "Mark blocked"), /* @__PURE__ */ React.createElement("button", { type: "button", className: "cursor-pointer text-[12.5px] text-om-muted", onClick: clear }, "Clear")),
      columnsLabel: "Columns \u25BE",
      columnsMenuLabel: "Toggle columns",
      emptyLabel: "No rows match the filters.",
      rangeLabel: (start, end, total) => total === 0 ? "0 results" : `${start}\u2013${end} of ${total}`,
      pageSize: 6,
      bodyMaxHeight: 252
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "mt-6 font-mono text-[11px] text-om-faint" }, "$ openmes design-system \xB7 @openmes/ui \xB7 Geist White")), /* @__PURE__ */ React.createElement(
    ConfirmDialog,
    {
      open: confirmOpen,
      onClose: () => setConfirmOpen(false),
      onConfirm: () => setConfirmOpen(false),
      title: "Delete WO-2026-007?",
      confirmLabel: "Delete order",
      cancelLabel: "Cancel"
    },
    "This permanently removes the work order and its routing. Logged output stays in reports. Cannot be undone."
  ), /* @__PURE__ */ React.createElement(
    Modal,
    {
      open: modalOpen,
      onClose: () => setModalOpen(false),
      title: "New work order",
      subtitle: "LINE WSZ-01",
      footer: /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => setModalOpen(false) }, "Cancel"), /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: () => setModalOpen(false) }, "Create"))
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-3" }, /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: [{ value: "hepa13", label: "HEPA-13 Standard" }, { value: "carbon", label: "Carbon Pre-Filter" }],
        value: "hepa13",
        onChange: () => {
        }
      }
    ), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2.5" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement(TextField, { label: "Qty", mono: true, value: "250", onChange: () => {
    } })), /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement(TextField, { label: "Due", mono: true, value: "26 May", onChange: () => {
    } }))))
  ));
}
function ComponentGallery() {
  return /* @__PURE__ */ React.createElement(ToastProvider, null, /* @__PURE__ */ React.createElement(GalleryBody, null));
}
export {
  ComponentGallery as default
};
