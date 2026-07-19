export type EchoConnectionState =
  | 'initializing'
  | 'connecting'
  | 'connected'
  | 'unavailable'
  | 'disconnected'
  | 'failed';

/**
 * Liveness indicator for the UI (drives <LiveDot/>).
 *
 * There is no live push channel (the realtime transports were removed), so data
 * stays fresh by REST polling (React Query `refetchInterval`). Report `unavailable` so
 * <LiveDot/> shows its honest "POLLING" state rather than a misleading "LIVE".
 *
 * Return type preserved so existing consumers (LiveDot) are unchanged. If a new
 * realtime transport lands, resolve the real connection state here.
 */
export function useEchoConnectionState(): EchoConnectionState {
  return 'unavailable';
}
