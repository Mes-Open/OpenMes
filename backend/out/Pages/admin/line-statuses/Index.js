import { useMemo, useState } from "react";
import { Head, router } from "@inertiajs/react";
import { Button, Checkbox, IconButton } from "@openmes/ui";
import { useLiveQuery } from "@tanstack/react-db";
import AppLayout from "../../../layouts/AppLayout";
import { realtimeCollection } from "../../../lib/realtimeCollection";
import { __ } from "../../../lib/i18n";
function LineStatusesIndex() {
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Line Statuses") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-4xl mx-auto" }, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-2" }, __("Line Statuses")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-sm mb-6" }, __("Global kanban statuses available to every production line.")), /* @__PURE__ */ React.createElement(Editor, null)));
}
LineStatusesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function Editor() {
  const collection = useMemo(() => realtimeCollection("line_statuses_global"), []);
  const { data: rows } = useLiveQuery(
    (q) => q.from({ s: collection }).orderBy(({ s }) => s.sort_order, "asc")
  );
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm overflow-hidden" }, /* @__PURE__ */ React.createElement("table", { className: "w-full text-sm" }, /* @__PURE__ */ React.createElement("thead", null, /* @__PURE__ */ React.createElement("tr", { className: "text-left text-om-muted border-b border-om-line bg-om-panel" }, /* @__PURE__ */ React.createElement("th", { className: "px-4 py-3" }, __("Color")), /* @__PURE__ */ React.createElement("th", { className: "px-4 py-3" }, __("Name")), /* @__PURE__ */ React.createElement("th", { className: "px-4 py-3 w-24" }, __("Order")), /* @__PURE__ */ React.createElement("th", { className: "px-4 py-3 w-24" }, __("Default")), /* @__PURE__ */ React.createElement("th", { className: "px-4 py-3 text-right" }, __("Actions")))), /* @__PURE__ */ React.createElement("tbody", null, rows.map((s) => /* @__PURE__ */ React.createElement(StatusRow, { key: s.id, status: s })), /* @__PURE__ */ React.createElement(AddRow, { nextOrder: rows.length }))));
}
function StatusRow({ status }) {
  const [name, setName] = useState(status.name);
  const [color, setColor] = useState(status.color);
  const [sortOrder, setSortOrder] = useState(status.sort_order ?? 0);
  const [isDefault, setIsDefault] = useState(!!status.is_default);
  const [saving, setSaving] = useState(false);
  const dirty = name !== status.name || color !== status.color || Number(sortOrder) !== (status.sort_order ?? 0) || isDefault !== !!status.is_default;
  const save = () => {
    setSaving(true);
    router.put(
      `/admin/line-statuses/${status.id}`,
      { name, color, sort_order: sortOrder, is_default: isDefault },
      { preserveScroll: true, onFinish: () => setSaving(false) }
    );
  };
  const destroy = () => {
    if (confirm(__('Delete status ":name"?', { name: status.name }))) {
      router.delete(`/admin/line-statuses/${status.id}`, { preserveScroll: true });
    }
  };
  return /* @__PURE__ */ React.createElement("tr", { className: "border-b border-om-line last:border-0" }, /* @__PURE__ */ React.createElement("td", { className: "px-4 py-2" }, /* @__PURE__ */ React.createElement("input", { type: "color", value: color, onChange: (e) => setColor(e.target.value), className: "h-8 w-12 rounded border border-om-line p-0.5" })), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-2" }, /* @__PURE__ */ React.createElement("input", { type: "text", value: name, onChange: (e) => setName(e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-2" }, /* @__PURE__ */ React.createElement("input", { type: "number", value: sortOrder, onChange: (e) => setSortOrder(e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-center" }, /* @__PURE__ */ React.createElement(Checkbox, { checked: isDefault, onChange: setIsDefault }))), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-end gap-2" }, /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "button",
      variant: "primary",
      onClick: save,
      disabled: !dirty || saving,
      loading: saving
    },
    saving ? __("Saving\u2026") : __("Save")
  ), /* @__PURE__ */ React.createElement(
    IconButton,
    {
      variant: "danger",
      onClick: destroy,
      title: __("Delete"),
      "aria-label": __("Delete")
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" }))
  ))));
}
function AddRow({ nextOrder }) {
  const [name, setName] = useState("");
  const [color, setColor] = useState("#3b82f6");
  const [adding, setAdding] = useState(false);
  const add = () => {
    if (!name.trim()) return;
    setAdding(true);
    router.post(
      "/admin/line-statuses",
      { name, color, sort_order: nextOrder, is_default: false },
      {
        preserveScroll: true,
        onSuccess: () => {
          setName("");
          setColor("#3b82f6");
        },
        onFinish: () => setAdding(false)
      }
    );
  };
  return /* @__PURE__ */ React.createElement("tr", { className: "bg-om-panel/50" }, /* @__PURE__ */ React.createElement("td", { className: "px-4 py-2" }, /* @__PURE__ */ React.createElement("input", { type: "color", value: color, onChange: (e) => setColor(e.target.value), className: "h-8 w-12 rounded border border-om-line p-0.5" })), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-2" }, /* @__PURE__ */ React.createElement("input", { type: "text", value: name, onChange: (e) => setName(e.target.value), placeholder: __("New status name\u2026"), className: "form-input w-full" })), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-2 text-om-faint" }, nextOrder), /* @__PURE__ */ React.createElement("td", null), /* @__PURE__ */ React.createElement("td", { className: "px-4 py-2 text-right" }, /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "button",
      variant: "primary",
      onClick: add,
      disabled: !name.trim() || adding,
      loading: adding
    },
    adding ? __("Adding\u2026") : __("Add")
  )));
}
export {
  LineStatusesIndex as default
};
