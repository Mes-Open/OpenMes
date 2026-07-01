import { Head, Link, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import { ActiveBadge } from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
function WorkstationsIndex() {
  const { line, workstations = [] } = usePage().props;
  const handleToggle = (ws) => {
    router.post(`/admin/lines/${line.id}/workstations/${ws.id}/toggle-active`, {}, { preserveScroll: true });
  };
  const handleDelete = (ws) => {
    if (confirm(__("Are you sure you want to delete this workstation?"))) {
      router.delete(`/admin/lines/${line.id}/workstations/${ws.id}`, { preserveScroll: true });
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Workstations \u2014 :name", { name: line.name }) }), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}`,
      className: "text-om-accent hover:text-om-accent flex items-center gap-2 mb-4 text-sm"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    __("Back to :name", { name: line.name })
  ), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Workstations")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, line.name)), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/workstations/create`,
      className: "bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover"
    },
    __("+ Add Workstation")
  ))), workstations.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm text-center py-12" }, /* @__PURE__ */ React.createElement("svg", { className: "mx-auto h-16 w-16 text-om-faint mb-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" })), /* @__PURE__ */ React.createElement("p", { className: "text-lg font-medium text-om-muted" }, __("No workstations yet")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1 mb-4" }, __("Get started by creating your first workstation for this line.")), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/workstations/create`,
      className: "inline-block bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover"
    },
    __("+ Create Workstation")
  )) : /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" }, workstations.map((ws) => /* @__PURE__ */ React.createElement("div", { key: ws.id, className: "bg-om-card rounded-om-sm shadow-sm p-5 hover:shadow-md transition-shadow" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between mb-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 mb-1" }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-bold text-om-ink" }, ws.name), /* @__PURE__ */ React.createElement(ActiveBadge, { active: ws.is_active })), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted font-mono" }, ws.code), ws.workstation_type && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-1" }, __("Type: :type", { type: ws.workstation_type })))), /* @__PURE__ */ React.createElement("div", { className: "mb-4 p-3 bg-om-panel rounded-om-sm grid grid-cols-2 gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "text-center" }, /* @__PURE__ */ React.createElement("p", { className: "text-2xl font-bold text-om-ink" }, ws.template_steps_count), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, __("Template Steps"))), /* @__PURE__ */ React.createElement("div", { className: "text-center" }, /* @__PURE__ */ React.createElement("p", { className: "text-2xl font-bold text-om-accent" }, ws.workers_count), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, __("Workers")))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 pt-4 border-t border-om-line2" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/workstations/${ws.id}/edit`,
      className: "flex-1 text-center text-sm px-3 py-2 border border-om-line rounded-om-sm text-om-muted hover:bg-om-bg font-medium"
    },
    __("Edit")
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => handleToggle(ws),
      className: "p-2 text-om-muted hover:text-om-ink",
      title: ws.is_active ? __("Deactivate") : __("Activate")
    },
    ws.is_active ? /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" })) : /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" }))
  ), ws.template_steps_count === 0 ? /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => handleDelete(ws),
      className: "p-2 text-om-blocked hover:text-om-blocked",
      title: __("Delete")
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" }))
  ) : /* @__PURE__ */ React.createElement("span", { className: "p-2 text-om-faintest", title: __("Cannot delete \u2014 has template steps") }, /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" }))))))));
}
WorkstationsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkstationsIndex as default
};
