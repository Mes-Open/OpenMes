import { useState, useRef, useEffect } from "react";
import { Link } from "@inertiajs/react";
const KIND_TO_TYPE = {
  "work-order": "work_order",
  "finished-goods": "finished_goods",
  "workstation-step": "workstation_step",
  pallet: "pallet"
};
function LabelPrintMenu({ kind, id, templates = [], label = "Print Label" }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  const wantType = KIND_TO_TYPE[kind];
  const applicable = templates.filter((t) => t.type === wantType);
  useEffect(() => {
    if (!open) return void 0;
    const onClick = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, [open]);
  if (!id) return null;
  const printIcon = /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" }));
  if (applicable.length === 0) {
    return /* @__PURE__ */ React.createElement(
      Link,
      {
        href: "/packaging/label-templates",
        className: "inline-flex items-center gap-1.5 px-3 py-1.5 rounded-om-sm text-sm bg-om-chip hover:bg-om-line2 text-om-muted",
        title: "Configure label templates first"
      },
      printIcon,
      " Set up labels\u2026"
    );
  }
  const url = (tid, fmt) => `/packaging/labels/${kind}/${id}/${fmt}?template=${tid}`;
  return /* @__PURE__ */ React.createElement("div", { className: "relative inline-block", ref }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setOpen((o) => !o),
      className: "inline-flex items-center gap-1.5 px-3 py-1.5 rounded-om-sm text-sm bg-om-chip hover:bg-om-line2 text-om-muted"
    },
    printIcon,
    label,
    /* @__PURE__ */ React.createElement("svg", { className: "w-3 h-3", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 9l-7 7-7-7" }))
  ), open && /* @__PURE__ */ React.createElement("div", { className: "absolute right-0 mt-1 w-64 bg-om-card rounded-om-sm shadow-lg border border-om-line2 z-50" }, /* @__PURE__ */ React.createElement("div", { className: "p-2" }, /* @__PURE__ */ React.createElement("p", { className: "px-2 py-1 text-xs text-om-muted uppercase tracking-wide" }, "Choose template"), applicable.map((t) => /* @__PURE__ */ React.createElement("div", { key: t.id, className: "px-2 py-1.5" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm font-medium text-om-muted truncate" }, t.name, t.is_default && /* @__PURE__ */ React.createElement("span", { className: "text-[10px] text-om-accent ml-1" }, "default")), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, t.size, " mm \xB7 ", String(t.barcode_format ?? "").toUpperCase()), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 mt-1" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: url(t.id, "pdf"),
      target: "_blank",
      rel: "noreferrer",
      className: "flex-1 text-center text-xs px-2 py-1 rounded bg-om-ink text-om-on-ink hover:bg-om-ink-hover"
    },
    "PDF"
  ), /* @__PURE__ */ React.createElement(
    "a",
    {
      href: url(t.id, "zpl"),
      className: "flex-1 text-center text-xs px-2 py-1 rounded bg-om-ink text-om-on-ink hover:bg-om-ink-hover"
    },
    "ZPL"
  )))))));
}
export {
  LabelPrintMenu as default
};
