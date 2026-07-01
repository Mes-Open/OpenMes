import { createContext, useContext, useMemo } from "react";
import { realtimeCollection } from "../lib/realtimeCollection";
const LiveShapesContext = createContext(null);
function useHotShapes() {
  return useContext(LiveShapesContext);
}
function LiveShapesProvider({ children }) {
  const value = useMemo(
    () => ({
      workOrdersActive: realtimeCollection("work_orders_active", (r) => r.id),
      issuesOpen: realtimeCollection("issues_open", (r) => r.id)
    }),
    []
  );
  return /* @__PURE__ */ React.createElement(LiveShapesContext.Provider, { value }, children);
}
export {
  LiveShapesProvider,
  useHotShapes
};
