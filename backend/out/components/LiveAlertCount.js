import { useMemo } from "react";
import { useLiveQuery } from "@tanstack/react-db";
import { useHotShapes } from "./LiveShapesProvider";
const OPEN_STATUSES = ["OPEN", "ACKNOWLEDGED"];
const TERMINAL_STATUSES = ["DONE", "REJECTED", "CANCELLED"];
function LiveAlertCount({ fallback = 0, children }) {
  const hot = useHotShapes();
  if (!hot) return children(fallback);
  return /* @__PURE__ */ React.createElement(Live, { hot, fallback }, children);
}
function Live({ hot, fallback, children }) {
  const { data: issues = [], isLoading: il } = useLiveQuery((q) => q.from({ r: hot.issuesOpen }));
  const { data: orders = [], isLoading: ol } = useLiveQuery((q) => q.from({ r: hot.workOrdersActive }));
  const count = useMemo(() => {
    const todayStr = (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
    const openIssues = issues.filter((i) => OPEN_STATUSES.includes(i.status)).length;
    const overdue = orders.filter(
      (o) => o.due_date && String(o.due_date).slice(0, 10) < todayStr && !TERMINAL_STATUSES.includes(o.status)
    ).length;
    const blocked = orders.filter((o) => o.status === "BLOCKED").length;
    return openIssues + overdue + blocked;
  }, [issues, orders]);
  return children(il || ol ? fallback : count);
}
export {
  LiveAlertCount as default
};
