import { useEffect, useMemo, useRef } from "react";
import { router } from "@inertiajs/react";
import { useLiveQuery } from "@tanstack/react-db";
import { realtimeCollection } from "../lib/realtimeCollection";
function LineSync({ lineId, reloadOnly = [] }) {
  const collection = useMemo(
    () => realtimeCollection("work_orders_active", (r) => r.id),
    []
  );
  const { data: rows = [] } = useLiveQuery((q) => q.from({ r: collection }));
  const signal = useMemo(
    () => rows.filter((r) => String(r.line_id) === String(lineId)).map((r) => `${r.id}:${r.status}:${r.produced_qty}:${r.line_status_id ?? ""}:${r.updated_at ?? ""}`).sort().join("|"),
    [rows, lineId]
  );
  const first = useRef(true);
  useEffect(() => {
    if (first.current) {
      first.current = false;
      return;
    }
    router.reload({
      preserveState: true,
      preserveScroll: true,
      ...reloadOnly.length ? { only: reloadOnly } : {}
    });
  }, [signal]);
  return null;
}
export {
  LineSync as default
};
