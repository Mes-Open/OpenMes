import { FontAwesome } from '@expo/vector-icons';
import { formatDistanceToNowStrict, isPast, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo } from 'react';
import { Pressable, ScrollView, StatusBar, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { DrawerActions, useNavigation } from '@react-navigation/native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { useConnections } from '@/hooks/queries/useConnectivity';
import { useIssues } from '@/hooks/queries/useIssues';
import { useMaintenanceEvents } from '@/hooks/queries/useMaintenance';
import type { Issue } from '@/types/api';
import type { MaintenanceEvent } from '@/api/maintenance';
import type { MachineConnection } from '@/api/connectivity';

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
  block: colors.blocked,
  red: colors.blocked,
  major: colors.accent,
  amber: colors.downtime,
  minor: colors.downtime,
};

export function AlertsDashboardScreen() {
  const router = useRouter();
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const { t } = useTranslation();

  const issuesQ = useIssues({ status: 'OPEN' });
  const maintPendingQ = useMaintenanceEvents({ status: 'pending' });
  const maintInProgressQ = useMaintenanceEvents({ status: 'in_progress' });
  const connectionsQ = useConnections(true);

  const openIssues = issuesQ.data ?? [];
  const offlineConnections = (connectionsQ.data ?? []).filter((c) => c.status !== 'connected');
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

  const critical = openIssues[0] ?? null;
  const criticalSub = (() => {
    if (!critical) return null;
    const ago = critical.created_at ? formatDistanceToNowStrict(parseISO(critical.created_at)) : null;
    return [critical.work_order?.order_no, ago ? `${ago}` : null].filter(Boolean).join(' · ');
  })();

  const feed = useMemo(() => buildFeed(openIssues, allMaint, offlineConnections), [openIssues, allMaint, offlineConnections]);

  return (
    <View style={styles.screen}>
      <StatusBar barStyle="dark-content" />
      <View style={[styles.headerBar, { paddingTop: insets.top + 10 }]}>
        <Pressable
          onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
          hitSlop={8}
          style={({ pressed }) => [styles.iconBtn, { opacity: pressed ? 0.6 : 1 }]}>
          <FontAwesome name="bars" size={16} color={colors.ink} />
        </Pressable>
        <View style={{ flex: 1 }}>
          <Text style={styles.title}>{t('Alerts dashboard')}</Text>
          <Mono size={11} color={colors.faint} letterSpacing={0.6} style={{ marginTop: 4 }}>
            {t('System-wide').toUpperCase()} · {openIssues.length + overdueMaint.length + offlineConnections.length} {t('Active').toUpperCase()}
          </Mono>
        </View>
        <View style={styles.iconBtn}>
          <FontAwesome name="filter" size={14} color={colors.ink} />
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
              <Mono size={11} color="rgba(255,255,255,0.75)" letterSpacing={0.6}>
                {t('Critical').toUpperCase()} · {critical.line?.name ? `${t('Blocking').toUpperCase()} ${critical.line.name.toUpperCase()}` : t('Open issue').toUpperCase()}
              </Mono>
              <Text style={styles.criticalTitle} numberOfLines={2}>
                {critical.description ?? critical.issue_type?.name ?? t('Unresolved issue')}
              </Text>
              {criticalSub ? (
                <Mono size={11} color="rgba(255,255,255,0.9)" style={{ marginTop: 4 }}>{criticalSub.toUpperCase()}</Mono>
              ) : null}
            </View>
          </Pressable>
        ) : null}

        <View style={styles.tilesRow}>
          <CountTile
            count={openIssues.length}
            label={t('Issues')}
            sub={openIssues.length > 0 ? `${openIssues.length} ${t('Open')}` : t('All clear')}
            color={colors.blocked}
            icon="exclamation-circle"
          />
          <CountTile
            count={overdueMaint.length}
            label={t('Maint')}
            sub={overdueMaint.length > 0 ? `${overdueMaint.length} ${t('Overdue')}` : `${allMaint.length} ${t('Pending')}`}
            color={colors.downtime}
            icon="wrench"
          />
          <CountTile
            count={offlineConnections.length}
            label={t('Machine')}
            sub={offlineConnections.length > 0 ? t('MQTT off') : t('All live')}
            color={colors.accent}
            icon="cog"
          />
        </View>

        <Mono size={11} color={colors.faint} letterSpacing={0.8}>{t('Unified feed').toUpperCase()}</Mono>

        <View style={styles.feed}>
          {feed.length === 0 ? (
            <Mono size={11} color={colors.faint} style={{ padding: 16, textAlign: 'center' }}>{t('No active alerts').toUpperCase()}</Mono>
          ) : (
            feed.map((item, i) => <FeedRow key={item.id} item={item} last={i === feed.length - 1} />)
          )}
        </View>
      </ScrollView>
    </View>
  );
}

function buildFeed(issues: Issue[], maint: MaintenanceEvent[], connections: MachineConnection[]): FeedItem[] {
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
    const overdue = e.scheduled_at && safeParse(e.scheduled_at) && isPast(safeParse(e.scheduled_at)!);
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
    <View style={styles.tile}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Mono size={10} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
        <FontAwesome name={icon} size={12} color={color} />
      </View>
      <Mono size={28} color={color} weight="600" style={{ marginTop: 6 }}>{count}</Mono>
      <Mono size={10} color={colors.muted} letterSpacing={0.4} style={{ marginTop: 4 }}>{sub.toUpperCase()}</Mono>
    </View>
  );
}

function FeedRow({ item, last }: { item: FeedItem; last: boolean }) {
  const { t } = useTranslation();
  const ago = item.at ? formatDistanceToNowStrict(item.at, { addSuffix: false }) : '';
  return (
    <View style={[styles.feedRow, last ? null : styles.feedRowBorder]}>
      <View style={[styles.sevBar, { backgroundColor: SEV_COLOR[item.sev] }]} />
      <View style={{ flex: 1, minWidth: 0 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Mono size={9.5} color={SEV_COLOR[item.sev]} weight="700" letterSpacing={0.5}>{item.kind}</Mono>
          <Mono size={10} color={colors.faint}>· {item.line}</Mono>
          {item.ack ? <Mono size={9} color={colors.accent} letterSpacing={0.5}>● {t('Ack').toUpperCase()}</Mono> : null}
        </View>
        <Text style={styles.feedDesc} numberOfLines={2}>{item.desc}</Text>
      </View>
      {ago ? <Mono size={10.5} color={colors.faint}>{ago.toUpperCase()}</Mono> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  headerBar: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 18, paddingBottom: 14, gap: 12 },
  iconBtn: { width: 36, height: 36, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, alignItems: 'center', justifyContent: 'center' },
  title: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  criticalBanner: { backgroundColor: colors.blocked, borderRadius: radius.md, padding: 16, flexDirection: 'row', alignItems: 'center', gap: 14 },
  criticalIcon: { width: 48, height: 48, borderRadius: radius.md, backgroundColor: 'rgba(0,0,0,0.18)', alignItems: 'center', justifyContent: 'center' },
  criticalTitle: { color: '#fff', fontSize: 15, fontFamily: fonts.sans.native.semibold, marginTop: 4 },
  tilesRow: { flexDirection: 'row', gap: 8 },
  tile: { flex: 1, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 12, backgroundColor: colors.card },
  feed: { borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, overflow: 'hidden', backgroundColor: colors.card },
  feedRow: { flexDirection: 'row', alignItems: 'center', gap: 10, padding: 12 },
  feedRowBorder: { borderBottomColor: colors.line2, borderBottomWidth: StyleSheet.hairlineWidth },
  sevBar: { width: 3, alignSelf: 'stretch', borderRadius: 2 },
  feedDesc: { fontSize: 12.5, color: colors.ink, marginTop: 4, fontFamily: fonts.sans.native.regular },
});
