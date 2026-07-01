import { useState, useEffect } from "react";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { Button } from "@openmes/ui";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
const INPUT_CLASS = "w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]";
function TwoFactor() {
  const { enabled, secret, qrCodeDataUri, recoveryCodes } = usePage().props;
  useEffect(() => {
    if (typeof router.encryptHistory === "function") {
      router.encryptHistory();
    }
    if (recoveryCodes && recoveryCodes.length > 0 && typeof router.clearHistory === "function") {
      router.clearHistory();
    }
  }, [recoveryCodes]);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Two-Factor Authentication") }), /* @__PURE__ */ React.createElement("div", { className: "p-6 max-w-2xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(Link, { href: "/settings", className: "text-[13px] text-om-muted hover:text-om-ink flex items-center gap-1 transition-colors" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })), __("Back to Settings")), /* @__PURE__ */ React.createElement("h1", { className: "mt-3 text-[22px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Two-Factor Authentication"))), recoveryCodes && recoveryCodes.length > 0 && /* @__PURE__ */ React.createElement(RecoveryCodes, { codes: recoveryCodes }), enabled ? /* @__PURE__ */ React.createElement(ManagePanel, null) : /* @__PURE__ */ React.createElement(SetupPanel, { secret, qrCodeDataUri })));
}
TwoFactor.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function RecoveryCodes({ codes }) {
  const [copied, setCopied] = useState(false);
  const copy = () => {
    navigator.clipboard?.writeText(codes.join("\n")).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-downtime-bg border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "font-mono text-[10px] uppercase tracking-[0.12em] text-om-ink font-semibold" }, __("Recovery codes")), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted mt-1" }, __("Store these somewhere safe. Each code can be used once if you lose access to your authenticator. They won't be shown again.")), /* @__PURE__ */ React.createElement("div", { className: "mt-3 grid grid-cols-2 gap-2" }, codes.map((c, i) => /* @__PURE__ */ React.createElement("span", { key: i, className: "bg-om-card border border-om-line rounded-om-sm px-3 py-1.5 font-mono text-[13px] text-om-ink select-all" }, c))), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: copy, className: "mt-3 text-[12.5px] text-om-accent hover:underline" }, copied ? __("Copied!") : __("Copy all")));
}
function SetupPanel({ secret, qrCodeDataUri }) {
  const form = useForm({ code: "" });
  const [copied, setCopied] = useState(false);
  const submit = (e) => {
    e.preventDefault();
    form.post("/settings/two-factor/confirm");
  };
  const copySecret = () => {
    navigator.clipboard?.writeText(secret).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-6 space-y-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink" }, __("Enable Two-Factor Authentication")), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted mt-1" }, __("Scan the QR code with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator)."))), qrCodeDataUri && /* @__PURE__ */ React.createElement("div", { className: "text-center" }, /* @__PURE__ */ React.createElement("img", { src: qrCodeDataUri, alt: __("2FA QR code"), className: "mx-auto rounded-om-sm border border-om-line" })), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted mb-1" }, __("Can't scan? Enter this key manually:")), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement("code", { className: "flex-1 bg-om-chip border border-om-line rounded-om-sm px-3 py-2 font-mono text-[13px] tracking-widest text-om-ink select-all break-all" }, secret), /* @__PURE__ */ React.createElement(Button, { type: "button", variant: "secondary", onClick: copySecret, className: "whitespace-nowrap" }, copied ? __("Copied!") : __("Copy")))), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "space-y-3 pt-2 border-t border-om-line" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]" }, __("Enter the 6-digit code to confirm")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.code,
      onChange: (e) => form.setData("code", e.target.value.replace(/\D/g, "").slice(0, 6)),
      inputMode: "numeric",
      autoComplete: "one-time-code",
      maxLength: 6,
      autoFocus: true,
      className: `${INPUT_CLASS} text-center text-2xl font-mono tracking-[0.5em]`
    }
  ), form.errors.code && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, form.errors.code)), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", className: "w-full", disabled: form.data.code.length !== 6, loading: form.processing }, form.processing ? __("Verifying\u2026") : __("Enable Two-Factor Authentication"))));
}
function ManagePanel() {
  return /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5 flex items-center gap-3" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-3 rounded-full bg-om-running" }), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink" }, __("Two-factor authentication is on")), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted" }, __("Your account requires an authentication code at login.")))), /* @__PURE__ */ React.createElement(
    PasswordActionCard,
    {
      title: __("Regenerate recovery codes"),
      description: __("Invalidate your old recovery codes and generate a fresh set."),
      action: "/settings/two-factor/recovery-codes",
      submitLabel: __("Regenerate codes"),
      tone: "blue"
    }
  ), /* @__PURE__ */ React.createElement(
    PasswordActionCard,
    {
      title: __("Disable two-factor authentication"),
      description: __("Remove 2FA from your account. You'll only need your password to log in."),
      action: "/settings/two-factor/disable",
      submitLabel: __("Disable 2FA"),
      tone: "red"
    }
  ));
}
function PasswordActionCard({ title, description, action, submitLabel, tone }) {
  const form = useForm({ password: "" });
  const submit = (e) => {
    e.preventDefault();
    form.post(action, { onSuccess: () => form.reset("password") });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("h3", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink" }, title), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted mt-1" }, description), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "mt-3 flex gap-2 items-start" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "password",
      value: form.data.password,
      onChange: (e) => form.setData("password", e.target.value),
      placeholder: __("Current password"),
      autoComplete: "current-password",
      className: INPUT_CLASS
    }
  ), form.errors.password && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, form.errors.password)), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: tone === "red" ? "danger" : "primary",
      disabled: !form.data.password,
      loading: form.processing,
      className: "whitespace-nowrap"
    },
    submitLabel
  )));
}
export {
  TwoFactor as default
};
