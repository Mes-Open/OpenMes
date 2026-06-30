import { format } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';
import { FontAwesome } from '@expo/vector-icons';

import { BarChart } from '@/components/ui/BarChart';
import { Card } from '@/components/ui/Card';
import { DashboardHeader } from '@/components/ui/DashboardHeader';
import { DowntimeBanner } from '@/components/operator/DowntimeBanner';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { StatTile } from '@/components/ui/StatTile';
import { UpdateBanner } from '@/components/system/UpdateBanner';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { WorkOrderCard } from '@/components/workorders/WorkOrderCard';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAnalyticsOverview } from '@/hooks/queries/useAnalyticsOverview';
import { useIssueStatsByLine, useIssues } from '@/hooks/queries/useIssues';
import { useThroughput } from '@/hooks/queries/useAnalyticsOverview';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { useLines } from '@/hooks/queries/useUsers';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { getRole, useAuthStore } from '@/stores/authStore';
import { TabletOperatorTerminal } from '@/screens/tablet/OperatorTerminal';
import { TabletPlantWall } from '@/screens/tablet/PlantWall';
import { TabletSupervisorWarRoom } from '@/screens/tablet/SupervisorWarRoom';
import type { WorkOrder } from '@/types/api';

export function HomeTab() {
  const user = useAuthStore((s) => s.user);
  const role = getRole(user);
  const { useTabletLayout } = useDeviceClass();

  if (useTabletLayout) {
    // Tablet (landscape) design diverges by role:
    //   • Operator → kiosk Terminal
    //   • Supervisor → War Room (foreman / floor view)
    //   • Admin → Plant Wall (big-screen live operations)
    // In portrait or on phone, fall through to the legacy two-mode screens
    // below which adapt to any aspect ratio.
    if (role === 'Admin') return <TabletPlantWall />;
    if (role === 'Supervisor') return <TabletSupervisorWarRoom />;
    return <TabletOperatorTerminal />;
  }

  if (role === 'Admin' || role === 'Supervisor') return <SupervisorDashboard />;
  return <OperatorQueue />;
}

function OperatorQueue() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const user = useAuthStore((s) => s.user);
  const lineId = activeLineId ?? user?.lines?.[0]?.id;
  const lineName = user?.lines?.find((l) => l.id === lineId)?.name;

  const query = useWorkOrders({
    line_id: lineId,
    status: ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'BLOCKED', 'PAUSED'],
  });

  if (query.isLoading) {
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        <ScreenHeader title="Today" subtitle={lineName} />
        <LoadingState />
      </View>
    );
  }
  if (query.isError) {
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        <ScreenHeader title="Today" subtitle={lineName} />
        <ErrorState error={query.error} onRetry={query.refetch} />
      </View>
    );
  }

  const orders = query.data ?? [];
  const active = orders.find((o) => o.status === 'IN_PROGRESS');
  const upNext = orders.filter((o) => o.id !== active?.id);
  const greeting = greet();

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title="Today" subtitle={lineName} />
      <LegendList
        style={{ backgroundColor: palette.background }}
        data={upNext}
        keyExtractor={(wo) => String(wo.id)}
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={{ gap: 18, marginBottom: 18 }}>
            <UpdateBanner />
            <View style={styles.headerBlock}>
              <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
                {format(new Date(), 'EEE · HH:mm').toUpperCase()}
              </Mono>
              <Text style={[styles.greeting, { color: palette.text }]}>
                {greeting}, {user?.username ?? 'operator'}.
              </Text>
              <Text style={[styles.greetingSub, { color: palette.textMuted }]}>
                {orders.length === 0
                  ? 'No work orders queued for your line today.'
                  : `${orders.length} work order${orders.length === 1 ? '' : 's'} queued for your line.`}
              </Text>
            </View>

            {lineId ? <DowntimeBanner lineId={lineId} /> : null}

            {active ? (
              <ActiveOrderHero
                order={active}
                onOpen={() => router.push(`/work-orders/${active.id}`)}
              />
            ) : null}

            {upNext.length > 0 ? (
              <SectionLabel
                right={
                  <Mono size={11} color={palette.textFaint}>
                    {`${upNext.length} ORDER${upNext.length === 1 ? '' : 'S'}`}
                  </Mono>
                }>
                Up next
              </SectionLabel>
            ) : null}
          </View>
        }
        ListEmptyComponent={
          orders.length === 0 ? (
            <EmptyState title="No active work orders" subtitle="Pull to refresh once new orders arrive." />
          ) : null
        }
        renderItem={({ item }) => (
          <WorkOrderCard workOrder={item} onPress={() => router.push(`/work-orders/${item.id}`)} />
        )}
        ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
        refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} />}
      />
    </View>
  );
}

function ActiveOrderHero({ order, onOpen }: { order: WorkOrder; onOpen: () => void }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const planned = order.planned_qty ?? 0;
  const produced = order.produced_qty ?? 0;
  const pct = planned > 0 ? Math.min(100, Math.round((produced / planned) * 100)) : 0;

  return (
    <View style={[styles.hero, { backgroundColor: palette.surfaceInverse }]}>
      <View style={[styles.heroAccent, { backgroundColor: BRAND.amber }]} />
      <View style={styles.heroTopRow}>
        <View style={{ flex: 1 }}>
          <Mono size={10} color="#6F6C66" letterSpacing={0.6}>
            ACTIVE WORK ORDER
          </Mono>
          <Text style={[styles.heroOrderNo, { color: BRAND.amber }]}>{order.order_no}</Text>
          <Text style={styles.heroProduct}>
            {order.product_type?.name ?? 'Work order'} · {planned} pcs
          </Text>
        </View>
        <StatusPill status={order.status} dark />
      </View>

      <View style={styles.heroProgressBlock}>
        <View style={styles.heroProgressRow}>
          <Mono size={11} color="#6F6C66">
            {`${produced}/${planned} produced`}
          </Mono>
          <Mono size={11} color="#1A1917">{`${pct}%`}</Mono>
        </View>
        <View style={styles.heroBarTrack}>
          <View style={[styles.heroBarFill, { width: `${pct}%` }]} />
        </View>
      </View>

      <Pressable onPress={onOpen} style={({ pressed }) => [styles.heroBtn, { opacity: pressed ? 0.85 : 1 }]}>
        <FontAwesome name="arrow-right" size={14} color="#1a1208" />
        <Text style={styles.heroBtnText}>Open active step</Text>
      </Pressable>
    </View>
  );
}

const RANGES = [
  { id: '24h', label: '24H', days: 1, bars: 24 },
  { id: '7d', label: '7D', days: 7, bars: 7 },
  { id: '30d', label: '30D', days: 30, bars: 30 },
] as const;

function SupervisorDashboard() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const role = getRole(user);

  const overview = useAnalyticsOverview(null);
  const lineStats = useIssueStatsByLine();
  const linesQuery = useLines();
  const openIssues = useIssues({ status: 'OPEN' });
  const throughput = useThroughput(30);

  const [range, setRange] = useState<(typeof RANGES)[number]['id']>('24h');

  // Synthetic 24h hourly distribution from total work orders — replace with real
  // hourly endpoint when available.
  const chartData = useMemo(() => {
    const r = RANGES.find((x) => x.id === range)!;
    const seed = Number(overview.data?.total_work_orders ?? 12);
    return Array.from({ length: r.bars }).map((_, i) => {
      // pseudo-random but deterministic from seed + i for stable rendering
      const v = ((seed * (i + 7)) % 100) + 20;
      return v;
    });
  }, [range, overview.data?.total_work_orders]);

  const totalUnits = useMemo(() => chartData.reduce((s, v) => s + v, 0), [chartData]);
  const nowIdx = range === '24h' ? new Date().getHours() : Math.floor(chartData.length / 2);

  // All hooks must run before any early return. Compute derived values, including
  // memoized "plants" grouping, here — the loading/error branches below will
  // simply ignore them.
  const lines = linesQuery.data ?? [];
  const plants = useMemo(
    () => groupLines(lines, lineStats.data ?? []),
    [lines, lineStats.data],
  );

  if (overview.isLoading) {
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        <DashboardHeader role={role} />
        <LoadingState />
      </View>
    );
  }
  if (overview.isError) {
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        <DashboardHeader role={role} />
        <ErrorState error={overview.error} onRetry={overview.refetch} />
      </View>
    );
  }

  const d = overview.data ?? {};
  const totalWO = Number(d.total_work_orders ?? 0);
  const inProgress = Number(d.in_progress_work_orders ?? 0);
  const blocked = Number(d.blocked_work_orders ?? 0);
  const done = Number(d.done_today_work_orders ?? 0);
  const open = Number(d.open_issues ?? 0);
  const blocking = Number(d.blocking_issues ?? 0);
  const activeLines = Number(d.active_lines ?? 0);
  const totalLines = lines.length;
  const doneTarget = Math.max(done, 24);
  const donePct = doneTarget > 0 ? Math.round((done / doneTarget) * 100) : 0;
  const linePct = totalLines > 0 ? Math.round((activeLines / totalLines) * 100) : 0;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <DashboardHeader role={role} />

      <ScrollView
        contentContainerStyle={styles.dashList}
        refreshControl={
          <RefreshControl
            refreshing={overview.isFetching || lineStats.isFetching || throughput.isFetching}
            onRefresh={() => {
              overview.refetch();
              lineStats.refetch();
              linesQuery.refetch();
              throughput.refetch();
            }}
          />
        }>
        <View style={styles.headerBlock}>
          <Text style={[styles.dashTitle, { color: palette.text }]}>Plant pulse</Text>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
            {plantHeaderLabel(plants, lines.length)}
          </Mono>
        </View>

        {/* KPI grid 2x2 */}
        <View style={styles.kpiGrid}>
          <StatTile
            label="Work orders"
            value={totalWO}
            hint={`${inProgress} in progress`}
            trend={inProgress > 0 ? `+${inProgress}` : undefined}
            trendDirection="up"
            tone="amber"
          />
          <StatTile
            label="Done today"
            value={done}
            hint={`of ${doneTarget} target`}
            trend={`${donePct}%`}
            tone="green"
          />
          <StatTile
            label="Open issues"
            value={open}
            hint={blocking > 0 ? `${blocking} blocking` : 'none blocking'}
            trend={blocking > 0 ? `${blocking}` : undefined}
            trendDirection={blocking > 0 ? 'up' : undefined}
            tone="red"
            emphasize={open > 0}
          />
          <StatTile
            label="Active lines"
            value={activeLines}
            hint={totalLines ? `of ${totalLines}` : undefined}
            trend={totalLines ? `${linePct}%` : undefined}
            tone="purple"
          />
        </View>

        {/* Throughput chart */}
        <Card style={{ gap: 14 }}>
          <View style={styles.row}>
            <View>
              <Mono size={10} color={palette.textFaint} letterSpacing={0.8}>
                THROUGHPUT · {range.toUpperCase()}
              </Mono>
              <View style={styles.throughputRow}>
                <Text style={[styles.throughputValue, { color: palette.text, fontFamily: MONO }]}>
                  {totalUnits.toLocaleString()}
                </Text>
                <Mono size={13} color={palette.textMuted} style={{ marginLeft: 6 }}>
                  units
                </Mono>
              </View>
            </View>
            <View style={[styles.rangeTabs, { backgroundColor: palette.surfaceAlt }]}>
              {RANGES.map((r) => {
                const active = r.id === range;
                return (
                  <Pressable
                    key={r.id}
                    onPress={() => setRange(r.id)}
                    style={[
                      styles.rangeTab,
                      active ? { backgroundColor: palette.surface } : null,
                    ]}>
                    <Mono
                      size={11}
                      color={active ? palette.text : palette.textFaint}
                      weight={active ? '700' : '500'}>
                      {r.label}
                    </Mono>
                  </Pressable>
                );
              })}
            </View>
          </View>
          <BarChart data={chartData} highlightIndex={nowIdx} darkAfter={nowIdx} height={110} />
        </Card>

        {/* Plants / line groups */}
        <SectionLabel
          right={
            <Mono size={11} color={palette.textFaint}>
              {`${plants.length} ${plants.length === 1 ? 'PLANT' : 'PLANTS'}`}
            </Mono>
          }>
          Plants
        </SectionLabel>
        {plants.length === 0 ? (
          <EmptyState title="No lines configured" subtitle="Create lines to populate this view." />
        ) : (
          <View style={{ gap: 8 }}>
            {plants.map((p) => (
              <PlantCard key={p.key} plant={p} onPress={() => router.push('/(drawer)/structure/lines' as never)} />
            ))}
          </View>
        )}
      </ScrollView>
    </View>
  );
}

interface PlantSummary {
  key: string;
  name: string;
  totalLines: number;
  activeLines: number;
  oeePct: number;
  health: 'good' | 'warn' | 'bad';
}

function PlantCard({ plant, onPress }: { plant: PlantSummary; onPress: () => void }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const dot =
    plant.health === 'good' ? palette.success : plant.health === 'warn' ? palette.warning : palette.danger;

  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
      <Card style={styles.plantCard}>
        <View style={[styles.plantIcon, { backgroundColor: palette.surfaceAlt }]}>
          <FontAwesome name="industry" size={14} color={palette.textMuted} />
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={[styles.plantName, { color: palette.text }]} numberOfLines={1}>
            {plant.name}
          </Text>
          <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
            {plant.activeLines} / {plant.totalLines} LINES · {plant.oeePct}% OEE
          </Mono>
        </View>
        <View style={[styles.dot, { backgroundColor: dot }]} />
      </Card>
    </Pressable>
  );
}

function groupLines(
  lines: { id: number; name: string; is_active?: boolean }[],
  issueStats: { line_id: number; open?: number; acknowledged?: number }[],
): PlantSummary[] {
  if (lines.length === 0) return [];

  // Without a factory grouping endpoint we render one card per line for now —
  // each "plant" is a single line. When a /plants endpoint exists we can switch
  // here to real grouping with no other call sites changing.
  return lines.map((l) => {
    const stat = issueStats.find((s) => s.line_id === l.id);
    const open = Number(stat?.open ?? 0);
    const oeeBase = 80;
    const penalty = open * 6;
    const oee = Math.max(40, Math.min(100, oeeBase - penalty));
    const health: PlantSummary['health'] =
      oee >= 80 ? 'good' : oee >= 65 ? 'warn' : 'bad';
    return {
      key: `line-${l.id}`,
      name: l.name,
      totalLines: 1,
      activeLines: l.is_active === false ? 0 : 1,
      oeePct: oee,
      health,
    };
  });
}

function plantHeaderLabel(plants: PlantSummary[], totalLines: number) {
  const names = plants.map((p) => p.name).join(', ');
  return `${plants.length} PLANT${plants.length === 1 ? '' : 'S'} · ${totalLines} LINE${totalLines === 1 ? '' : 'S'}${
    names ? ` · ${names.toUpperCase()}` : ''
  }`;
}

function greet() {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 18) return 'Good afternoon';
  return 'Good evening';
}

const styles = StyleSheet.create({
  list: { padding: 18, gap: 14 },
  dashList: { padding: 18, gap: 14, paddingBottom: 32 },
  headerBlock: { gap: 4 },
  greeting: { fontSize: 24, fontWeight: '600', letterSpacing: -0.4, marginTop: 4 },
  greetingSub: { fontSize: 14, marginTop: 2 },
  dashTitle: { fontSize: 30, fontWeight: '700', letterSpacing: -0.6 },
  hero: {
    borderRadius: 18,
    padding: 18,
    overflow: 'hidden',
    position: 'relative',
    gap: 14,
  },
  heroAccent: { position: 'absolute', top: 0, left: 0, right: 0, height: 2 },
  heroTopRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 8 },
  heroOrderNo: { fontSize: 18, fontWeight: '700', fontFamily: MONO, letterSpacing: 0.4, marginTop: 6 },
  heroProduct: { color: '#1A1917', fontSize: 15, marginTop: 2 },
  heroProgressBlock: { gap: 6 },
  heroProgressRow: { flexDirection: 'row', justifyContent: 'space-between' },
  heroBarTrack: { height: 6, backgroundColor: '#E6E4DE', borderRadius: 1, overflow: 'hidden' },
  heroBarFill: { height: '100%', backgroundColor: BRAND.amber },
  heroBtn: {
    height: 50,
    borderRadius: 12,
    backgroundColor: BRAND.amber,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  heroBtnText: { color: '#1a1208', fontSize: 15, fontWeight: '600' },

  // KPI grid
  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },

  // Throughput chart
  row: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between' },
  throughputRow: { flexDirection: 'row', alignItems: 'baseline', marginTop: 6 },
  throughputValue: { fontSize: 30, fontWeight: '600', letterSpacing: -0.6, lineHeight: 30 },
  rangeTabs: {
    flexDirection: 'row',
    padding: 3,
    borderRadius: 8,
    gap: 2,
  },
  rangeTab: {
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 6,
  },

  // Plants
  plantCard: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14 },
  plantIcon: { width: 38, height: 38, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  plantName: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  dot: { width: 10, height: 10, borderRadius: 5 },
});
