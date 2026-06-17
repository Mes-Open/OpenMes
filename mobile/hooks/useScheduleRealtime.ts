import { useElectricInvalidate } from '@/hooks/useElectricInvalidate';

/**
 * Keep the schedule + work-order lists fresh. Previously the Reverb `schedule`
 * (+ per-line) channels; now driven by the Electric `work_orders_all` shape —
 * the schedule is derived from work orders, so any work-order change
 * invalidates the schedule and work-order REST caches.
 *
 * Same signature as before, so consuming screens are unchanged.
 */
export function useScheduleRealtime(enabled: boolean = true): void {
  useElectricInvalidate(
    'work_orders_all',
    [['system-schedule'], ['work-orders']],
    {
      enabled,
      // Schedule depends on timing/assignment fields, not just status.
      fields: ['id', 'status', 'line_id', 'planned_start_at', 'planned_end_at', 'due_date', 'updated_at'],
    },
  );
}
