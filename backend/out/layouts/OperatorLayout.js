import { Link, router, usePage } from "@inertiajs/react";
import { OnlineDot } from "@openmes/ui";
function OperatorLayout({ children }) {
  const { auth, line, selectedWorkstation, csrf_token } = usePage().props;
  const path = typeof window !== "undefined" ? window.location.pathname : "";
  const isActive = (prefix) => path === prefix || path.startsWith(prefix);
  return /* @__PURE__ */ React.createElement("div", { className: "min-h-screen flex flex-col bg-om-bg font-sans" }, /* @__PURE__ */ React.createElement("header", { className: "shrink-0 bg-om-card border-b border-om-line" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-4 px-4 h-16" }, /* @__PURE__ */ React.createElement(Link, { href: "/operator/select-line", className: "flex items-center shrink-0" }, /* @__PURE__ */ React.createElement("img", { src: "/logo_open_mes.png", alt: "OpenMES", className: "h-8 w-auto" })), line && /* @__PURE__ */ React.createElement("div", { className: "min-w-0 border-l border-om-line pl-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-[15px] font-semibold leading-tight text-om-ink truncate" }, line.name), selectedWorkstation && /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint truncate" }, selectedWorkstation.name)), /* @__PURE__ */ React.createElement(OnlineDot, { label: "ONLINE", pulse: true, className: "hidden md:inline-flex shrink-0" }), line && /* @__PURE__ */ React.createElement("nav", { className: "ml-auto flex items-center gap-2" }, /* @__PURE__ */ React.createElement(TopLink, { href: "/operator/queue", active: isActive("/operator/queue") || isActive("/operator/work-order") }, "Queue"), /* @__PURE__ */ React.createElement(TopLink, { href: "/operator/workstation", active: isActive("/operator/workstation") }, "Workstation"), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/operator/select-line",
      className: "px-3 py-2.5 rounded-om-sm text-sm font-medium text-om-muted border border-om-line hover:bg-om-chip hover:text-om-ink transition-colors"
    },
    "Switch Line"
  )), /* @__PURE__ */ React.createElement("div", { className: `flex items-center gap-3 ${line ? "" : "ml-auto"}` }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "w-8 h-8 rounded-full bg-om-ink flex items-center justify-center text-om-on-ink text-sm font-semibold" }, auth?.user?.initial ?? "?"), /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-ink hidden md:block" }, auth?.user?.name)), /* @__PURE__ */ React.createElement("form", { action: "/logout", method: "POST" }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrf_token }), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "submit",
      title: "Logout",
      className: "p-2.5 rounded-om-sm text-om-faint hover:text-om-blocked hover:bg-om-chip transition-colors"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement(
      "path",
      {
        strokeLinecap: "round",
        strokeLinejoin: "round",
        strokeWidth: "2",
        d: "M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
      }
    ))
  ))))), /* @__PURE__ */ React.createElement("main", { className: "flex-1 overflow-auto p-4 md:p-6" }, /* @__PURE__ */ React.createElement(FlashMessages, null), children));
}
function TopLink({ href, active, children }) {
  return /* @__PURE__ */ React.createElement(
    Link,
    {
      href,
      className: `px-4 py-2.5 rounded-om-sm text-sm font-semibold transition-colors ${active ? "bg-om-ink text-om-on-ink" : "text-om-muted hover:bg-om-chip hover:text-om-ink"}`
    },
    children
  );
}
function FlashMessages() {
  const { flash } = usePage().props;
  if (!flash) return null;
  const items = [
    ["success", "bg-om-running-bg border-om-running/30 text-om-running"],
    ["error", "bg-om-blocked-bg border-om-blocked/30 text-om-blocked"],
    ["warning", "bg-om-downtime-bg border-om-downtime/30 text-om-downtime"],
    ["info", "bg-om-chip border-om-line text-om-muted"]
  ].filter(([k]) => flash[k]);
  if (!items.length) return null;
  return /* @__PURE__ */ React.createElement("div", { className: "mb-4 space-y-2 max-w-3xl mx-auto" }, items.map(([k, cls]) => /* @__PURE__ */ React.createElement("div", { key: k, className: `p-3 rounded-om-sm border text-sm ${cls}` }, flash[k])));
}
export {
  OperatorLayout as default
};
