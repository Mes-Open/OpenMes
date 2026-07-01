import { Head, router, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import InspectionPlanForm from "./Form";
import { __, formatDateTime } from "../../../lib/i18n";
function VersionBadge({ v }) {
  const cls = v.is_draft ? "bg-om-downtime-bg text-om-downtime" : v.is_active ? "bg-om-running-bg text-om-running" : "bg-om-chip text-om-muted";
  const label = v.is_draft ? __("Draft") : v.is_active ? __("Published") : __("Archived");
  return /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded-full text-xs font-medium ${cls}` }, label);
}
function InspectionPlanEdit() {
  const { plan, materials = [], materialTypes = [], history = [] } = usePage().props;
  const form = useForm({
    name: plan.name ?? "",
    description: plan.description ?? "",
    scope: plan.scope ?? "generic",
    material_id: plan.material_id != null ? String(plan.material_id) : "",
    material_type_id: plan.material_type_id != null ? String(plan.material_type_id) : "",
    criteria: plan.criteria ?? []
  });
  const submit = (e) => {
    e.preventDefault();
    form.put(`/admin/inspection-plans/${plan.id}`);
  };
  const publish = () => {
    if (confirm(__("Publish this version? It becomes the live plan used for new inspections."))) {
      router.post(`/admin/inspection-plans/${plan.id}/publish`);
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `${__("Edit")} ${plan.name}` }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 mb-2" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Edit Inspection Plan")), /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded-full text-xs font-medium bg-om-chip text-om-accent" }, "v", plan.version), /* @__PURE__ */ React.createElement(VersionBadge, { v: { is_draft: plan.is_draft, is_active: plan.is_active } })), plan.is_draft ? /* @__PURE__ */ React.createElement("div", { className: "mb-5 rounded-om-sm bg-om-downtime-bg border border-om-line px-4 py-3 flex items-center justify-between gap-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-downtime" }, __("This is a draft \u2014 editable in place and not yet used for inspections.")), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: publish, className: "btn-touch btn-primary whitespace-nowrap" }, __("Publish"))) : /* @__PURE__ */ React.createElement("div", { className: "mb-5 rounded-om-sm bg-om-chip border border-om-line px-4 py-3" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-accent" }, __("This version is published and immutable. Saving creates a new draft version \u2014 the published version stays unchanged so past inspections remain reproducible."))), /* @__PURE__ */ React.createElement(
    InspectionPlanForm,
    {
      form,
      materials,
      materialTypes,
      submitLabel: plan.is_draft ? __("Save Changes") : __("Save as new version"),
      onSubmit: submit
    }
  ), history.length > 1 && /* @__PURE__ */ React.createElement("div", { className: "mt-8 max-w-4xl" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mb-3" }, __("Version history")), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm divide-y divide-om-line2" }, history.map((v) => /* @__PURE__ */ React.createElement("div", { key: v.id, className: "flex items-center gap-3 px-4 py-2.5 text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted w-10" }, "v", v.version), /* @__PURE__ */ React.createElement(VersionBadge, { v }), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted flex-1" }, v.is_draft ? __("Last edited :t", { t: formatDateTime(v.updated_at) }) : __("Published :t", { t: formatDateTime(v.published_at) })), v.id !== plan.id && /* @__PURE__ */ React.createElement("a", { href: `/admin/inspection-plans/${v.id}/edit`, className: "text-om-accent hover:underline" }, __("Open")))))));
}
InspectionPlanEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  InspectionPlanEdit as default
};
