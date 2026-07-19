/**
 * No-op. This previously kept the schedule + work-order lists fresh over a live
 * sync; that transport is gone, so those screens stay current via React Query
 * `refetchInterval` plus the targeted cache invalidation each mutation performs
 * on success. The signature is preserved so consuming screens are unchanged —
 * wire a new realtime transport in here if one lands.
 */
export function useScheduleRealtime(_enabled: boolean = true): void {
  // intentionally empty — no realtime transport wired up.
}
