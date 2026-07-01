import { useEffect, useRef } from "react";
import ShapeChangeWatcher from "./ShapeChangeWatcher";
function LiveRefresh({
  pollUrl,
  shape = "work_orders_all",
  intervalMs = 1e4,
  enabled = true,
  onRefresh
}) {
  const cbRef = useRef(onRefresh);
  cbRef.current = onRefresh;
  const lastUpdate = useRef(null);
  useEffect(() => {
    if (!enabled || !pollUrl) return void 0;
    const tick = async () => {
      try {
        const r = await fetch(pollUrl, {
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
        });
        if (!r.ok) return;
        const d = await r.json();
        if (!d.last_updated) return;
        if (lastUpdate.current === null) {
          lastUpdate.current = d.last_updated;
          return;
        }
        if (d.last_updated !== lastUpdate.current) {
          lastUpdate.current = d.last_updated;
          cbRef.current?.();
        }
      } catch {
      }
    };
    const t = setInterval(tick, intervalMs);
    return () => clearInterval(t);
  }, [enabled, pollUrl, intervalMs]);
  if (!enabled) return null;
  return /* @__PURE__ */ React.createElement(ShapeChangeWatcher, { shape, onChange: () => cbRef.current?.() });
}
export {
  LiveRefresh as default
};
