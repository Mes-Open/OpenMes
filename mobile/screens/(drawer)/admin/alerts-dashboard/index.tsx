import { FontAwesome } from '@expo/vector-icons';
import { formatDistanceToNowStrict, isPast, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo } from 'react';
import { Pressable, ScrollView, StatusBar, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { DrawerActions, useNavigation } from '@react-navigation/native';

import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useConnections } from '@/hooks/queries/useConnectivity';
import { useIssues } from '@/hooks/queries/useIssues';
import { useMaintenanceEvents } from '@/hooks/queries/useMaintenance';
import type { Issue } from '@/types/api';
import type { MaintenanceEvent } from '@/api/maintenance';
import type { MachineConnection } from '@/api/connectivity';

const DARK = Colors.dark;

type FeedKind = 'ISSUE' | 'MAINT' | 'MACHINE';
type Sev = 'block' | 'red' | 'major' | 'amber' | 'minor';

interface FeedItem {
  id: string;
  kind: FeedKind;
  sev: Sev;
  line: string;
  desc: string;
  at: Date | null;
  ack?: boolean;
}

const SEV_COLOR: Record<Sev, string> = {
  block: '#c0392b',
  red: '#c0392b',
  major: '#f97316',
  amber: BRAND.amber,
  minor: BRAND.amber,
};

export function AlertsDashboardScreen() {
  const router = useRouter();
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();

  const issuesQ = useIssues({ status: 'OPEN' });
  const maintPendingQ = useMaintenanceEvents({ status: 'pending' });
  const maintInProgressQ = useMaintenanceEvents({ status: 'in_progress' });
  const connectionsQ = useConnections(true);

  const openIssues = issuesQ.data ?? [];
  const offlineConnections = (connectionsQ.data ?? []).filter(
    (c) => c.status !== 'connected',
  );
  const allMaint = useMemo<MaintenanceEvent[]>(
    () => [...(maintPendingQ.data?.data ?? []), ...(maintInProgressQ.data?.data ?? [])],
    [maintPendingQ.data?.data, maintInProgressQ.data?.data],
  );
  const overdueMaint = allMaint.filter((e) => {
    if (!e.scheduled_at) return false;
    try {
      return isPast(parseISO(e.scheduled_at));
    } catch {
      return false;
    }
  });

  // Critical = first blocking issue; "blocking" maps from issue type's severity.
  // Backend doesn't expose a clean blocking flag, so we treat the most recent
  // OPEN issue as critical when nothing else stands out.
  const critical = openIssues[0] ?? null;
  const criticalSub = (() => {
    if (!critical) return null;
    const ago = critical.created_at
      ? formatDistanceToNowStrict(parseISO(critical.created_at))
      : null;
    return [critical.work_order?.order_no, ago ? `${ago}` : null].filter(Boolean).join(' · ');
  })();

  const feed = useMemo(() => buildFeed(openIssues, allMaint, offlineConnections), [
    openIssues,
    allMaint,
    offlineConnections,
  ]);

  return (
    <View style={{ flex: 1, backgroundColor: DARK.background }}>
      <StatusBar barStyle="light-content" />
      <View style={[styles.headerBar, { paddingTop: insets.top + 10 }]}>
        <Pressable
          onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
          hitSlop={8}
          style={({ pressed }) => [
            styles.iconBtn,
            { borderColor: DARK.border, opacity: pressed ? 0.6 : 1 },
          ]}>
          <FontAwesome name="bars" size={16} color={DARK.text} />
        </Pressable>
        <View style={{ flex: 1 }}>
          <Text style={[styles.title, { color: DARK.text }]}>Alerts dashboard</Text>
          <Mono size={11} color={DARK.textFaint} letterSpacing={0.6} style={{ marginTop: 4 }}>
            SYSTEM-WIDE ·{' '}
            {openIssues.length + overdueMaint.length + offlineConnections.length} ACTIVE
          </Mono>
        </View>
        <View
          style={[styles.iconBtn, { borderColor: DARK.border }]}>
          <FontAwesome name="filter" size={14} color={DARK.text} />
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.scroll}>
        {critical ? (
          <Pressable
            onPress={() => router.push(`/issues/${critical.id}` as never)}
            style={({ pressed }) => [styles.criticalBanner, { opacity: pressed ? 0.85 : 1 }]}>
            <View style={styles.criticalIcon}>
              <FontAwesome name="exclamation-triangle" size={22} color="#fff" />
            </View>
            <View style={{ flex: 1 }}>
              <Mono size={11} color="rgba(255,255,255,0.7)" letterSpacing={0.6}>
                CRITICAL · {critical.line?.name ? `BLOCKING ${critical.line.name.toUpperCase()}` : 'OPEN ISSUE'}
              </Mono>
              <Text style={styles.criticalTitle} numberOfLines={2}>
                {critical.description ?? critical.issue_type?.name ?? 'Unresolved issue'}
              </Text>
              {criticalSub ? (
                <Mono size={11} color="rgba(255,255,255,0.85)" style={{ marginTop: 4 }}>
                  {criticalSub.toUpperCase()}
                </Mono>
              ) : null}
            </View>
          </Pressable>
        ) : null}

        <View style={styles.tilesRow}>
          <CountTile
            count={openIssues.length}
            label="ISSUES"
            sub={
              openIssues.length > 0
                ? `${openIssues.length} OPEN`
                : 'ALL CLEAR'
            }
            color="#ff6b6b"
            icon="exclamation-circle"
          />
          <CountTile
            count={overdueMaint.length}
            label="MAINT"
            sub={overdueMaint.length > 0 ? `${overdueMaint.length} OVERDUE` : `${allMaint.length} PENDING`}
            color={BRAND.amber}
            icon="wrench"
          />
          <CountTile
            count={offlineConnections.length}
            label="MACHINE"
            sub={offlineConnections.length > 0 ? 'MQTT OFF' : 'ALL LIVE'}
            color="#a78bfa"
            icon="cog"
          />
        </View>

        <Mono size={11} color={DARK.textFaint} letterSpacing={0.8}>UNIFIED FEED</Mono>

        <View style={[styles.feed, { borderColor: DARK.border }]}>
          {feed.length === 0 ? (
            <Mono
              size={11}
              color={DARK.textFaint}
              style={{ padding: 16, textAlign: 'center' }}>
              NO ACTIVE ALERTS
            </Mono>
          ) : (
            feed.map((item, i) => (
              <FeedRow key={item.id} item={item} last={i === feed.length - 1} />
            ))
          )}
        </View>
      </ScrollView>
    </View>
  );
}

function buildFeed(
  issues: Issue[],
  maint: MaintenanceEvent[],
  connections: MachineConnection[],
): FeedItem[] {
  const items: FeedItem[] = [];
  for (const i of issues) {
    items.push({
      id: `i-${i.id}`,
      kind: 'ISSUE',
      sev: 'major',
      line: i.line?.name ?? '—',
      desc: i.description ?? i.issue_type?.name ?? `Issue #${i.id}`,
      at: i.created_at ? safeParse(i.created_at) : null,
      ack: i.acknowledged_at != null,
    });
  }
  for (const e of maint) {
    const overdue =
      e.scheduled_at && safeParse(e.scheduled_at) && isPast(safeParse(e.scheduled_at)!);
    items.push({
      id: `m-${e.id}`,
      kind: 'MAINT',
      sev: overdue ? 'red' : 'amber',
      line: e.line?.name ?? e.tool?.code ?? '—',
      desc: `${e.title}${e.tool?.code ? ` · ${e.tool.code}` : ''}`,
      at: e.scheduled_at ? safeParse(e.scheduled_at) : null,
    });
  }
  for (const c of connections) {
    items.push({
      id: `c-${c.id}`,
      kind: 'MACHINE',
      sev: 'major',
      line: c.name ?? '—',
      desc: `${c.protocol.toUpperCase()} · ${c.status}`,
      at: c.last_connected_at ? safeParse(c.last_connected_at) : null,
    });
  }
  // Newest first.
  return items.sort((a, b) => (b.at?.getTime() ?? 0) - (a.at?.getTime() ?? 0));
}

function safeParse(s: string): Date | null {
  try {
    return parseISO(s);
  } catch {
    return null;
  }
}

function CountTile({
  count,
  label,
  sub,
  color,
  icon,
}: {
  count: number;
  label: string;
  sub: string;
  color: string;
  icon: React.ComponentProps<typeof FontAwesome>['name'];
}) {
  return (
    <View style={[styles.tile, { borderColor: DARK.border, backgroundColor: DARK.surface }]}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Mono size={10} color={DARK.textFaint} letterSpacing={0.6}>{label}</Mono>
        <FontAwesome name={icon} size={12} color={color} />
      </View>
      <Text style={[styles.tileCount, { color, fontFamily: MONO }]}>{count}</Text>
      <Mono size={10} color={DARK.textMuted} letterSpacing={0.4} style={{ marginTop: 4 }}>
        {sub}
      </Mono>
    </View>
  );
}

function FeedRow({ item, last }: { item: FeedItem; last: boolean }) {
  const ago = item.at ? formatDistanceToNowStrict(item.at, { addSuffix: false }) : '';
  return (
    <View
      style={[
        styles.feedRow,
        last ? null : { borderBottomColor: DARK.border, borderBottomWidth: StyleSheet.hairlineWidth },
      ]}>
      <View style={[styles.sevBar, { backgroundColor: SEV_COLOR[item.sev] }]} />
      <View style={{ flex: 1, minWidth: 0 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Mono
            size={9.5}
            color={SEV_COLOR[item.sev]}
            weight="700"
            letterSpacing={0.5}>
            {item.kind}
          </Mono>
          <Mono size={10} color={DARK.textFaint}>· {item.line}</Mono>
          {item.ack ? (
            <Mono size={9} color="#EA5A2B" letterSpacing={0.5}>● ACK</Mono>
          ) : null}
        </View>
        <Text style={[styles.feedDesc, { color: DARK.text }]} numberOfLines={2}>
          {item.desc}
        </Text>
      </View>
      {ago ? <Mono size={10.5} color={DARK.textFaint}>{ago.toUpperCase()}</Mono> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  headerBar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingBottom: 14,
    gap: 12,
  },
  iconBtn: {
    width: 36,
    height: 36,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: { fontSize: 22, fontWeight: '700', letterSpacing: -0.4 },
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  criticalBanner: {
    backgroundColor: '#c0392b',
    borderRadius: 14,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  criticalIcon: {
    width: 48,
    height: 48,
    borderRadius: 12,
    backgroundColor: 'rgba(0,0,0,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  criticalTitle: { color: '#fff', fontSize: 15, fontWeight: '600', marginTop: 4 },
  tilesRow: { flexDirection: 'row', gap: 8 },
  tile: { flex: 1, borderWidth: 1, borderRadius: 12, padding: 12 },
  tileCount: { fontSize: 28, fontWeight: '600', letterSpacing: -0.5, marginTop: 6, lineHeight: 30 },
  feed: { borderWidth: 1, borderRadius: 12, overflow: 'hidden' },
  feedRow: { flexDirection: 'row', alignItems: 'center', gap: 10, padding: 12 },
  sevBar: { width: 3, alignSelf: 'stretch', borderRadius: 2 },
  feedDesc: { fontSize: 12.5, marginTop: 4 },
});
