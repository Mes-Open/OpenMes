import { useState } from "react";
import { __ } from "../../../lib/i18n";
function RuntimePanel({ runtime }) {
  if (!runtime) return null;
  const { required, alive, seconds_ago, label, command, docker } = runtime;
  const state = !required ? { dot: "bg-slate-400", text: __("Not required (connection inactive)"), tone: "text-om-muted" } : alive ? { dot: "bg-om-running animate-pulse", text: __("Running \u2014 last heartbeat :seconds ago", { seconds: `${seconds_ago ?? "?"}s` }), tone: "text-om-running" } : { dot: "bg-om-blocked", text: __("Not running \u2014 start the runtime below"), tone: "text-om-blocked" };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 p-5 space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("span", { className: `w-3 h-3 rounded-full shrink-0 ${state.dot}` }), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wider" }, label), /* @__PURE__ */ React.createElement("p", { className: `text-sm ${state.tone}` }, state.text))), command && /* @__PURE__ */ React.createElement(CommandBlock, { title: __("Artisan (foreground)"), value: command }), docker && /* @__PURE__ */ React.createElement(CommandBlock, { title: __("Docker (background)"), value: docker }));
}
function CommandBlock({ title, value }) {
  const [copied, setCopied] = useState(false);
  const copy = () => {
    navigator.clipboard?.writeText(value).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  };
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-1" }, /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, title), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: copy,
      className: "text-xs text-om-accent hover:text-om-accent"
    },
    copied ? __("Copied!") : __("Copy")
  )), /* @__PURE__ */ React.createElement("pre", { className: "bg-om-ink text-gray-100 text-xs font-mono rounded-om-sm p-3 overflow-x-auto" }, value));
}
export {
  RuntimePanel as default
};
