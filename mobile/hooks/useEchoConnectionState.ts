import { useElectricShape, type Row } from '@/hooks/useElectricShape';

export type EchoConnectionState =
  | 'initializing'
  | 'connecting'
  | 'connected'
  | 'unavailable'
  | 'disconnected'
  | 'failed';

/**
 * Liveness indicator for the UI (drives <LiveDot/>). Reverb is gone — liveness
 * now comes from Electric, so this reflects whether an Electric shape is
 * syncing: `connected` once the stream is up, `connecting` while it settles,
 * `failed` on error. Backed by a tiny shape (`lines_active`) so it's cheap.
 *
 * Name/return type preserved so existing consumers (LiveDot) are unchanged.
 */
export function useEchoConnectionState(): EchoConnectionState {
  const { isSuccess, isError } = useElectricShape<Row, number>('lines_active', {
    // We only care about the stream's health, not the rows.
    select: (rows) => rows.length,
  });

  if (isError) return 'failed';
  if (isSuccess) return 'connected';
  return 'connecting';
}
