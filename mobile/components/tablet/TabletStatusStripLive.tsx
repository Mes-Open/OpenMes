import { TabletStatusStrip } from '@/components/tablet/TabletStatusStrip';
import { useColorScheme } from '@/components/useColorScheme';
import { useConnections } from '@/hooks/queries/useConnectivity';

interface Props {
  /** When undefined the strip follows the active color scheme — most pages
   *  rely on this so dark-mode toggles propagate without each call site
   *  having to forward a prop. Operator screens that force a dark surface
   *  pass `dark={true}` explicitly. */
  dark?: boolean;
}

/**
 * Wrapper around TabletStatusStrip that wires the MQTT live dot to the real
 * `useConnections` query. Designed to live at the top of each tablet page's
 * content area — TabletShell + ScreenHeader render it so the strip appears
 * above every tablet page without pushing the permanent sidebar down.
 */
export function TabletStatusStripLive({ dark }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const effectiveDark = dark ?? scheme === 'dark';
  const connectionsQ = useConnections(true);
  const mqttLive = connectionsQ.data
    ? (connectionsQ.data ?? []).some((c) => c.status === 'connected')
    : null;
  return <TabletStatusStrip dark={effectiveDark} mqttLive={mqttLive} />;
}
