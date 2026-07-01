import { useEffect, useMemo, useRef } from "react";
import { useLiveQuery } from "@tanstack/react-db";
import { realtimeCollection } from "../lib/realtimeCollection";
function ShapeChangeWatcher({
  shape = "work_orders_all",
  fields = ["id", "status", "line_id", "planned_start_at", "planned_end_at", "due_date", "updated_at"],
  onChange
}) {
  const collection = useMemo(() => realtimeCollection(shape, (r) => r.id), [shape]);
  const { data: rows = [] } = useLiveQuery((q) => q.from({ r: collection }));
  const signature = useMemo(
    () => rows.map((r) => fields.map((f) => String(r[f] ?? "")).join(":")).sort().join("|"),
    [rows, fields]
  );
  const cbRef = useRef(onChange);
  cbRef.current = onChange;
  const first = useRef(true);
  useEffect(() => {
    if (first.current) {
      first.current = false;
      return;
    }
    cbRef.current?.();
  }, [signature]);
  return null;
}
export {
  ShapeChangeWatcher as default
};
