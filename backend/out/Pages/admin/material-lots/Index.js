import { useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import { Button, ConfirmDialog, Modal, TextField } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { STATUS_STYLES, materialLotStatusLabel } from "./fields";
import { __ } from "../../../lib/i18n";
function MaterialLotsIndex() {
  const { materialNames = {}, sourceNames = {} } = usePage().props;
  const [holdFor, setHoldFor] = useState(null);
  const [holdReason, setHoldReason] = useState("");
  const [holdIssueId, setHoldIssueId] = useState("");
  const [holdError, setHoldError] = useState("");
  const [releaseFor, setReleaseFor] = useState(null);
  const openHold = (lot) => {
    setHoldFor(lot);
    setHoldReason("");
    setHoldIssueId("");
    setHoldError("");
  };
  const submitHold = () => {
    if (!holdReason.trim()) {
      setHoldError(__("A hold reason is required."));
      return;
    }
    router.post(
      `/admin/material-lots/${holdFor.id}/hold`,
      { reason: holdReason.trim(), issue_id: holdIssueId.trim() || null },
      {
        preserveScroll: true,
        onSuccess: () => setHoldFor(null),
        onError: (errors) => setHoldError(errors.reason ?? errors.issue_id ?? __("Could not place the lot on hold."))
      }
    );
  };
  const submitRelease = () => {
    if (!releaseFor) return;
    router.post(`/admin/material-lots/${releaseFor.id}/release`, {}, {
      preserveScroll: true,
      onSuccess: () => setReleaseFor(null)
    });
    setReleaseFor(null);
  };
  const columns = [
    { key: "lot_number", label: __("Lot Number"), className: "font-mono text-om-muted" },
    { key: "material", label: __("Material"), className: "text-om-muted", render: (r) => materialNames[r.material_id] ?? "\u2014" },
    { key: "qty", label: __("Avail / Recv"), className: "text-om-muted", render: (r) => `${r.quantity_available ?? "\u2014"} / ${r.quantity_received ?? "\u2014"}` },
    { key: "unit_of_measure", label: __("Unit"), className: "text-om-muted" },
    { key: "expiry_date", label: __("Expiry"), className: "text-om-muted", render: (r) => r.expiry_date ? r.expiry_date.slice(0, 10) : "\u2014" },
    {
      key: "status",
      label: __("Status"),
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: `text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? "bg-om-chip text-om-muted"}` }, materialLotStatusLabel(r.status))
    }
  ];
  const actions = (r) => {
    const list = [{ label: __("Edit"), icon: "edit", href: `/admin/material-lots/${r.id}/edit` }];
    if (r.status === "received" || r.status === "released") {
      list.push({ label: __("Hold"), variant: "warning", onClick: () => openHold(r) });
    } else if (r.status === "quarantine") {
      list.push({ label: __("Release"), variant: "primary", onClick: () => setReleaseFor(r) });
    }
    list.push({
      label: __("Delete"),
      icon: "delete",
      variant: "danger",
      onClick: () => {
        if (confirm(__('Delete material lot ":name"?', { name: r.lot_number }))) {
          router.delete(`/admin/material-lots/${r.id}`, { preserveScroll: true });
        }
      }
    });
    return list;
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Material Lots") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "material_lots",
      title: __("Material Lots"),
      createHref: "/admin/material-lots/create",
      createLabel: __("+ New Lot"),
      columns,
      orderBy: "lot_number",
      actions,
      emptyText: __("No material lots yet.")
    }
  ), /* @__PURE__ */ React.createElement(
    Modal,
    {
      open: holdFor != null,
      onClose: () => setHoldFor(null),
      title: __("Place lot on hold"),
      subtitle: holdFor?.lot_number,
      closeLabel: __("Close"),
      footer: /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => setHoldFor(null) }, __("Cancel")), /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: submitHold }, __("Place on hold")))
    },
    /* @__PURE__ */ React.createElement("p", { className: "mb-[14px] text-[12.5px] leading-[1.5] text-om-muted" }, __("The lot will move to quarantine and cannot be consumed until released.")),
    /* @__PURE__ */ React.createElement(
      TextField,
      {
        label: __("Hold reason"),
        multiline: true,
        value: holdReason,
        onChange: (v) => {
          setHoldReason(v);
          if (holdError) setHoldError("");
        },
        placeholder: __("e.g. failed incoming inspection, supplier recall\u2026"),
        error: holdError || void 0,
        className: "mb-[14px]"
      }
    ),
    /* @__PURE__ */ React.createElement(
      TextField,
      {
        label: __("Linked issue ID (optional)"),
        mono: true,
        value: holdIssueId,
        onChange: setHoldIssueId,
        placeholder: __("e.g. 1042"),
        inputMode: "numeric"
      }
    )
  ), /* @__PURE__ */ React.createElement(
    ConfirmDialog,
    {
      open: releaseFor != null,
      onClose: () => setReleaseFor(null),
      onConfirm: submitRelease,
      title: __("Release lot from hold?"),
      confirmLabel: __("Release"),
      cancelLabel: __("Cancel"),
      destructive: false,
      icon: "\u21BA"
    },
    releaseFor ? __('Lot ":name" will be released from quarantine and become available again.', { name: releaseFor.lot_number }) : null
  ));
}
MaterialLotsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaterialLotsIndex as default
};
