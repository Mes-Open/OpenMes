const STATUS_DOT = {
  green: "bg-om-running",
  yellow: "bg-om-downtime",
  red: "bg-om-blocked",
  slate: "bg-slate-400"
};
function StatusDot({ color, pulse = false, size = "w-2.5 h-2.5" }) {
  const cls = STATUS_DOT[color] ?? "bg-slate-400";
  return /* @__PURE__ */ React.createElement("span", { className: `${size} rounded-full shrink-0 ${cls} ${pulse ? "animate-pulse" : ""}` });
}
function StatCard({ value, label, capitalize = false }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 p-4 text-center" }, /* @__PURE__ */ React.createElement("p", { className: `text-2xl font-bold text-om-ink ${capitalize ? "capitalize" : ""}` }, value), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-1" }, label));
}
function Section({ title, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 p-5 space-y-4" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wider" }, title), children);
}
function Field({ label, required, error, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), children, error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
export {
  Field,
  STATUS_DOT,
  Section,
  StatCard,
  StatusDot
};
