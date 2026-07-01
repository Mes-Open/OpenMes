import { useState, useEffect, useRef, useCallback } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { DatePicker, Dropdown } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import LiveRefresh from "../../../components/LiveRefresh";
import { formatDate, formatNumber } from "../../../lib/i18n";
const SHIFT_COLORS = {
  1: { bg: "bg-sky-300", label: "S1", hours: "00-06" },
  2: { bg: "bg-om-downtime/60", label: "S2", hours: "06-12" },
  3: { bg: "bg-orange-400", label: "S3", hours: "12-18" },
  4: { bg: "bg-rose-400", label: "S4", hours: "18-24" }
};
const WO_COLORS = {
  PENDING: "bg-om-line2 border-om-line text-om-muted",
  ACCEPTED: "bg-om-chip border-om-accent text-om-accent",
  IN_PROGRESS: "bg-om-downtime-bg border-om-downtime text-om-downtime",
  BLOCKED: "bg-om-blocked-bg border-om-blocked text-om-blocked",
  PAUSED: "bg-orange-200 border-orange-400 text-om-downtime",
  DONE: "bg-om-running-bg border-om-running text-om-running"
};
const STATUS_LABELS = {
  PENDING: "Pending",
  ACCEPTED: "Accepted",
  IN_PROGRESS: "In Progress",
  BLOCKED: "Blocked",
  PAUSED: "Paused",
  DONE: "Done",
  REJECTED: "Rejected",
  CANCELLED: "Cancelled"
};
const PRIORITY_META = {
  5: { label: "Urgent", color: "text-om-blocked", bg: "bg-om-blocked-bg border-om-line", icon: "\u26A0" },
  4: { label: "High", color: "text-om-accent", bg: "bg-orange-50 border-orange-200", icon: "\u25B2" },
  3: { label: "Medium", color: "text-om-downtime", bg: "bg-om-downtime-bg border-om-line", icon: "\u25CF" },
  2: { label: "Low", color: "text-om-accent", bg: "bg-om-chip border-om-line", icon: "\u25BC" },
  1: { label: "Lowest", color: "text-om-muted", bg: "bg-om-panel border-om-line2", icon: "\u2014" }
};
const PX_PER_MINUTE = 2;
const HOUR_PX = 60 * PX_PER_MINUTE;
const TOTAL_WIDTH = 24 * 60 * PX_PER_MINUTE;
function getCsrf() {
  return document.querySelector("meta[name=csrf-token]")?.content ?? "";
}
async function apiCall(url, method, body) {
  const r = await fetch(url, {
    method,
    headers: {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "X-CSRF-TOKEN": getCsrf(),
      "X-Requested-With": "XMLHttpRequest"
    },
    body: JSON.stringify(body)
  });
  return r;
}
function loadPercClass(pct) {
  if (pct > 100) return "text-om-blocked";
  if (pct > 80) return "text-om-accent";
  return "text-om-running";
}
function loadBarClass(pct) {
  if (pct > 100) return "bg-om-blocked";
  if (pct > 80) return "bg-orange-500";
  return "bg-om-running";
}
function Planner() {
  const {
    data = [],
    lines = [],
    allLines = [],
    shifts = [],
    viewMode = "weekly",
    shiftsPerDay = 1,
    slotMinutes = 15,
    horizonWeeks = 4,
    showWeekends = true,
    startDate,
    rangeStart,
    rangeEnd,
    navPrev,
    navNext,
    backlogOrders = [],
    maintenanceEvents = [],
    realtimeMode = "polling"
  } = usePage().props;
  const lineId = new URLSearchParams(window.location.search).get("line_id") ?? "";
  const nav = useCallback((params) => {
    router.get("/admin/schedule", params, { preserveState: false });
  }, []);
  const goTo = (startDateStr) => nav({ start_date: startDateStr, view_mode: viewMode, line_id: lineId });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);
  const toastTimeout = useRef(null);
  const [backlogCollapsed, setBacklogCollapsed] = useState(false);
  const [backlogSearch, setBacklogSearch] = useState("");
  const [backlogPriority, setBacklogPriority] = useState("");
  const [backlogLine, setBacklogLine] = useState("");
  const [backlogSort, setBacklogSort] = useState("due_date");
  const [assignPopup, setAssignPopup] = useState(false);
  const [assignTarget, setAssignTarget] = useState(null);
  const [assignSearch, setAssignSearch] = useState("");
  const [selected, setSelected] = useState(null);
  const dragOrderId = useRef(null);
  const dragOrderNo = useRef(null);
  const [dragOverCell, setDragOverCell] = useState(null);
  const [trackingId, setTrackingId] = useState(null);
  const [trackingData, setTrackingData] = useState(null);
  const trackingTimer = useRef(null);
  const [pollingActive, setPollingActive] = useState(false);
  const [tooltip, setTooltip] = useState(null);
  const [tipPos, setTipPos] = useState({ x: 0, y: 0 });
  const backlogItems = backlogOrders.map((wo) => ({
    id: wo.id,
    order_no: wo.order_no,
    product: wo.product_name ?? "-",
    qty: wo.planned_qty,
    priority: wo.priority,
    status: STATUS_LABELS[wo.status] ?? wo.status,
    due_date: wo.due_date ?? "-",
    line_id: wo.line_id
  }));
  const showToast = useCallback((msg, type = "success") => {
    setToast({ msg, type });
    clearTimeout(toastTimeout.current);
    toastTimeout.current = setTimeout(() => setToast(null), 3e3);
  }, []);
  const saveOrder = useCallback(async (orderId, body) => {
    setSaving(true);
    try {
      const r = await apiCall(`/admin/schedule/${orderId}`, "PUT", body);
      const json = await r.json();
      if (json.success) {
        showToast(json.message ?? "Saved");
        return json;
      } else {
        showToast(json.message ?? "Error saving", "error");
        return null;
      }
    } catch {
      showToast("Connection error", "error");
      return null;
    } finally {
      setSaving(false);
    }
  }, [showToast]);
  const refreshContent = useCallback(() => {
    router.reload({ preserveState: false });
  }, []);
  const openAssign = (lineId2, date, shift, weekNumber) => {
    setAssignTarget({ lineId: lineId2, date, shift, weekNumber });
    setAssignSearch("");
    setAssignPopup(true);
  };
  const assignOrder = async (orderId) => {
    const { lineId: lid, date, shift, weekNumber } = assignTarget;
    const body = { line_id: lid };
    if (date) {
      body.due_date = date;
      const shiftHours = { 1: 0, 2: 6, 3: 12, 4: 18 };
      const startH = shiftHours[shift] ?? 8;
      body.planned_start_at = `${date}T${String(startH).padStart(2, "0")}:00:00`;
      body.planned_end_at = `${date}T${String(startH + 6).padStart(2, "0")}:00:00`;
    }
    if (weekNumber) body.week_number = weekNumber;
    if (shift) body.shift_number = shift;
    const result = await saveOrder(orderId, body);
    if (result) {
      setAssignPopup(false);
      refreshContent();
    }
  };
  const unassignOrder = async (orderId) => {
    if (!confirm("Remove this order from schedule?")) return;
    const result = await saveOrder(orderId, {
      line_id: "",
      due_date: "",
      week_number: "",
      shift_number: "",
      end_date: "",
      end_shift_number: "",
      planned_start_at: "",
      planned_end_at: ""
    });
    if (result) refreshContent();
  };
  const onDragStart = (e, id, no) => {
    dragOrderId.current = id;
    dragOrderNo.current = no || (backlogItems.find((i) => i.id == id)?.order_no ?? "WO");
    e.dataTransfer.effectAllowed = "move";
    e.dataTransfer.setData("text/plain", id);
    e.target.style.opacity = "0.4";
  };
  const onDragEnd = (e) => {
    dragOrderId.current = null;
    dragOrderNo.current = null;
    setDragOverCell(null);
    e.target.style.opacity = "";
  };
  const onDrop = async (e, lid, date, shift, weekNumber) => {
    e.preventDefault();
    const id = e.dataTransfer.getData("text/plain") || dragOrderId.current;
    setDragOverCell(null);
    dragOrderId.current = null;
    dragOrderNo.current = null;
    if (!id) return;
    setAssignTarget({ lineId: lid, date, shift, weekNumber });
    const body = { line_id: lid };
    if (date) {
      body.due_date = date;
    }
    if (weekNumber) body.week_number = weekNumber;
    if (shift) body.shift_number = shift;
    const result = await saveOrder(id, body);
    if (result) refreshContent();
  };
  const selectOrder = (wo, cellDate, shift) => {
    if (dragOrderId.current) return;
    if (selected?.id === wo.id) {
      setSelected(null);
      return;
    }
    setSelected({
      id: wo.id,
      order_no: wo.order_no,
      line_id: wo.line_id,
      dueDate: wo.due_date ?? "",
      shift: wo.shift_number ?? "",
      endDate: wo.end_date ?? "",
      endShift: wo.end_shift_number ?? "",
      url: `/admin/work-orders/${wo.id}`
    });
  };
  const saveSelectedDates = async () => {
    if (!selected) return;
    setSaving(true);
    try {
      await saveOrder(selected.id, {
        due_date: selected.dueDate,
        shift_number: selected.shift ? parseInt(selected.shift) : ""
      });
      const spanBody = selected.endDate && selected.endShift ? { end_date: selected.endDate, end_shift_number: parseInt(selected.endShift) } : { end_date: null, end_shift_number: null };
      const r = await apiCall(`/admin/schedule/${selected.id}/resize`, "PUT", spanBody);
      const json = await r.json();
      if (json.success) showToast(json.message ?? "Saved");
      else showToast(json.message ?? "Error", "error");
      refreshContent();
      setSelected(null);
    } catch {
      showToast("Connection error", "error");
    } finally {
      setSaving(false);
    }
  };
  const startTracking = (id) => {
    stopTracking();
    setTrackingId(id);
    setTrackingData(null);
    fetchTracking(id);
    trackingTimer.current = setInterval(() => fetchTracking(id), 5e3);
  };
  const stopTracking = () => {
    clearInterval(trackingTimer.current);
    setTrackingId(null);
    setTrackingData(null);
  };
  const fetchTracking = async (id) => {
    try {
      const r = await fetch(`/admin/schedule/check-updates?track=${id}`, {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
      });
      if (!r.ok) return;
      const d = await r.json();
      if (d.tracked_order) setTrackingData(d.tracked_order);
    } catch {
    }
  };
  const pendingRefresh = useRef(false);
  const onWorkOrdersChanged = useCallback(() => {
    if (saving || dragOrderId.current) {
      pendingRefresh.current = true;
      return;
    }
    refreshContent();
  }, [saving, refreshContent]);
  useEffect(() => {
    if (!saving && pendingRefresh.current) {
      pendingRefresh.current = false;
      refreshContent();
    }
  }, [saving, refreshContent]);
  useEffect(() => {
    setPollingActive(realtimeMode !== "off");
  }, [realtimeMode]);
  const filteredBacklog = backlogItems.filter((i) => !backlogSearch || (i.order_no + " " + i.product).toLowerCase().includes(backlogSearch.toLowerCase())).filter((i) => !backlogPriority || String(i.priority) === String(backlogPriority)).filter((i) => !backlogLine || String(i.line_id) === String(backlogLine) || !i.line_id).sort((a, b) => {
    if (backlogSort === "priority") return (b.priority ?? 0) - (a.priority ?? 0);
    if (backlogSort === "planned_qty") return (b.qty ?? 0) - (a.qty ?? 0);
    const da = a.due_date === "-" ? "9999" : a.due_date;
    const db = b.due_date === "-" ? "9999" : b.due_date;
    return da.localeCompare(db);
  });
  const groupedBacklog = {};
  filteredBacklog.forEach((i) => {
    const p = i.priority ?? 3;
    if (!groupedBacklog[p]) groupedBacklog[p] = [];
    groupedBacklog[p].push(i);
  });
  const priorityKeys = Object.keys(groupedBacklog).map(Number).sort((a, b) => b - a);
  const rangeStartFmt = rangeStart ? formatDate(new Date(rangeStart), { day: "2-digit", month: "2-digit" }) : "";
  const rangeEndFmt = rangeEnd ? formatDate(new Date(rangeEnd), { day: "2-digit", month: "2-digit", year: "numeric" }) : "";
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: "Production Planner" }), /* @__PURE__ */ React.createElement(
    LiveRefresh,
    {
      pollUrl: "/admin/schedule/check-updates",
      shape: "work_orders_all",
      instant: true,
      enabled: realtimeMode !== "off",
      onRefresh: onWorkOrdersChanged
    }
  ), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om shadow-sm border border-om-line2 px-4 py-2.5 mb-4 flex flex-wrap items-center gap-3" }, /* @__PURE__ */ React.createElement("button", { onClick: () => goTo(navPrev), className: "p-2 rounded-om-sm hover:bg-om-chip text-om-muted transition", title: "Previous" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2.5", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M15 19l-7-7 7-7" }))), /* @__PURE__ */ React.createElement("span", { className: "font-semibold text-sm text-om-muted" }, rangeStartFmt, " \u2013 ", rangeEndFmt), /* @__PURE__ */ React.createElement("button", { onClick: () => goTo(navNext), className: "p-2 rounded-om-sm hover:bg-om-chip text-om-muted transition", title: "Next" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2.5", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M9 5l7 7-7 7" }))), /* @__PURE__ */ React.createElement("div", { className: "h-6 border-l border-om-line mx-1" }), /* @__PURE__ */ React.createElement("div", { className: "flex bg-om-chip rounded-om-sm p-0.5" }, [["weekly", "Weekly"], ["daily", "Daily"], ["hourly", "Hourly"], ["monthly", "Monthly"]].map(([m, l]) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key: m,
      onClick: () => nav({ start_date: startDate, view_mode: m, line_id: lineId }),
      className: `px-3 py-1 text-xs font-medium rounded-md transition ${viewMode === m ? "bg-om-card text-om-ink shadow-sm" : "text-om-muted hover:text-om-ink"}`
    },
    l
  )), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/schedule/employees?date=${startDate}`,
      className: "px-3 py-1 text-xs font-medium rounded-md transition text-om-downtime hover:text-om-downtime",
      title: "Employee day planner \u2014 tachograph view"
    },
    "Employees"
  )), /* @__PURE__ */ React.createElement("div", { className: "h-6 border-l border-om-line mx-1" }), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: lineId == null ? "" : String(lineId),
      onChange: (v) => nav({ start_date: startDate, view_mode: viewMode, line_id: v }),
      options: [
        { value: "", label: "All Lines" },
        ...allLines.map((l) => ({ value: String(l.id), label: l.name }))
      ]
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "flex-1" }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 text-[10px] text-om-muted" }, Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => /* @__PURE__ */ React.createElement("span", { key: s, className: "inline-flex items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: `w-3 h-2.5 rounded-sm ${SHIFT_COLORS[s]?.bg ?? "bg-om-line"}` }), SHIFT_COLORS[s]?.label ?? `S${s}`))), /* @__PURE__ */ React.createElement("div", { className: "h-6 border-l border-om-line mx-1" }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1.5", title: pollingActive ? "Auto-refresh: polling every 10s" : "Auto-refresh disabled" }, /* @__PURE__ */ React.createElement("span", { className: "relative flex h-2.5 w-2.5" }, pollingActive && /* @__PURE__ */ React.createElement("span", { className: "animate-ping absolute inline-flex h-full w-full rounded-full bg-om-running opacity-75" }), /* @__PURE__ */ React.createElement("span", { className: `relative inline-flex rounded-full h-2.5 w-2.5 ${pollingActive ? "bg-om-running" : "bg-om-faintest"}` })), /* @__PURE__ */ React.createElement("span", { className: "text-[10px] text-om-faint" }, pollingActive ? "Live" : "Off")), /* @__PURE__ */ React.createElement("div", { className: "h-6 border-l border-om-line mx-1" }), /* @__PURE__ */ React.createElement("button", { onClick: () => nav({ view_mode: viewMode, line_id: lineId }), className: "px-3 py-1.5 text-xs font-medium rounded-om-sm bg-om-chip text-om-accent hover:bg-om-chip transition" }, "Today")), /* @__PURE__ */ React.createElement("div", { className: "flex gap-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0" }, viewMode === "weekly" && /* @__PURE__ */ React.createElement(
    WeeklyView,
    {
      data,
      shiftsPerDay,
      showWeekends,
      maintenanceEvents,
      dragOverCell,
      dragOrderNo,
      selectedId: selected?.id,
      onDragStart,
      onDragEnd,
      onDragOver: (e, cellId) => {
        e.preventDefault();
        setDragOverCell(cellId);
      },
      onDragLeave: (e, cellId) => {
        if (dragOverCell === cellId) setDragOverCell(null);
      },
      onDrop,
      onSelectOrder: selectOrder,
      onOpenAssign: openAssign,
      onUnassign: unassignOrder,
      onShowTip: (e, d) => {
        const r = e.target.getBoundingClientRect();
        setTipPos({ x: r.left + window.scrollX, y: r.bottom + window.scrollY + 8 });
        setTooltip(d);
      },
      onHideTip: () => setTooltip(null)
    }
  ), viewMode === "daily" && /* @__PURE__ */ React.createElement(
    DailyView,
    {
      data,
      lines,
      maintenanceEvents,
      onUnassign: unassignOrder
    }
  ), viewMode === "hourly" && /* @__PURE__ */ React.createElement(
    HourlyView,
    {
      data,
      slotMinutes,
      startDate,
      shiftsPerDay,
      maintenanceEvents,
      onUnassign: unassignOrder,
      onRefresh: refreshContent,
      showToast
    }
  ), viewMode === "monthly" && /* @__PURE__ */ React.createElement(MonthlyView, { data, onUnassign: unassignOrder })), /* @__PURE__ */ React.createElement("div", { className: `shrink-0 transition-all duration-300 ${backlogCollapsed ? "w-10" : "w-[380px]"}` }, backlogCollapsed ? /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => setBacklogCollapsed(false),
      className: "w-10 h-full bg-om-card border border-om-line2 rounded-om shadow-sm flex flex-col items-center pt-4 hover:bg-om-bg transition"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4 text-om-faint", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M15 19l-7-7 7-7" })),
    /* @__PURE__ */ React.createElement("span", { className: "mt-2 text-[10px] font-medium text-om-muted", style: { writingMode: "vertical-lr" } }, __("Backlog"), " (", backlogOrders.length, ")")
  ) : /* @__PURE__ */ React.createElement(
    BacklogPanel,
    {
      backlogOrders,
      backlogItems,
      allLines,
      search: backlogSearch,
      onSearch: setBacklogSearch,
      filterLine: backlogLine,
      onFilterLine: setBacklogLine,
      filterPriority: backlogPriority,
      onFilterPriority: setBacklogPriority,
      sort: backlogSort,
      onSort: setBacklogSort,
      groupedBacklog,
      priorityKeys,
      dragOverCell,
      onDragStart,
      onDragEnd,
      onCollapse: () => setBacklogCollapsed(true)
    }
  ))), assignPopup && /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "fixed inset-0 z-50 flex items-center justify-center bg-black/40",
      onClick: (e) => {
        if (e.target === e.currentTarget) setAssignPopup(false);
      },
      onKeyDown: (e) => e.key === "Escape" && setAssignPopup(false)
    },
    /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om shadow-2xl w-[420px] max-h-[70vh] flex flex-col border border-om-line2" }, /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line2 flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-bold text-om-ink" }, "Assign order to shift"), /* @__PURE__ */ React.createElement("p", { className: "text-[10px] text-om-faint mt-0.5" }, "Select an unassigned order to place in this slot")), /* @__PURE__ */ React.createElement("button", { onClick: () => setAssignPopup(false), className: "p-1 rounded hover:bg-om-chip text-om-faint" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M6 18L18 6M6 6l12 12" })))), /* @__PURE__ */ React.createElement("div", { className: "px-4 py-2 border-b border-om-line2" }, /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "text",
        value: assignSearch,
        onChange: (e) => setAssignSearch(e.target.value),
        placeholder: "Search by order number or product...",
        className: "w-full text-xs border-om-line rounded-om-sm py-1.5 px-2.5 placeholder-om-faint",
        autoFocus: true
      }
    )), /* @__PURE__ */ React.createElement("div", { className: "flex-1 overflow-y-auto px-4 py-2 space-y-1.5" }, backlogItems.filter((i) => !assignSearch || (i.order_no + " " + i.product).toLowerCase().includes(assignSearch.toLowerCase())).map((item) => /* @__PURE__ */ React.createElement(
      "button",
      {
        key: item.id,
        onClick: () => assignOrder(item.id),
        className: "w-full text-left p-2.5 rounded-om-sm border border-om-line2 hover:border-om-accent hover:bg-om-chip transition"
      },
      /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-xs font-bold text-om-ink" }, item.order_no), /* @__PURE__ */ React.createElement("span", { className: "text-[10px] px-1.5 py-0.5 rounded bg-om-chip text-om-muted" }, item.status)),
      /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 mt-1 text-[10px] text-om-muted" }, /* @__PURE__ */ React.createElement("span", null, item.product), /* @__PURE__ */ React.createElement("span", null, "\xB7"), /* @__PURE__ */ React.createElement("span", null, item.qty, " pcs"), /* @__PURE__ */ React.createElement("span", null, "\xB7"), /* @__PURE__ */ React.createElement("span", null, "Due: ", item.due_date))
    )), backlogItems.filter((i) => !assignSearch || (i.order_no + " " + i.product).toLowerCase().includes(assignSearch.toLowerCase())).length === 0 && /* @__PURE__ */ React.createElement("div", { className: "text-center py-6 text-xs text-om-faint" }, "No matching orders found")))
  ), selected && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-30", onClick: () => setSelected(null) }), /* @__PURE__ */ React.createElement("div", { className: "fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-om-card rounded-om shadow-2xl border border-om-line2 p-4 w-[480px] max-w-[95vw]" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-bold text-om-ink" }, selected.order_no), /* @__PURE__ */ React.createElement(Link, { href: selected.url, className: "text-[10px] text-om-accent hover:underline" }, "View details \u2192")), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => trackingId === selected.id ? stopTracking() : startTracking(selected.id),
      className: `flex items-center gap-1 px-2 py-1 text-[10px] font-medium rounded-om-sm transition ${trackingId === selected.id ? "bg-om-running-bg text-om-running ring-1 ring-green-400" : "bg-om-chip text-om-muted hover:bg-om-line2"}`
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-3 h-3", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M15 12a3 3 0 11-6 0 3 3 0 016 0z" }), /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" })),
    trackingId === selected.id ? "Tracking" : "Track live"
  ), /* @__PURE__ */ React.createElement("button", { onClick: () => setSelected(null), className: "p-1 rounded hover:bg-om-chip text-om-faint" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M6 18L18 6M6 6l12 12" }))))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-[10px] font-semibold text-om-muted uppercase mb-1" }, "Start date"), /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: selected.dueDate || null,
      onChange: (iso) => setSelected((s) => ({ ...s, dueDate: iso ?? "" })),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-[10px] font-semibold text-om-muted uppercase mb-1" }, "Start shift"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: selected.shift == null ? "" : String(selected.shift),
      onChange: (v) => setSelected((s) => ({ ...s, shift: v })),
      placeholder: "\u2014",
      className: "w-full",
      options: Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => ({ value: String(s), label: `${SHIFT_COLORS[s]?.label ?? `S${s}`} (${SHIFT_COLORS[s]?.hours ?? ""})` }))
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-[10px] font-semibold text-om-muted uppercase mb-1" }, "End date"), /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: selected.endDate || null,
      onChange: (iso) => setSelected((s) => ({ ...s, endDate: iso ?? "" })),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-[10px] font-semibold text-om-muted uppercase mb-1" }, "End shift"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: selected.endShift == null ? "" : String(selected.endShift),
      onChange: (v) => setSelected((s) => ({ ...s, endShift: v })),
      placeholder: "\u2014",
      className: "w-full",
      options: Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => ({ value: String(s), label: `${SHIFT_COLORS[s]?.label ?? `S${s}`} (${SHIFT_COLORS[s]?.hours ?? ""})` }))
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 mt-3" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: saveSelectedDates,
      className: "flex-1 px-3 py-2 text-xs font-semibold rounded-om-sm bg-om-ink text-om-on-ink hover:bg-om-ink-hover transition"
    },
    "Save"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => setSelected(null),
      className: "px-3 py-2 text-xs font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition"
    },
    "Cancel"
  )))), trackingId && trackingData && /* @__PURE__ */ React.createElement("div", { className: "fixed top-4 right-4 z-50 bg-om-card rounded-om shadow-2xl border border-om-line2 w-[320px]" }, /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line2 flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "relative flex h-2.5 w-2.5" }, /* @__PURE__ */ React.createElement("span", { className: "animate-ping absolute inline-flex h-full w-full rounded-full bg-om-running opacity-75" }), /* @__PURE__ */ React.createElement("span", { className: "relative inline-flex rounded-full h-2.5 w-2.5 bg-om-running" })), /* @__PURE__ */ React.createElement("span", { className: "text-xs font-bold text-om-ink" }, trackingData.order_no), /* @__PURE__ */ React.createElement("span", { className: `text-[10px] font-medium px-1.5 py-0.5 rounded ${trackingData.is_overdue ? "bg-om-blocked-bg text-om-blocked" : "bg-om-chip text-om-muted"}` }, trackingData.status)), /* @__PURE__ */ React.createElement("button", { onClick: stopTracking, className: "p-1 rounded hover:bg-om-chip text-om-faint", title: "Stop tracking" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M6 18L18 6M6 6l12 12" })))), /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 space-y-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-1" }, /* @__PURE__ */ React.createElement("span", { className: "text-[10px] font-semibold text-om-muted uppercase" }, "Progress"), /* @__PURE__ */ React.createElement("span", { className: `text-xs font-bold ${trackingData.progress_percent >= 100 ? "text-om-running" : trackingData.is_overdue ? "text-om-blocked" : "text-om-accent"}` }, trackingData.progress_percent, "%")), /* @__PURE__ */ React.createElement("div", { className: "w-full h-3 bg-om-line2 rounded-full overflow-hidden" }, /* @__PURE__ */ React.createElement(
    "div",
    {
      className: `h-full rounded-full transition-all duration-500 ${trackingData.progress_percent >= 100 ? "bg-om-running" : trackingData.is_overdue ? "bg-om-blocked" : "bg-om-ink"}`,
      style: { width: `${Math.min(100, trackingData.progress_percent || 0)}%` }
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-panel rounded-om-sm p-2 text-center" }, /* @__PURE__ */ React.createElement("div", { className: "text-[10px] text-om-faint uppercase" }, "Produced"), /* @__PURE__ */ React.createElement("div", { className: "text-lg font-bold text-om-ink" }, trackingData.produced_qty)), /* @__PURE__ */ React.createElement("div", { className: "bg-om-panel rounded-om-sm p-2 text-center" }, /* @__PURE__ */ React.createElement("div", { className: "text-[10px] text-om-faint uppercase" }, "Planned"), /* @__PURE__ */ React.createElement("div", { className: "text-lg font-bold text-om-ink" }, trackingData.planned_qty))), /* @__PURE__ */ React.createElement("div", { className: "space-y-1.5 text-xs text-om-muted" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", null, "Line"), /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, trackingData.line)), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", null, "Product"), /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, trackingData.product)), trackingData.current_step && /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", null, "Current step"), /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, trackingData.current_step.name))), trackingData.is_overdue && /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 px-3 py-2 bg-om-blocked-bg rounded-om-sm text-xs text-om-blocked" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4 shrink-0", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" })), "This order is overdue!")), /* @__PURE__ */ React.createElement("div", { className: "px-4 py-2 border-t border-om-line2 text-[10px] text-om-faint text-center" }, "Auto-refreshing every 5s")), toast && /* @__PURE__ */ React.createElement("div", { className: `fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-om shadow-lg text-sm font-medium max-w-sm ${toast.type === "success" ? "bg-om-running text-white" : "bg-om-blocked text-white"}` }, toast.type === "success" ? /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 shrink-0", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M5 13l4 4L19 7" })) : /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 shrink-0", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M6 18L18 6M6 6l12 12" })), toast.msg), saving && /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-40 bg-om-card/10 pointer-events-none flex items-center justify-center" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-lg px-4 py-2 text-sm text-om-muted flex items-center gap-2" }, /* @__PURE__ */ React.createElement("svg", { className: "animate-spin h-4 w-4 text-om-accent", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("circle", { className: "opacity-25", cx: "12", cy: "12", r: "10", stroke: "currentColor", strokeWidth: "4", fill: "none" }), /* @__PURE__ */ React.createElement("path", { className: "opacity-75", fill: "currentColor", d: "M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" })), "Saving...")), tooltip && /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "fixed z-40 bg-om-ink text-om-on-ink rounded-om-sm shadow-xl px-3 py-2 text-xs pointer-events-none max-w-xs",
      style: { left: tipPos.x, top: tipPos.y }
    },
    /* @__PURE__ */ React.createElement("div", { className: "font-bold mb-1" }, tooltip.order_no),
    /* @__PURE__ */ React.createElement("div", { className: "opacity-80" }, tooltip.product),
    /* @__PURE__ */ React.createElement("div", { className: "opacity-80" }, "Qty: ", tooltip.qty),
    /* @__PURE__ */ React.createElement("div", { className: "opacity-80" }, "Status: ", tooltip.status)
  )));
}
Planner.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function WeeklyView({
  data,
  shiftsPerDay,
  showWeekends,
  maintenanceEvents,
  dragOverCell,
  dragOrderNo,
  selectedId,
  onDragStart,
  onDragEnd,
  onDragOver,
  onDragLeave,
  onDrop,
  onSelectOrder,
  onOpenAssign,
  onUnassign,
  onShowTip,
  onHideTip
}) {
  const daysInWeek = showWeekends ? 7 : 5;
  const today = (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
  return /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, data.map((period, idx) => {
    const isOverloaded = period.total_load_percent > 100;
    const weekStart = period.start;
    const isCurrentWeek = (() => {
      const now = /* @__PURE__ */ new Date();
      const ws2 = new Date(period.start);
      const we = new Date(period.end);
      return now >= ws2 && now <= we;
    })();
    const prevYear = idx > 0 ? new Date(data[idx - 1].start).getFullYear() : null;
    const thisYear = new Date(period.start).getFullYear();
    const dayHeaders = [];
    const ws = new Date(period.start);
    for (let d = 0; d < daysInWeek; d++) {
      const cur = new Date(ws);
      cur.setDate(ws.getDate() + d);
      dayHeaders.push({
        date: cur.toISOString().slice(0, 10),
        dayLabel: formatDate(cur, { weekday: "short" }),
        dayNum: formatDate(cur, { day: "2-digit", month: "2-digit" }),
        isToday: cur.toISOString().slice(0, 10) === today,
        isWeekend: cur.getDay() === 0 || cur.getDay() === 6
      });
    }
    return /* @__PURE__ */ React.createElement("div", { key: period.number }, prevYear !== null && thisYear !== prevYear && /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-4 py-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1 border-t-2 border-om-line" }), /* @__PURE__ */ React.createElement("span", { className: "text-lg font-black text-om-muted tracking-wider" }, thisYear), /* @__PURE__ */ React.createElement("div", { className: "flex-1 border-t-2 border-om-line" })), /* @__PURE__ */ React.createElement("div", { className: `bg-om-card rounded-om border-2 overflow-hidden shadow-sm ${isOverloaded ? "border-om-blocked" : isCurrentWeek ? "border-om-accent" : "border-om-line2"}` }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between px-4 py-2 bg-om-panel border-b border-om-line2" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("span", { className: "text-lg font-black text-om-ink" }, "wk. ", period.number), /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-muted" }, formatDate(new Date(period.start), { day: "2-digit", month: "2-digit" }), "\u2013", formatDate(new Date(period.end), { day: "2-digit", month: "2-digit" })), /* @__PURE__ */ React.createElement("div", { className: "flex gap-0.5" }, Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => /* @__PURE__ */ React.createElement("div", { key: s, className: `w-5 h-3 rounded-sm ${SHIFT_COLORS[s]?.bg ?? "bg-om-line"} flex items-center justify-center text-[7px] font-bold text-white/80` }, SHIFT_COLORS[s]?.label ?? `S${s}`)))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-4 text-xs" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "orders: ", /* @__PURE__ */ React.createElement("strong", { className: "text-om-ink" }, period.total_orders)), /* @__PURE__ */ React.createElement("span", null, "load: ", /* @__PURE__ */ React.createElement("strong", { className: loadPercClass(period.total_load_percent) }, period.total_load_percent, "%")), /* @__PURE__ */ React.createElement("div", { className: "w-24 h-2 bg-om-line2 rounded-full overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: `h-full rounded-full ${loadBarClass(period.total_load_percent)}`, style: { width: `${Math.min(period.total_load_percent, 100)}%` } })))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("table", { className: "w-full border-collapse table-fixed" }, /* @__PURE__ */ React.createElement("colgroup", null, /* @__PURE__ */ React.createElement("col", { style: { width: "100px" } }), dayHeaders.map((_, i) => /* @__PURE__ */ React.createElement("col", { key: i, style: { width: `${100 / daysInWeek}%` } }))), /* @__PURE__ */ React.createElement("thead", null, /* @__PURE__ */ React.createElement("tr", null, /* @__PURE__ */ React.createElement("th", { className: "p-1.5 text-left text-[10px] font-semibold text-om-faint uppercase border-r border-om-line2" }, "Line / Shift"), dayHeaders.map((dh) => /* @__PURE__ */ React.createElement("th", { key: dh.date, className: `p-1 text-center border-r border-om-line2 ${dh.isToday ? "bg-om-chip" : ""} ${dh.isWeekend ? "bg-om-panel/50" : ""}` }, /* @__PURE__ */ React.createElement("div", { className: "text-[10px] text-om-faint uppercase" }, dh.dayLabel), /* @__PURE__ */ React.createElement("div", { className: `text-xs font-bold ${dh.isToday ? "text-om-accent" : "text-om-muted"}` }, dh.dayNum), dh.isToday && /* @__PURE__ */ React.createElement("div", { className: "h-0.5 bg-om-ink rounded-full mt-0.5 mx-auto w-8" }))))), /* @__PURE__ */ React.createElement("tbody", null, period.lines.map((lineData) => /* @__PURE__ */ React.createElement(
      WeekLineRows,
      {
        key: lineData.line.id,
        lineData,
        dayHeaders,
        shiftsPerDay,
        weekNumber: period.number,
        maintenanceEvents,
        dragOverCell,
        dragOrderNo,
        selectedId,
        onDragStart,
        onDragEnd,
        onDragOver,
        onDragLeave,
        onDrop,
        onSelectOrder,
        onOpenAssign,
        onUnassign,
        onShowTip,
        onHideTip
      }
    )))))));
  }));
}
function WeekLineRows({
  lineData,
  dayHeaders,
  shiftsPerDay,
  weekNumber,
  maintenanceEvents,
  dragOverCell,
  dragOrderNo,
  selectedId,
  onDragStart,
  onDragEnd,
  onDragOver,
  onDragLeave,
  onDrop,
  onSelectOrder,
  onOpenAssign,
  onUnassign,
  onShowTip,
  onHideTip
}) {
  const { line, grid = {}, spans = {}, load_percent } = lineData;
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("tr", { className: "bg-om-panel/80" }, /* @__PURE__ */ React.createElement("td", { colSpan: dayHeaders.length + 1, className: "px-2 py-1" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-xs font-bold text-om-muted" }, line.name), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-[10px] text-om-faint" }, "load:"), /* @__PURE__ */ React.createElement("div", { className: "w-16 h-1.5 bg-om-line2 rounded-full overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: `h-full rounded-full ${loadBarClass(load_percent)}`, style: { width: `${Math.min(load_percent, 100)}%` } })), /* @__PURE__ */ React.createElement("span", { className: `text-[10px] font-semibold ${loadPercClass(load_percent)}` }, load_percent, "%"))))), Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => /* @__PURE__ */ React.createElement("tr", { key: s, className: "border-b border-om-line2 hover:bg-om-bg/30" }, /* @__PURE__ */ React.createElement("td", { className: "px-2 py-2 text-[10px] font-medium border-r border-om-line2 whitespace-nowrap" }, /* @__PURE__ */ React.createElement("span", { className: "inline-flex items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: `w-2.5 h-2 rounded-sm ${SHIFT_COLORS[s]?.bg ?? "bg-om-line"}` }), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, SHIFT_COLORS[s]?.label ?? `S${s}`), /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, SHIFT_COLORS[s]?.hours ?? ""))), dayHeaders.map((dh) => {
    const gridKey = `${dh.date}-${s}`;
    const slotOrder = grid[gridKey];
    const spanInfo = spans[gridKey];
    const isCont = spanInfo?.type === "cont";
    if (isCont) return null;
    const rowspan = spanInfo && ["start", "day-start"].includes(spanInfo.type) ? spanInfo.rowspan : 1;
    const cellId = `cell-${line.id}-${dh.date}-${s}`;
    const isOrderObj = slotOrder && slotOrder !== "__span__" && typeof slotOrder === "object";
    const isDragOver = dragOverCell === cellId;
    const cellTd = /* @__PURE__ */ React.createElement(
      "td",
      {
        key: dh.date,
        className: `p-0.5 relative border-r border-gray-50 transition-colors ${dh.isToday ? "bg-om-chip" : ""} ${dh.isWeekend ? "bg-om-panel/30" : ""} ${selectedId && isOrderObj && slotOrder.id === selectedId ? "ring-2 ring-inset ring-om-accent bg-om-chip" : ""}`,
        "data-cell-line": line.id,
        "data-cell-date": dh.date,
        "data-cell-shift": s,
        rowSpan: rowspan > 1 ? rowspan : void 0
      },
      isOrderObj ? /* @__PURE__ */ React.createElement(
        "div",
        {
          className: "relative group/cell h-full",
          draggable: true,
          onDragStart: (e) => onDragStart(e, slotOrder.id, slotOrder.order_no),
          onDragEnd,
          onDragOver: (e) => onDragOver(e, cellId),
          onDragLeave: (e) => onDragLeave(e, cellId),
          onDrop: (e) => onDrop(e, line.id, dh.date, s, weekNumber)
        },
        (() => {
          const isOverdue = slotOrder.due_date && new Date(slotOrder.due_date) < /* @__PURE__ */ new Date() && !["DONE", "REJECTED", "CANCELLED"].includes(slotOrder.status);
          const colorClass = isOverdue ? "bg-om-blocked text-white animate-pulse ring-2 ring-red-400" : WO_COLORS[slotOrder.status] ?? "bg-om-line2 border-om-line text-om-muted";
          return /* @__PURE__ */ React.createElement(
            Link,
            {
              href: `/admin/work-orders/${slotOrder.id}`,
              className: `block px-2 py-4 rounded text-[11px] font-medium truncate cursor-pointer hover:opacity-80 transition h-full flex items-center border-2 ${colorClass}`,
              onClick: (e) => {
                e.preventDefault();
                onSelectOrder(slotOrder, dh.date, s);
              },
              onMouseEnter: (e) => onShowTip(e, { order_no: slotOrder.order_no, product: slotOrder.product_name ?? "-", qty: slotOrder.planned_qty, status: STATUS_LABELS[slotOrder.status] ?? slotOrder.status }),
              onMouseLeave: onHideTip
            },
            slotOrder.order_no
          );
        })(),
        /* @__PURE__ */ React.createElement(
          "button",
          {
            onClick: (e) => {
              e.preventDefault();
              onUnassign(slotOrder.id);
            },
            className: "absolute -top-1 -right-1 w-4 h-4 rounded-full bg-om-blocked text-white text-[8px] font-bold leading-none flex items-center justify-center opacity-0 group-hover/cell:opacity-100 transition-opacity shadow-sm hover:brightness-95 z-10",
            title: "Remove from schedule"
          },
          "\u2715"
        )
      ) : /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
        "div",
        {
          onClick: () => onOpenAssign(line.id, dh.date, s, weekNumber),
          onDragOver: (e) => {
            e.preventDefault();
            onDragOver(e, cellId);
          },
          onDragLeave: (e) => onDragLeave(e, cellId),
          onDrop: (e) => onDrop(e, line.id, dh.date, s, weekNumber),
          "data-cell-line": line.id,
          "data-cell-date": dh.date,
          "data-cell-shift": s,
          className: `h-[52px] rounded transition-all cursor-pointer relative overflow-hidden ${isDragOver ? "bg-om-chip border-2 border-dashed border-om-accent scale-[1.02]" : `${SHIFT_COLORS[s]?.bg ?? "bg-om-line2"} opacity-15 hover:opacity-40`}`
        },
        isDragOver && dragOrderNo.current && /* @__PURE__ */ React.createElement("span", { className: "absolute inset-0 flex items-center justify-center text-[9px] font-bold text-om-accent" }, dragOrderNo.current)
      ), (maintenanceEvents ?? []).filter((m) => m.line_id == line.id && m.scheduled_at_date === dh.date).map((maint, mi) => /* @__PURE__ */ React.createElement(
        "div",
        {
          key: mi,
          className: "mt-0.5 px-1.5 py-1 rounded text-[9px] font-medium truncate border border-purple-400 bg-om-chip text-om-ink",
          title: `${maint.title} \u2014 ${maint.scheduled_at_time}`
        },
        "\u{1F527} ",
        maint.title
      )))
    );
    return cellTd;
  }))));
}
function DailyView({ data, lines, maintenanceEvents, onUnassign }) {
  const today = (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 shadow-sm overflow-x-auto" }, /* @__PURE__ */ React.createElement("table", { className: "w-full border-collapse table-fixed", style: { minWidth: `${data.length * 80 + 120}px` } }, /* @__PURE__ */ React.createElement("colgroup", null, /* @__PURE__ */ React.createElement("col", { style: { width: "120px" } }), data.map((_, i) => /* @__PURE__ */ React.createElement("col", { key: i }))), /* @__PURE__ */ React.createElement("thead", null, /* @__PURE__ */ React.createElement("tr", { className: "border-b border-om-line2" }, /* @__PURE__ */ React.createElement("th", { className: "sticky left-0 z-10 bg-om-card p-2 text-xs font-semibold text-om-muted text-left border-r border-om-line2" }, "Production line"), data.map((day) => {
    const isToday = day.date === today;
    const isWeekend = (() => {
      const d = new Date(day.date);
      return d.getDay() === 0 || d.getDay() === 6;
    })();
    return /* @__PURE__ */ React.createElement("th", { key: day.date, className: `p-1.5 text-center border-r border-om-line2 ${isToday ? "bg-om-chip" : ""} ${isWeekend ? "bg-om-panel" : ""}` }, /* @__PURE__ */ React.createElement("div", { className: "text-[10px] text-om-faint uppercase" }, formatDate(new Date(day.date), { weekday: "short" })), /* @__PURE__ */ React.createElement("div", { className: `text-xs font-bold ${isToday ? "text-om-accent" : "text-om-muted"}` }, formatDate(new Date(day.date), { day: "2-digit", month: "2-digit" })), isToday && /* @__PURE__ */ React.createElement("div", { className: "h-0.5 bg-om-ink rounded-full mt-0.5 mx-auto w-8" }));
  }))), /* @__PURE__ */ React.createElement("tbody", null, lines.map((line) => /* @__PURE__ */ React.createElement("tr", { key: line.id, className: "border-b border-om-line2 hover:bg-om-bg/50" }, /* @__PURE__ */ React.createElement("td", { className: "sticky left-0 z-10 bg-om-card p-2 text-xs font-medium text-om-muted border-r border-om-line2 whitespace-nowrap" }, line.code ?? line.name), data.map((day) => {
    const isToday = day.date === today;
    const dayLineData = day.lines?.find((l) => l.line.id === line.id);
    const dayOrders = dayLineData?.orders ?? [];
    const dayMaint = (maintenanceEvents ?? []).filter((m) => m.line_id == line.id && m.scheduled_at_date === day.date);
    return /* @__PURE__ */ React.createElement("td", { key: day.date, className: `p-1 border-r border-om-line2 align-top ${isToday ? "bg-om-chip" : ""}` }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-0.5" }, dayOrders.map((wo) => {
      const isOverdue = wo.due_date && new Date(wo.due_date) < /* @__PURE__ */ new Date() && !["DONE", "REJECTED", "CANCELLED"].includes(wo.status);
      return /* @__PURE__ */ React.createElement("div", { key: wo.id, className: "relative group/cell" }, /* @__PURE__ */ React.createElement(
        Link,
        {
          href: `/admin/work-orders/${wo.id}`,
          className: `block px-2 py-4 rounded text-[11px] font-medium truncate border ${isOverdue ? "bg-om-blocked border-red-600 text-white animate-pulse ring-2 ring-red-400" : `${WO_COLORS[wo.status] ?? "bg-om-line2 border-om-line text-om-ink"}`}`,
          title: wo.order_no
        },
        wo.order_no
      ), /* @__PURE__ */ React.createElement(
        "button",
        {
          onClick: () => onUnassign(wo.id),
          className: "absolute -top-1 -right-1 w-4 h-4 rounded-full bg-om-blocked text-white text-[8px] font-bold leading-none flex items-center justify-center opacity-0 group-hover/cell:opacity-100 transition-opacity shadow-sm hover:brightness-95 z-10",
          title: "Remove from schedule"
        },
        "\u2715"
      ));
    }), dayMaint.map((maint, mi) => /* @__PURE__ */ React.createElement(
      "div",
      {
        key: mi,
        className: "block px-2 py-2 rounded text-[10px] font-medium truncate border-2 border-purple-400 bg-om-chip text-om-ink",
        title: `${maint.title} \u2014 ${maint.scheduled_at_time}`
      },
      maint.title
    )), dayOrders.length === 0 && dayMaint.length === 0 && /* @__PURE__ */ React.createElement("div", { className: "h-8" })));
  }))))));
}
function HourlyView({ data, slotMinutes, startDate, shiftsPerDay, maintenanceEvents, onUnassign, onRefresh, showToast }) {
  const scrollerRef = useRef(null);
  const [nowLeft, setNowLeft] = useState(null);
  const isToday = data?.date === (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
  const dayStartIso = data?.date ? `${data.date}T00:00:00` : null;
  const dragRef = useRef({ active: false, mode: null, card: null, origLeft: 0, origWidth: 0, origStart: 0, origDur: 0, origLineId: null, startX: 0 });
  const [statusText, setStatusText] = useState("");
  function snapToSlot(min) {
    return Math.round(min / slotMinutes) * slotMinutes;
  }
  function fmtMin(m) {
    return `${String(Math.floor(m / 60)).padStart(2, "0")}:${String(m % 60).padStart(2, "0")}`;
  }
  function toIso(min) {
    if (!dayStartIso) return "";
    const d = new Date(dayStartIso);
    d.setMinutes(d.getMinutes() + min);
    return d.toISOString();
  }
  useEffect(() => {
    const update = () => {
      if (!isToday) {
        setNowLeft(null);
        return;
      }
      const now = /* @__PURE__ */ new Date();
      setNowLeft((now.getHours() * 60 + now.getMinutes()) * PX_PER_MINUTE);
    };
    update();
    if (!isToday) return;
    const t = setInterval(update, 6e4);
    return () => clearInterval(t);
  }, [isToday]);
  useEffect(() => {
    if (!scrollerRef.current) return;
    if (isToday && nowLeft !== null) {
      scrollerRef.current.scrollLeft = Math.max(0, nowLeft - 200);
    } else {
      scrollerRef.current.scrollLeft = 6 * 60 * PX_PER_MINUTE;
    }
  }, []);
  const shiftBoundaries = [];
  if (shiftsPerDay >= 1) {
    const len = Math.floor(1440 / shiftsPerDay);
    for (let b = len; b < 1440; b += len) shiftBoundaries.push(b);
  }
  const getCsrfToken = () => document.querySelector("meta[name=csrf-token]")?.content ?? "";
  const handleMouseDown = (e, card, mode) => {
    if (e.button !== 0) return;
    dragRef.current = {
      active: true,
      mode,
      card,
      origLeft: parseFloat(card.style.left) || 0,
      origWidth: parseFloat(card.style.width) || 0,
      origStart: parseInt(card.dataset.startMinute, 10) || 0,
      origDur: parseInt(card.dataset.durationMinutes, 10) || 0,
      origLineId: parseInt(card.dataset.lineId, 10) || null,
      startX: e.clientX
    };
    card.classList.add(mode === "move" ? "opacity-80" : "ring-2");
    setStatusText(mode === "move" ? `${fmtMin(dragRef.current.origStart)} (move)` : `${dragRef.current.origDur}m (resize)`);
    e.preventDefault();
  };
  useEffect(() => {
    const onMove = (e) => {
      const d = dragRef.current;
      if (!d.active || !d.card) return;
      const dx = e.clientX - d.startX;
      if (d.mode === "move") {
        const maxLeft = TOTAL_WIDTH - d.origWidth;
        const clamped = Math.max(0, Math.min(maxLeft, d.origLeft + dx));
        d.card.style.left = clamped + "px";
        setStatusText(`${fmtMin(snapToSlot(clamped / PX_PER_MINUTE))} (${d.origDur}m)`);
      } else if (d.mode === "resize") {
        const minW = slotMinutes * PX_PER_MINUTE;
        const maxW = TOTAL_WIDTH - d.origLeft;
        const clamped = Math.max(minW, Math.min(maxW, d.origWidth + dx));
        d.card.style.width = clamped + "px";
        setStatusText(`${fmtMin(d.origStart)} (${snapToSlot(clamped / PX_PER_MINUTE)}m)`);
      }
    };
    const onUp = async (e) => {
      const d = dragRef.current;
      if (!d.active || !d.card) return;
      d.active = false;
      d.card.classList.remove("opacity-80", "ring-2");
      const woId = d.card.dataset.woId;
      if (d.mode === "move") {
        const rawMin = (parseFloat(d.card.style.left) || 0) / PX_PER_MINUTE;
        const newStart = Math.max(0, Math.min(24 * 60 - d.origDur, snapToSlot(rawMin)));
        d.card.style.left = newStart * PX_PER_MINUTE + "px";
        let targetLineId = d.origLineId;
        const under = document.elementFromPoint(e.clientX, e.clientY);
        if (under) {
          const lane = under.closest("[data-lane-id]");
          if (lane?.dataset.laneId) targetLineId = parseInt(lane.dataset.laneId, 10);
        }
        if (newStart === d.origStart && targetLineId === d.origLineId) {
          setStatusText("");
          return;
        }
        await saveHourly(woId, toIso(newStart), toIso(newStart + d.origDur), targetLineId, false, onRefresh, showToast, getCsrfToken);
        d.card.dataset.startMinute = newStart;
      } else {
        const rawDur = (parseFloat(d.card.style.width) || 0) / PX_PER_MINUTE;
        const newDur = Math.min(snapToSlot(Math.max(slotMinutes, rawDur)), 24 * 60 - d.origStart);
        d.card.style.width = newDur * PX_PER_MINUTE + "px";
        if (newDur === d.origDur) {
          setStatusText("");
          return;
        }
        await saveHourlyResize(woId, toIso(d.origStart), toIso(d.origStart + newDur), false, onRefresh, showToast, getCsrfToken);
        d.card.dataset.durationMinutes = newDur;
      }
      setStatusText("");
    };
    window.addEventListener("mousemove", onMove);
    window.addEventListener("mouseup", onUp);
    return () => {
      window.removeEventListener("mousemove", onMove);
      window.removeEventListener("mouseup", onUp);
    };
  }, [slotMinutes, dayStartIso]);
  if (!data || !data.lines) return /* @__PURE__ */ React.createElement("div", { className: "p-8 text-om-faint text-sm text-center" }, "No hourly data.");
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 shadow-sm overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between px-4 py-2.5 border-b border-om-line2 bg-om-panel/60" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-bold text-om-ink" }, data.label), isToday && /* @__PURE__ */ React.createElement("span", { className: "px-1.5 py-0.5 rounded text-[10px] font-semibold bg-om-chip text-om-accent" }, "Today"), /* @__PURE__ */ React.createElement("span", { className: "text-[10px] text-om-faint" }, slotMinutes, " min snap"), statusText && /* @__PURE__ */ React.createElement("span", { className: "text-[10px] font-mono bg-om-ink text-om-on-ink px-2 py-0.5 rounded" }, statusText)), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 text-[10px] text-om-muted" }, /* @__PURE__ */ React.createElement("span", { className: "inline-flex items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-2.5 rounded-sm bg-om-accent/40 border border-om-accent" }), " Scheduled"), /* @__PURE__ */ React.createElement("span", { className: "inline-flex items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-2.5 rounded-sm bg-om-blocked-bg border border-om-blocked" }), " Conflict"), /* @__PURE__ */ React.createElement("span", { className: "inline-flex items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-2.5 rounded-sm bg-om-line2 border border-om-faintest border-dashed" }), " Legacy"))), /* @__PURE__ */ React.createElement("div", { className: "flex" }, /* @__PURE__ */ React.createElement("div", { className: "shrink-0 w-[200px] border-r border-om-line2 bg-om-card" }, /* @__PURE__ */ React.createElement("div", { className: "h-9 border-b border-om-line2 bg-om-panel/60 flex items-center px-3" }, /* @__PURE__ */ React.createElement("span", { className: "text-[10px] font-semibold text-om-muted uppercase tracking-wide" }, "Production line")), data.lines.map((lr) => {
    const maxLanes = lr.orders.reduce((m, o) => Math.max(m, o.total_lanes ?? 1), 1);
    const labelH = Math.max(114, 6 + maxLanes * 34);
    return /* @__PURE__ */ React.createElement("div", { key: lr.line.id, className: "border-b border-om-line2 px-3 py-2 flex flex-col justify-center", style: { height: `${labelH}px` } }, /* @__PURE__ */ React.createElement("div", { className: "text-sm font-semibold text-om-ink truncate" }, lr.line.code ?? lr.line.name), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1.5 mt-1" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1 h-1.5 bg-om-line2 rounded-full overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: `h-full rounded-full ${lr.load_percent >= 90 ? "bg-om-blocked" : lr.load_percent >= 70 ? "bg-om-downtime" : "bg-emerald-500"}`, style: { width: `${lr.load_percent}%` } })), /* @__PURE__ */ React.createElement("span", { className: `text-[10px] font-bold ${lr.load_percent >= 90 ? "text-om-blocked" : lr.load_percent >= 70 ? "text-om-downtime" : "text-emerald-600"}` }, lr.load_percent, "%")), /* @__PURE__ */ React.createElement("div", { className: "text-[10px] text-om-faint mt-0.5" }, Math.floor(lr.used_minutes / 60), "h ", lr.used_minutes % 60, "m / 24h"));
  })), /* @__PURE__ */ React.createElement("div", { className: "flex-1 overflow-x-auto", ref: scrollerRef }, /* @__PURE__ */ React.createElement("div", { className: "relative", style: { width: `${TOTAL_WIDTH}px` } }, /* @__PURE__ */ React.createElement("div", { className: "flex h-9 border-b border-om-line2 bg-om-panel/60 sticky top-0 z-20" }, Array.from({ length: 24 }, (_, h) => /* @__PURE__ */ React.createElement("div", { key: h, className: "relative shrink-0 border-r border-om-line2 flex items-center justify-start pl-1.5", style: { width: `${HOUR_PX}px` } }, /* @__PURE__ */ React.createElement("span", { className: "text-[10px] font-semibold text-om-muted" }, String(h).padStart(2, "0"), ":00"), /* @__PURE__ */ React.createElement("div", { className: "absolute inset-y-0 w-px bg-om-line2", style: { left: `${HOUR_PX / 4}px` } }), /* @__PURE__ */ React.createElement("div", { className: "absolute inset-y-0 w-px bg-om-line2", style: { left: `${HOUR_PX / 2}px` } }), /* @__PURE__ */ React.createElement("div", { className: "absolute inset-y-0 w-px bg-om-line2", style: { left: `${HOUR_PX * 3 / 4}px` } })))), data.lines.map((lr) => {
    const maxLanes = lr.orders.reduce((m, o) => Math.max(m, o.total_lanes ?? 1), 1);
    const rowH = Math.max(114, 6 + maxLanes * 34);
    const lineMaint = (maintenanceEvents ?? []).filter((m) => m.line_id == lr.line.id && m.scheduled_at_date === data.date);
    return /* @__PURE__ */ React.createElement(
      "div",
      {
        key: lr.line.id,
        "data-lane-id": lr.line.id,
        className: "lane relative border-b border-om-line2",
        style: { height: `${rowH}px` }
      },
      Array.from({ length: 23 }, (_, h) => /* @__PURE__ */ React.createElement("div", { key: h, className: "absolute top-0 bottom-0 w-px bg-om-line2", style: { left: `${(h + 1) * HOUR_PX}px` } })),
      shiftBoundaries.map((b) => /* @__PURE__ */ React.createElement("div", { key: b, className: "absolute top-0 bottom-0 w-px border-l border-dashed border-indigo-400/70", style: { left: `${b * PX_PER_MINUTE}px` } })),
      isToday && nowLeft !== null && /* @__PURE__ */ React.createElement("div", { className: "absolute top-0 bottom-0 w-0.5 bg-om-blocked z-30 pointer-events-none", style: { left: `${nowLeft}px` } }, /* @__PURE__ */ React.createElement("div", { className: "absolute -top-1 -left-1 w-2 h-2 rounded-full bg-om-blocked" })),
      lr.orders.map((order) => {
        const wo = order.wo;
        const totalLanes = order.total_lanes ?? 1;
        const lane = order.lane ?? 0;
        const laneH = totalLanes > 1 ? Math.max(30, Math.floor(90 / totalLanes)) : 90;
        const laneTop = 6 + lane * (laneH + 2);
        const cardClass = order.has_conflict ? "bg-om-blocked-bg border-om-blocked text-om-blocked" : order.is_legacy ? "bg-om-line2 border-om-faintest text-om-muted border-dashed" : wo.status === "IN_PROGRESS" ? "bg-om-downtime-bg border-om-downtime text-om-downtime" : wo.status === "DONE" ? "bg-om-running-bg border-om-running text-om-running" : wo.status === "BLOCKED" ? "bg-om-blocked-bg border-om-blocked text-om-blocked" : "bg-om-chip border-om-accent text-om-ink";
        const startH = Math.floor(order.start_minute / 60);
        const startM = order.start_minute % 60;
        const endH = Math.floor(order.end_minute / 60);
        const endM = order.end_minute % 60;
        return /* @__PURE__ */ React.createElement(
          "div",
          {
            key: wo.id,
            className: `wo-card absolute rounded border-2 shadow-sm px-1.5 py-1 overflow-visible cursor-grab active:cursor-grabbing select-none group ${cardClass}`,
            style: { left: `${order.start_minute * PX_PER_MINUTE}px`, width: `${Math.max(20, order.duration_minutes * PX_PER_MINUTE)}px`, top: `${laneTop}px`, height: `${laneH}px` },
            "data-wo-id": wo.id,
            "data-line-id": lr.line.id,
            "data-start-minute": order.start_minute,
            "data-duration-minutes": order.duration_minutes,
            onMouseDown: (e) => handleMouseDown(e, e.currentTarget, "move"),
            title: `${wo.order_no} \u2014 ${wo.product_name ?? ""}`
          },
          order.start_minute === 0 && wo.planned_start_at && /* @__PURE__ */ React.createElement("span", { className: "absolute left-0 top-1/2 -translate-y-1/2 -ml-0.5 text-[12px] leading-none text-om-muted" }, "\xAB"),
          order.end_minute >= data.minutes_per_day && wo.planned_end_at && /* @__PURE__ */ React.createElement("span", { className: "absolute right-2 top-1/2 -translate-y-1/2 text-[12px] leading-none text-om-muted" }, "\xBB"),
          /* @__PURE__ */ React.createElement("div", { className: "flex flex-col h-full pointer-events-none" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1" }, /* @__PURE__ */ React.createElement(Link, { href: `/admin/work-orders/${wo.id}`, className: "text-[11px] font-bold truncate pointer-events-auto hover:underline", onClick: (e) => e.stopPropagation() }, wo.order_no), order.has_conflict && /* @__PURE__ */ React.createElement("span", { className: "text-[9px] font-bold uppercase text-om-blocked" }, "Conflict"), order.is_legacy && /* @__PURE__ */ React.createElement("span", { className: "text-[9px] font-bold uppercase text-om-muted" }, "Legacy")), /* @__PURE__ */ React.createElement("div", { className: "text-[10px] truncate opacity-80" }, wo.product_name ?? "-"), /* @__PURE__ */ React.createElement("div", { className: "text-[9px] mt-auto opacity-70 truncate" }, String(startH).padStart(2, "0"), ":", String(startM).padStart(2, "0"), " \u2013 ", String(endH).padStart(2, "0"), ":", String(endM).padStart(2, "0"), "(", Math.floor(order.duration_minutes / 60), "h", order.duration_minutes % 60 ? ` ${order.duration_minutes % 60}m` : "", ")")),
          /* @__PURE__ */ React.createElement(
            "button",
            {
              className: "absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-om-blocked text-white text-[8px] font-bold leading-none flex items-center justify-center opacity-0 group-hover:opacity-100 hover:!opacity-100 transition-opacity shadow-sm hover:brightness-95 z-20 pointer-events-auto",
              onClick: (e) => {
                e.stopPropagation();
                if (confirm("Remove this order from schedule?")) onUnassign(wo.id);
              },
              title: "Remove from schedule"
            },
            "\u2715"
          ),
          !order.is_legacy && /* @__PURE__ */ React.createElement(
            "div",
            {
              className: "wo-resize-handle absolute right-0 top-0 bottom-0 w-1.5 cursor-ew-resize bg-black/0 hover:bg-om-ink-hover/20 rounded-r pointer-events-auto",
              onMouseDown: (e) => {
                e.stopPropagation();
                handleMouseDown(e, e.currentTarget.closest("[data-wo-id]"), "resize");
              }
            }
          )
        );
      }),
      lineMaint.map((maint, mi) => {
        const maintMin = maint.scheduled_at_minute ?? 0;
        const maintDur = maint.duration_minutes ?? 60;
        return /* @__PURE__ */ React.createElement(
          "div",
          {
            key: mi,
            className: "absolute rounded-om-sm border-2 border-purple-500 bg-purple-200 px-2 py-1.5 text-[11px] font-bold text-purple-900 truncate z-10 shadow-md flex items-center gap-1",
            style: { left: `${maintMin * PX_PER_MINUTE}px`, width: `${Math.max(80, maintDur * PX_PER_MINUTE)}px`, top: "6px", height: "40px" },
            title: `${maint.title} \u2014 ${maint.scheduled_at_time}`
          },
          "\u{1F527} ",
          /* @__PURE__ */ React.createElement("span", { className: "truncate" }, maint.title)
        );
      }),
      lr.orders.length === 0 && lineMaint.length === 0 && /* @__PURE__ */ React.createElement("div", { className: "absolute inset-0 flex items-center justify-center text-[11px] text-om-faintest italic pointer-events-none" }, "No scheduled orders")
    );
  })))));
}
async function saveHourly(woId, startAt, endAt, lineId, force, onRefresh, showToast, getCsrf2) {
  const body = { planned_start_at: startAt, planned_end_at: endAt, line_id: lineId };
  if (force) body.force_conflict = 1;
  const r = await fetch(`/admin/schedule/${woId}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": getCsrf2(), "X-Requested-With": "XMLHttpRequest" },
    body: JSON.stringify(body)
  });
  if (r.status === 409) {
    const d = await r.json().catch(() => ({}));
    if (confirm((d.message || "Conflict detected.") + "\n\nSave anyway?")) {
      return saveHourly(woId, startAt, endAt, lineId, true, onRefresh, showToast, getCsrf2);
    }
    location.reload();
    return;
  }
  if (!r.ok) {
    alert(`Save failed (${r.status}).`);
    location.reload();
    return;
  }
  if (lineId !== parseInt(new URLSearchParams(window.location.search).get("line_id") || "0")) location.reload();
}
async function saveHourlyResize(woId, startAt, endAt, force, onRefresh, showToast, getCsrf2) {
  const body = { planned_start_at: startAt, planned_end_at: endAt };
  if (force) body.force_conflict = 1;
  const r = await fetch(`/admin/schedule/${woId}/resize`, {
    method: "PUT",
    headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": getCsrf2(), "X-Requested-With": "XMLHttpRequest" },
    body: JSON.stringify(body)
  });
  if (r.status === 409) {
    const d = await r.json().catch(() => ({}));
    if (confirm((d.message || "Conflict detected.") + "\n\nSave anyway?")) {
      return saveHourlyResize(woId, startAt, endAt, true, onRefresh, showToast, getCsrf2);
    }
    location.reload();
  }
  if (!r.ok) {
    alert(`Save failed (${r.status}).`);
    location.reload();
  }
}
function MonthlyView({ data, onUnassign }) {
  return /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, data.map((period) => /* @__PURE__ */ React.createElement("div", { key: `${period.month}-${period.year}`, className: "bg-om-card rounded-om border border-om-line2 shadow-sm overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between px-5 py-3 bg-om-panel border-b border-om-line2" }, /* @__PURE__ */ React.createElement("span", { className: "text-lg font-bold text-om-ink" }, period.label), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-4 text-sm" }, /* @__PURE__ */ React.createElement("span", null, "orders: ", /* @__PURE__ */ React.createElement("strong", null, period.total_orders)), /* @__PURE__ */ React.createElement("span", null, "load: ", /* @__PURE__ */ React.createElement("strong", { className: loadPercClass(period.total_load_percent) }, period.total_load_percent, "%")))), /* @__PURE__ */ React.createElement("div", { className: "divide-y divide-om-line2" }, period.lines.map((lineData) => /* @__PURE__ */ React.createElement("div", { key: lineData.line.id, className: "flex items-center px-4 py-2.5 hover:bg-om-bg/50" }, /* @__PURE__ */ React.createElement("div", { className: "w-32 shrink-0 text-sm font-medium text-om-muted" }, lineData.line.name), /* @__PURE__ */ React.createElement("div", { className: "flex-1 flex flex-wrap gap-1.5" }, lineData.orders.map((wo) => {
    const isOverdue = wo.due_date && new Date(wo.due_date) < /* @__PURE__ */ new Date() && !["DONE", "REJECTED", "CANCELLED"].includes(wo.status);
    return /* @__PURE__ */ React.createElement(
      Link,
      {
        key: wo.id,
        href: `/admin/work-orders/${wo.id}`,
        className: `inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[11px] font-medium ${isOverdue ? "bg-om-blocked border-red-600 text-white animate-pulse ring-2 ring-red-400" : `${WO_COLORS[wo.status] ?? "bg-om-line2 border-om-line text-om-ink"}`}`
      },
      wo.order_no,
      " ",
      /* @__PURE__ */ React.createElement("span", { className: "opacity-50" }, "\xB7"),
      " ",
      wo.planned_qty,
      "pcs"
    );
  }), lineData.orders.length === 0 && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint italic" }, "No orders")), /* @__PURE__ */ React.createElement("div", { className: `w-20 text-right text-sm font-bold ${loadPercClass(lineData.load_percent)}` }, lineData.load_percent, "%")))))));
}
function BacklogPanel({
  backlogOrders,
  backlogItems,
  allLines,
  search,
  onSearch,
  filterLine,
  onFilterLine,
  filterPriority,
  onFilterPriority,
  sort,
  onSort,
  groupedBacklog,
  priorityKeys,
  dragOverCell,
  onDragStart,
  onDragEnd,
  onCollapse
}) {
  const totalPcs = backlogOrders.reduce((s, o) => s + (Number(o.planned_qty) || 0), 0);
  const urgentCount = backlogOrders.filter((o) => (o.priority ?? 0) >= 4).length;
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line2 rounded-om shadow-sm flex flex-col sticky top-4", style: { height: "calc(100vh - 180px)" } }, /* @__PURE__ */ React.createElement("div", { className: "px-3 py-2.5 border-b border-om-line2 flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4 text-om-faint", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" })), /* @__PURE__ */ React.createElement("span", { className: "text-sm font-bold text-om-muted" }, __("Backlog")), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint" }, "(", backlogOrders.length, ")")), /* @__PURE__ */ React.createElement("button", { onClick: onCollapse, className: "p-1 rounded hover:bg-om-chip text-om-faint" }, /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", strokeWidth: "2", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", d: "M9 5l7 7-7 7" })))), /* @__PURE__ */ React.createElement("div", { className: "px-3 py-2 border-b border-om-line2" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: search,
      onChange: (e) => onSearch(e.target.value),
      placeholder: __("Search orders..."),
      className: "w-full text-xs border-om-line rounded-om-sm py-1.5 px-2.5 placeholder-om-faint"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "px-3 py-2 border-b border-om-line2 space-y-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1" }, /* @__PURE__ */ React.createElement("button", { onClick: () => onFilterLine(""), className: `px-2 py-0.5 text-[10px] font-medium rounded transition ${filterLine === "" ? "bg-om-ink text-om-on-ink" : "bg-om-chip text-om-muted hover:bg-om-line2"}` }, __("All")), allLines.map((l) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key: l.id,
      onClick: () => onFilterLine(filterLine === String(l.id) ? "" : String(l.id)),
      className: `px-2 py-0.5 text-[10px] font-medium rounded transition ${filterLine === String(l.id) ? "bg-om-ink text-om-on-ink" : "bg-om-chip text-om-muted hover:bg-om-line2"}`
    },
    l.code ?? l.name
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1" }, Object.entries(PRIORITY_META).map(([pv, pl]) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key: pv,
      onClick: () => onFilterPriority(filterPriority === pv ? "" : pv),
      className: `px-2 py-0.5 text-[10px] font-medium rounded border ${pl.bg} ${pl.color} transition ${filterPriority === pv ? "ring-2 ring-gray-400" : ""}`
    },
    pl.icon,
    " ",
    pl.label
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1.5" }, /* @__PURE__ */ React.createElement("span", { className: "text-[10px] text-om-faint" }, __("Sort:")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: sort == null ? "" : String(sort),
      onChange: (v) => onSort(v),
      options: [
        { value: "due_date", label: __("Due date") },
        { value: "priority", label: __("Priority") },
        { value: "planned_qty", label: __("Quantity") }
      ]
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex-1 overflow-y-auto px-3 py-2 space-y-2" }, backlogOrders.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "text-center py-8" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint" }, __("No unassigned orders"))) : priorityKeys.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "text-center py-8 text-xs text-om-faint" }, __("No results match filters")) : priorityKeys.map((p) => {
    const pl = PRIORITY_META[p] ?? PRIORITY_META[3];
    return /* @__PURE__ */ React.createElement("div", { key: p }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1.5 pt-1" }, /* @__PURE__ */ React.createElement("span", { className: `text-[10px] font-bold ${pl.color}` }, pl.icon, " ", pl.label), /* @__PURE__ */ React.createElement("span", { className: "text-[10px] text-om-faint" }, "(", groupedBacklog[p].length, ")"), /* @__PURE__ */ React.createElement("div", { className: "flex-1 border-t border-om-line2" })), groupedBacklog[p].map((item) => /* @__PURE__ */ React.createElement(
      "div",
      {
        key: item.id,
        className: `border rounded-om-sm p-2.5 ${pl.bg} hover:shadow-sm transition text-xs cursor-grab active:cursor-grabbing`,
        draggable: true,
        onDragStart: (e) => onDragStart(e, item.id),
        onDragEnd
      },
      /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between mb-1.5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(Link, { href: `/admin/work-orders/${item.id}`, className: "font-bold text-om-ink hover:underline" }, item.order_no), /* @__PURE__ */ React.createElement("div", { className: "text-[10px] text-om-muted mt-0.5" }, item.product)), /* @__PURE__ */ React.createElement("span", { className: `px-1.5 py-0.5 rounded text-[9px] font-medium border ${WO_COLORS[backlogOrders.find((o) => o.id === item.id)?.status] ?? "bg-om-line2 border-om-line text-om-muted"}` }, item.status)),
      /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-x-3 gap-y-0.5 text-[10px] text-om-muted" }, /* @__PURE__ */ React.createElement("div", null, "Qty: ", /* @__PURE__ */ React.createElement("strong", { className: "text-om-ink" }, formatNumber(Number(item.qty)))), /* @__PURE__ */ React.createElement("div", null, "Due: ", /* @__PURE__ */ React.createElement("strong", { className: item.due_date !== "-" && new Date(item.due_date) < /* @__PURE__ */ new Date() ? "text-om-blocked" : "text-om-ink" }, item.due_date)), /* @__PURE__ */ React.createElement("div", null, "Line: ", /* @__PURE__ */ React.createElement("strong", { className: "text-om-ink" }, item.line_id ? allLines.find((l) => l.id == item.line_id)?.name ?? "unassigned" : "unassigned")), /* @__PURE__ */ React.createElement("div", null, "Priority: ", /* @__PURE__ */ React.createElement("strong", { className: pl.color }, item.priority ?? "-"))),
      !item.line_id && /* @__PURE__ */ React.createElement("div", { className: "mt-1.5 text-[10px] text-om-downtime bg-om-downtime-bg rounded px-2 py-1" }, "Suggestion: Assign to available line with free capacity")
    )));
  })), /* @__PURE__ */ React.createElement("div", { className: "px-3 py-2.5 border-t border-om-line2 bg-om-panel/50 rounded-b-xl" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-2 text-center mb-2.5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "text-[10px] text-om-faint" }, "total pcs"), /* @__PURE__ */ React.createElement("div", { className: "text-sm font-bold text-om-ink" }, formatNumber(totalPcs))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "text-[10px] text-om-faint" }, "orders"), /* @__PURE__ */ React.createElement("div", { className: "text-sm font-bold text-om-ink" }, backlogOrders.length)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "text-[10px] text-om-faint" }, "urgent"), /* @__PURE__ */ React.createElement("div", { className: "text-sm font-bold text-om-blocked" }, urgentCount))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-1.5" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/work-orders/create", className: "flex-1 text-center py-1.5 rounded-om-sm text-[10px] font-medium bg-om-ink text-om-on-ink hover:bg-om-ink-hover transition" }, "+ Add"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/csv-import", className: "flex-1 text-center py-1.5 rounded-om-sm text-[10px] font-medium bg-om-chip text-om-muted hover:bg-om-line2 transition border border-om-line" }, "Import CSV"))));
}
export {
  Planner as default
};
