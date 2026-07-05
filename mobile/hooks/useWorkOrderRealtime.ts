/**
 * No-op. This previously kept a work order's REST-backed view fresh by
 * subscribing to a live sync; that transport is gone, so screens stay current
 * via React Query `refetchInterval` plus the targeted cache invalidation each
 * mutation performs on success. The signature is preserved so consuming screens
 * are unchanged — wire a new realtime transport in here if one lands.
 */
export function useWorkOrderRealtime(_workOrderId: number | undefined): void {
  // intentionally empty — no realtime transport wired up.
}
