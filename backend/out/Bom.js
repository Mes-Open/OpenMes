import { useState, useMemo } from "react";
import { __ } from "../../../lib/i18n";
import { Head, router, useForm, usePage } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
const TYPE_COLORS = {
  raw_material: "bg-om-downtime-bg text-om-downtime",
  semi_finished: "bg-om-chip text-om-accent",
  packaging: "bg-om-chip text-om-ink"
};
function typeColorClass(code) {
  return TYPE_COLORS[code] ?? "bg-om-chip text-om-ink";
}
function MaterialForm({ productType, processTemplate, materials, steps, item, onCancel }) {
  const isEdit = !!item;
  const form = useForm({
    material_id: item ? String(item.material_id ?? "") : "",
    quantity_per_unit: item ? String(item.quantity_per_unit ?? "") : "",
    template_step_id: item && item.template_step_id != null ? String(item.template_step_id) : "",
    scrap_percentage: item ? String(item.scrap_percentage ?? "0") : "0",
    consumed_at: item ? item.consumed_at ?? "start" : "start",
    notes: item ? item.notes ?? "" : ""
  });
  const { data, setData, errors, processing } = form;
  const selectedMaterial = isEdit ? null : materials.find((m) => String(m.id) === String(data.material_id));
  const unit = isEdit ? item.unit_of_measure : selectedMaterial?.unit_of_measure;
  const onMaterialChange = (id) => {
    setData("material_id", id);
    const m = materials.find((x) => String(x.id) === String(id));
    if (m && m.default_scrap_percentage != null && (data.scrap_percentage === "" || data.scrap_percentage === "0")) {
      setData("scrap_percentage", String(m.default_scrap_percentage));
    }
  };
  const submit = (e) => {
    e.preventDefault();
    const base = `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/bom`;
    if (isEdit) {
      form.put(`${base}/${item.id}`, { onSuccess: onCancel });
    } else {
      form.post(base, { onSuccess: onCancel });
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: "card mb-6", style: { borderLeft: "4px solid #3b82f6" } }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-semibold mb-4" }, isEdit ? `${__("Edit BOM Item")} - ${item.material_name}` : __("Add Material to BOM")), /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" }, isEdit ? /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Material"), /* @__PURE__ */ React.createElement("div", { className: "form-input w-full bg-om-panel text-om-muted" }, item.material_code, " - ", item.material_name)) : /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Material"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.material_id == null ? "" : String(data.material_id),
      onChange: (v) => onMaterialChange(v),
      placeholder: "Select material...",
      options: materials.map((m) => ({
        value: String(m.id),
        label: `${m.code} - ${m.name} (${m.unit_of_measure ? `${m.unit_of_measure}, ` : ""}${m.material_type_name})`
      })),
      className: "w-full"
    }
  ), errors.material_id && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-sm text-om-blocked" }, errors.material_id)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Quantity per Unit"), unit ? ` (${unit})` : "", " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      step: "0.0001",
      min: "0.0001",
      required: true,
      value: data.quantity_per_unit,
      onChange: (e) => setData("quantity_per_unit", e.target.value),
      className: `form-input w-full${errors.quantity_per_unit ? " border-om-blocked" : ""}`
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-faint" }, "How much of this material is needed per one finished product unit."), errors.quantity_per_unit && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-sm text-om-blocked" }, errors.quantity_per_unit)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Step (optional)")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.template_step_id == null ? "" : String(data.template_step_id),
      onChange: (v) => setData("template_step_id", v),
      options: [
        { value: "", label: "All steps / general" },
        ...steps.map((s) => ({
          value: String(s.id),
          label: `#${s.step_number} - ${s.name}`
        }))
      ],
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Scrap %")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      step: "0.01",
      min: "0",
      max: "100",
      value: data.scrap_percentage,
      onChange: (e) => setData("scrap_percentage", e.target.value),
      className: "form-input w-full"
    }
  ), selectedMaterial?.default_scrap_percentage != null && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-faint" }, "Pre-filled from the material default (", selectedMaterial.default_scrap_percentage, "%); adjust if needed.")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Consumed At")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.consumed_at == null ? "" : String(data.consumed_at),
      onChange: (v) => setData("consumed_at", v),
      options: [
        { value: "start", label: "Start of step" },
        { value: "during", label: "During step" },
        { value: "end", label: "End of step" }
      ],
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Notes")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: data.notes,
      onChange: (e) => setData("notes", e.target.value),
      placeholder: "Optional notes",
      className: "form-input w-full"
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-end gap-3 mt-4" }, /* @__PURE__ */ React.createElement("button", { type: "button", onClick: onCancel, className: "btn-touch btn-secondary" }, "Cancel"), /* @__PURE__ */ React.createElement("button", { type: "submit", disabled: processing, className: "btn-touch btn-primary" }, isEdit ? processing ? __("Saving\u2026") : __("Save Changes") : processing ? __("Adding\u2026") : __("Add to BOM")))));
}
function ProcessTemplatesBom() {
  const { productType, processTemplate, bomItems = [], materials = [], steps = [] } = usePage().props;
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingItem, setEditingItem] = useState(null);
  const startEdit = (item) => {
    setShowAddForm(false);
    setEditingItem(item);
  };
  const handleRemove = (item) => {
    if (!confirm(__("Remove this material from BOM?"))) return;
    router.delete(
      `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}/bom/${item.id}`,
      { preserveScroll: true }
    );
  };
  const columns = useMemo(() => [
    {
      id: "material",
      accessorKey: "material_name",
      header: __("Material"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "text-sm font-medium text-om-ink" }, row.original.material_name), /* @__PURE__ */ React.createElement("div", { className: "text-xs text-om-muted font-mono" }, row.original.material_code))
    },
    {
      id: "type",
      accessorKey: "material_type_name",
      header: __("Type"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement(
        "span",
        {
          className: `px-2 py-1 rounded-full text-xs font-medium ${typeColorClass(
            row.original.material_type_code
          )}`
        },
        row.original.material_type_name
      )
    },
    {
      id: "step",
      accessorFn: (r) => r.step_number != null ? `#${r.step_number} ${r.step_name}` : "",
      header: __("Step"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-muted" }, row.original.step_number != null ? `#${row.original.step_number} ${row.original.step_name}` : /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, __("General")))
    },
    {
      id: "qty_per_unit",
      accessorKey: "quantity_per_unit",
      header: __("Qty/Unit"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-sm font-mono" }, row.original.quantity_per_unit, " ", row.original.unit_of_measure)
    },
    {
      id: "scrap",
      accessorKey: "scrap_percentage",
      header: __("Scrap %"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-sm" }, row.original.scrap_percentage, "%")
    },
    {
      id: "consumed",
      accessorKey: "consumed_at",
      header: "Consumed",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-muted capitalize" }, row.original.consumed_at)
    },
    {
      id: "tracking",
      accessorKey: "tracking_type",
      header: "Tracking",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-muted capitalize" }, row.original.tracking_type)
    },
    {
      id: "actions",
      header: __("Actions"),
      enableSorting: false,
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(
        "button",
        {
          type: "button",
          onClick: () => startEdit(row.original),
          className: "text-om-accent hover:text-om-accent text-sm mr-4"
        },
        "Edit"
      ), /* @__PURE__ */ React.createElement(
        "button",
        {
          type: "button",
          onClick: () => handleRemove(row.original),
          className: "text-om-blocked hover:text-om-blocked text-sm"
        },
        "Remove"
      ))
    }
  ], [productType.id, processTemplate.id]);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `BOM - ${processTemplate.name}` }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}`,
      className: "text-om-accent hover:text-om-accent flex items-center gap-2 mb-4"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    "Back to Template"
  ), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Bill of Materials")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, processTemplate.name, " (v", processTemplate.version, ") \u2022 ", productType.name)), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => {
        setEditingItem(null);
        setShowAddForm((v) => !v);
      },
      className: "btn-touch btn-primary"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 inline-block mr-2", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M12 4v16m8-8H4" })),
    __("Add Material")
  ))), showAddForm && !editingItem && /* @__PURE__ */ React.createElement(
    MaterialForm,
    {
      productType,
      processTemplate,
      materials,
      steps,
      onCancel: () => setShowAddForm(false)
    }
  ), editingItem && /* @__PURE__ */ React.createElement(
    MaterialForm,
    {
      key: editingItem.id,
      productType,
      processTemplate,
      materials,
      steps,
      item: editingItem,
      onCancel: () => setEditingItem(null)
    }
  ), bomItems.length > 0 ? /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: bomItems,
      columns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  ) : /* @__PURE__ */ React.createElement("div", { className: "card text-center py-12" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-lg mb-4" }, __("No materials in BOM yet.")), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setShowAddForm(true),
      className: "btn-touch btn-primary"
    },
    "Add First Material"
  ))));
}
ProcessTemplatesBom.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProcessTemplatesBom as default
};
