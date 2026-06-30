import { useElectricInvalidate } from '@/hooks/useElectricInvalidate';

/**
 * Keep a single work order's REST-backed view (status + batches + steps) fresh
 * when a supervisor changes anything. Previously a Reverb `workorder.{id}`
 * channel; now driven by the Electric `work_orders_all` shape — when that
 * order's row changes, the related React Query caches are invalidated.
 *
 * Same signature as before, so consuming screens are unchanged.
 */
export function useWorkOrderRealtime(workOrderId: number | undefined): void {
  useElectricInvalidate(
    'work_orders_all',
    [
      ['work-order', workOrderId],
      ['batches', workOrderId],
      ['batch'],
    ],
    {
      enabled: !!workOrderId,
      rowMatches: (r) => String(r.id) === String(workOrderId),
      // produced_qty / line_status also matter for the detail view.
      fields: ['id', 'status', 'produced_qty', 'line_status_id', 'updated_at'],
    },
  );
}
