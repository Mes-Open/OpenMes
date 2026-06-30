import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAnalyticsOverview } from '@/hooks/queries/useAnalyticsOverview';
import { useIssues } from '@/hooks/queries/useIssues';
import { useLines } from '@/hooks/queries/useUsers';
import { useOee } from '@/hooks/queries/useOee';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import type { Issue, Line, WorkOrder } from '@/types/api';

type Palette = typeof Colors.light;

function num(v: number | string | null | undefined): number {
  if (v == null) return 0;
  return typeof v === 'string' ? parseFloat(v) : v;
}

function statusOf(wo: WorkOrder | null | undefined): 'running' | 'paused' | 'blocked' | 'idle' {
  if (!wo) return 'idle';
  if (wo.status === 'IN_PROGRESS') return 'running';
  if (wo.status === 'BLOCKED') return 'blocked';
  if (wo.status === 'PAUSED') return 'paused';
  return 'idle';
}

export function TabletPlantWall() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const overviewQ = useAnalyticsOverview();
  const linesQ = useLines();
  const oeeQ = useOee({});
  const issuesQ = useIssues({ status: 'OPEN' });
  const workOrdersQ = useWorkOrders({});

  const lines: Line[] = linesQ.data ?? [];
  const issues: Issue[] = issuesQ.data ?? [];

  // Plant OEE — average of latest OEE per line.
  const plantOee = useMemo(() => {
    const recs = oeeQ.data ?? [];
    const latestByLine = new Map<number, number>();
    for (const r of recs) {
      const v = num(r.oee_pct);
      if (v <= 0) continue;
      const prev = latestByLine.get(r.line_id);
      if (prev == null || new Date(r.record_date) > new Date(prev)) latestByLine.set(r.line_id, v);
    }
    const vals = Array.from(latestByLine.values());
    if (vals.length === 0) return null;
    return vals.reduce((a, b) => a + b, 0) / vals.length;
  }, [oeeQ.data]);

  // Throughput chart — backend's /analytics/throughput response shape isn't
  // typed yet, so we synthesize a 24h bar series from the total WO count just
  // like the phone supervisor dashboard. TODO(api/throughput-hourly): swap for
  // a real hourly time series.
  const throughputBars = useMemo(() => {
    const seed = Number(overviewQ.data?.total_work_orders ?? 12);
    return Array.from({ length: 48 }).map((_, i) => ((seed * (i + 7)) % 100) + 20);
  }, [overviewQ.data?.total_work_orders]);
  const throughputTotal = throughputBars.reduce<number>((a, b) => a + b, 0);
  const throughputMax = Math.max(1, ...throughputBars);

  // KPI tile values.
  const overview = overviewQ.data;
  const totalWoCount = overview?.total_work_orders ?? (workOrdersQ.data?.length ?? 0);
  const inProgressCount =
    overview?.in_progress_work_orders ??
    (workOrdersQ.data ?? []).filter((w) => w.status === 'IN_PROGRESS').length;
  const openIssuesCount = issues.length;
  const blockingIssuesCount = issues.filter((i) => i.issue_type?.is_blocking).length;
  const doneTodayCount = overview?.done_today_work_orders ?? null;
  // Per-line summaries for the tile grid.
  const lineSummaries = lines.map((line) => {
    const wos = (workOrdersQ.data ?? []).filter((w) => w.line_id === line.id);
    const active = wos.find((w) => w.status === 'IN_PROGRESS') ?? wos[0] ?? null;
    const planned = num(active?.planned_qty);
    const produced = num(active?.produced_qty);
    const pct = planned > 0 ? Math.min(100, Math.round((produced / planned) * 100)) : 0;
    return { line, status: statusOf(active), pct };
  });

  const styles = useMemo(() => makeStyles(palette), [palette]);

  return (
    <TabletShell
      dark={scheme === 'dark'}
      eyebrow="PLANT WALL · WARSAW MOKOTÓW"
      title="Live operations"
      right={
        <>
          <Mono size={11} color={palette.textFaint}>
            UPDATED {overviewQ.isFetching ? 'NOW' : '2S AGO'}
          </Mono>
          <View style={[styles.liveDot, { backgroundColor: palette.success }]} />
        </>
      }>
      {/* KPI strip */}
      <View style={styles.kpiStrip}>
        <BigKPI palette={palette} styles={styles}
          label="OEE"
          value={plantOee != null ? plantOee.toFixed(0) : '—'}
          suffix="%"
          trend={plantOee != null && plantOee >= 75 ? '+' : null}
          color={
            plantOee == null
              ? palette.textFaint
              : plantOee >= 85
              ? palette.success
              : plantOee >= 60
              ? BRAND.amber
              : palette.danger
          }
        />
        <BigKPI palette={palette} styles={styles}
          label="THROUGHPUT"
          value={throughputTotal.toLocaleString('en-US')}
          sub="units · 24h"
          trend="+"
        />
        <BigKPI palette={palette} styles={styles}
          label="WORK ORDERS"
          value={String(totalWoCount)}
          sub={`${inProgressCount} in progress`}
        />
        <BigKPI palette={palette} styles={styles}
          label="OPEN ISSUES"
          value={String(openIssuesCount)}
          sub={blockingIssuesCount > 0 ? `${blockingIssuesCount} blocking` : 'all clear'}
          color={openIssuesCount > 0 ? palette.danger : palette.success}
          down={blockingIssuesCount > 0}
        />
        <BigKPI palette={palette} styles={styles}
          label="DONE TODAY"
          value={doneTodayCount != null ? String(doneTodayCount) : '—'}
          sub={totalWoCount > 0 ? `of ${totalWoCount}` : ''}
          color={palette.success}
        />
      </View>

      <View style={styles.body}>
        {/* LEFT — Throughput chart + line tiles */}
        <View style={styles.leftCol}>
          <View style={styles.chartCard}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
              <View>
                <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
                  THROUGHPUT · 24H
                </Mono>
                <Mono size={26} color={palette.text} weight="600" style={{ marginTop: 4 }}>
                  {throughputTotal.toLocaleString('en-US')}
                  <Text style={{ fontSize: 14, color: palette.textFaint }}>{' '}units</Text>
                </Mono>
              </View>
              {/* Period toggle — matches the design (24H active, others muted).
                  Static for now; backend only exposes 24h throughput today. */}
              <View style={styles.periodRow}>
                {(['24H', '7D', '30D'] as const).map((p, i) => (
                  <View
                    key={p}
                    style={[
                      styles.periodChip,
                      {
                        backgroundColor: i === 0 ? palette.surfaceAlt : 'transparent',
                        borderColor: i === 0 ? palette.border : 'transparent',
                      },
                    ]}>
                    <Mono size={11} color={i === 0 ? palette.text : palette.textFaint}>
                      {p}
                    </Mono>
                  </View>
                ))}
              </View>
            </View>
            <View style={styles.barsRow}>
              {throughputBars.map((v, i) => (
                <View
                  key={i}
                  style={{
                    flex: 1,
                    height: `${Math.max(8, (v / throughputMax) * 100)}%`,
                    backgroundColor:
                      i >= throughputBars.length - 4
                        ? BRAND.amber
                        : i > throughputBars.length - 12
                        ? palette.text
                        : palette.border,
                    borderRadius: 1.5,
                  }}
                />
              ))}
            </View>
            <View style={styles.barsAxis}>
              {['00:00', '06:00', '12:00', '18:00', 'NOW'].map((t) => (
                <Mono key={t} size={10} color={palette.textFaint}>{t}</Mono>
              ))}
            </View>
          </View>

          <View style={styles.lineGrid}>
            {lineSummaries.slice(0, 4).map((s) => (
              <LineTile key={s.line.id} line={s.line} pct={s.pct} status={s.status} palette={palette} styles={styles} />
            ))}
          </View>
        </View>

        {/* RIGHT — Issues + shift */}
        <View style={styles.rightCol}>
          <View style={styles.issuesCard}>
            <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>ISSUES · OPEN</Mono>
            <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 8, marginTop: 6 }}>
              <Mono
                size={32}
                weight="600"
                color={openIssuesCount > 0 ? palette.danger : palette.success}
                letterSpacing={-0.5}>
                {openIssuesCount}
              </Mono>
              <Mono size={12} color={palette.textMuted}>
                {blockingIssuesCount} blocking ·{' '}
                {issues.filter((i) => i.acknowledged_at).length} ack'd
              </Mono>
            </View>
            <View style={[styles.divider, { backgroundColor: palette.border }]} />
            <View style={{ gap: 8 }}>
              {issues.slice(0, 4).map((i) => (
                <Pressable
                  key={i.id}
                  onPress={() => router.push(`/issues/${i.id}` as never)}
                  style={({ pressed }) => [styles.miniIssue, { opacity: pressed ? 0.8 : 1 }]}>
                  <View
                    style={[
                      styles.miniIssueRail,
                      {
                        backgroundColor: i.issue_type?.is_blocking ? palette.danger : BRAND.amber,
                      },
                    ]}
                  />
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Mono size={10} color={palette.textFaint}>
                      {(i.line?.name ?? '—').toUpperCase()}
                      {i.acknowledged_at ? '  · ● ACK' : ''}
                    </Mono>
                    <Text
                      style={{ color: palette.text, fontSize: 12, marginTop: 2 }}
                      numberOfLines={1}>
                      {i.description ?? i.issue_type?.name ?? 'Issue'}
                    </Text>
                  </View>
                  <Mono size={11} color={palette.textFaint}>
                    {(() => {
                      try {
                        return i.created_at
                          ? format(parseISO(i.created_at), 'HH:mm')
                          : '';
                      } catch {
                        return '';
                      }
                    })()}
                  </Mono>
                </Pressable>
              ))}
              {issues.length === 0 ? (
                <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 12 }}>
                  ALL CLEAR
                </Mono>
              ) : null}
            </View>
          </View>

          <View style={styles.shiftCard}>
            <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>SHIFT · A · 06–14</Mono>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 10 }}>
              <View style={{ flexDirection: 'row' }}>
                {['MK', 'AP', 'PW', 'TB'].map((i, idx) => (
                  <View
                    key={i}
                    style={[
                      styles.avatar,
                      {
                        marginLeft: idx === 0 ? 0 : -8,
                        borderColor: palette.surface,
                      },
                    ]}>
                    <Text style={styles.avatarText}>{i}</Text>
                  </View>
                ))}
              </View>
              <Mono size={13} color={palette.text}>4 / 5</Mono>
            </View>
            {/* Operators on shift would come from useOperatorsOnShift; mocked for now. */}
            <Mono size={11} color={palette.danger} style={{ marginTop: 8 }}>
              ● 1 no-show
            </Mono>
          </View>
        </View>
      </View>

      {/* Bottom — Schedule timeline strip */}
      <View style={styles.timelineCard}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
            SHIFT TIMELINE · A-SHIFT 06–14
          </Mono>
          <Mono size={11} color={BRAND.amber}>NOW · {format(new Date(), 'HH:mm')}</Mono>
        </View>
        <TimelineStrip lines={lines.slice(0, 4)} workOrders={workOrdersQ.data ?? []} palette={palette} styles={styles} />
      </View>
    </TabletShell>
  );
}

function BigKPI({
  label,
  value,
  sub,
  suffix,
  trend,
  color,
  down,
  palette,
  styles,
}: {
  label: string;
  value: string;
  sub?: string;
  suffix?: string;
  trend?: string | null;
  color?: string;
  down?: boolean;
  palette: Palette;
  styles: WallStyles;
}) {
  return (
    <View style={styles.kpi}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>{label}</Mono>
        {trend ? (
          <View
            style={[
              styles.kpiTrend,
              { backgroundColor: down ? palette.dangerSoft : palette.successSoft },
            ]}>
            <Mono size={10} color={down ? palette.danger : palette.success}>{trend}</Mono>
          </View>
        ) : null}
      </View>
      <Mono
        size={32}
        weight="600"
        color={color ?? palette.text}
        letterSpacing={-0.6}
        style={{ marginTop: 8, lineHeight: 34 }}>
        {value}
        {suffix ? <Text style={{ fontSize: 18, color: palette.textFaint }}>{suffix}</Text> : null}
      </Mono>
      {sub ? (
        <Mono size={11} color={palette.textMuted} style={{ marginTop: 6 }}>
          {sub}
        </Mono>
      ) : null}
    </View>
  );
}

function LineTile({
  line,
  pct,
  status,
  palette,
  styles,
}: {
  line: Line;
  pct: number;
  status: 'running' | 'paused' | 'blocked' | 'idle';
  palette: Palette;
  styles: WallStyles;
}) {
  const sc =
    status === 'running'
      ? palette.success
      : status === 'paused'
      ? BRAND.amber
      : status === 'blocked'
      ? palette.danger
      : palette.textFaint;
  return (
    <View style={styles.lineTile}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
        <View>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
            {line.code ?? `L-${String(line.id).padStart(2, '0')}`}
          </Mono>
          <Text
            style={{ color: palette.text, fontSize: 14, fontWeight: '600', marginTop: 4 }}
            numberOfLines={1}>
            {line.name}
          </Text>
        </View>
        <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: sc, marginTop: 6 }} />
      </View>
      <Mono size={22} weight="600" color={palette.text} style={{ marginTop: 12 }}>
        {pct}
        <Text style={{ fontSize: 13, color: palette.textFaint }}>%</Text>
      </Mono>
      <View style={{ height: 4, backgroundColor: palette.surfaceAlt, borderRadius: 2, marginTop: 8, overflow: 'hidden' }}>
        <View style={{ width: `${pct}%`, height: '100%', backgroundColor: sc }} />
      </View>
    </View>
  );
}

function TimelineStrip({
  lines,
  workOrders,
  palette,
  styles,
}: {
  lines: Line[];
  workOrders: WorkOrder[];
  palette: Palette;
  styles: WallStyles;
}) {
  // Simple horizontal strip mirroring the design's 9-hour A-shift band.
  // Backend doesn't expose schedule blocks yet for the wall, so we lay out one
  // segment per line's currently-active WO.
  // TODO(api/schedule-blocks): swap mock segments for real scheduled blocks.
  const hours = ['06', '07', '08', '09', '10', '11', '12', '13', '14'];
  const total = hours.length - 1;
  const nowHour = new Date().getHours();
  const nowPos = Math.max(0, Math.min(total, nowHour - 6));

  return (
    <View>
      <View style={{ flexDirection: 'row', paddingLeft: 48, marginBottom: 6 }}>
        {hours.map((h) => (
          <View key={h} style={{ flex: 1 }}>
            <Mono size={10} color={palette.textFaint}>{h}</Mono>
          </View>
        ))}
      </View>
      {lines.map((line) => {
        const wos = workOrders.filter((w) => w.line_id === line.id);
        const active = wos.find((w) => w.status === 'IN_PROGRESS') ?? wos[0];
        const color = active?.status === 'BLOCKED'
          ? palette.danger
          : active?.status === 'PAUSED'
          ? BRAND.amber
          : active?.status === 'IN_PROGRESS'
          ? palette.success
          : palette.border;
        return (
          <View key={line.id} style={styles.timelineRow}>
            <Mono size={11} color={palette.textMuted} style={{ width: 48 }}>
              {line.code ?? `L-${String(line.id).padStart(2, '0')}`}
            </Mono>
            <View style={styles.timelineTrack}>
              {hours.slice(1).map((_, i) => (
                <View
                  key={i}
                  style={[
                    styles.timelineGrid,
                    { left: `${((i + 1) / total) * 100}%` },
                  ]}
                />
              ))}
              {active ? (
                <View
                  style={[
                    styles.timelineSeg,
                    {
                      backgroundColor: color,
                      left: `${(nowPos / total) * 100 - 12}%`,
                      width: '30%',
                    },
                  ]}>
                  <Mono
                    size={9.5}
                    color={color === BRAND.amber ? '#1a1208' : '#fff'}
                    weight="600"
                    letterSpacing={0.3}
                    numberOfLines={1}>
                    {active.order_no}
                  </Mono>
                </View>
              ) : null}
              <View
                style={[
                  styles.nowLine,
                  { left: `${(nowPos / total) * 100}%`, backgroundColor: BRAND.amber },
                ]}
              />
            </View>
          </View>
        );
      })}
    </View>
  );
}

// StyleSheet is theme-bound: the surface and border colors switch with the
// active palette, so we build it lazily inside the component via useMemo and
// pass it down to sub-components instead of evaluating once at module load.
type WallStyles = ReturnType<typeof makeStyles>;

function makeStyles(palette: Palette) {
  return StyleSheet.create({
  liveDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    boxShadow: '0px 0px 6px rgba(62, 207, 142, 0.4)',
    elevation: 4,
  },
  kpiStrip: { flexDirection: 'row', gap: 12, marginBottom: 16 },
  kpi: {
    flex: 1,
    backgroundColor: palette.surface,
    borderColor: palette.border,
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
  },
  kpiTrend: { paddingVertical: 3, paddingHorizontal: 7, borderRadius: 4 },

  body: { flex: 1, flexDirection: 'row', gap: 16, marginBottom: 16, minHeight: 0 },
  leftCol: { flex: 2, gap: 12, minHeight: 0 },
  rightCol: { flex: 1, gap: 12, minHeight: 0 },

  chartCard: {
    flex: 1,
    backgroundColor: palette.surface,
    borderColor: palette.border,
    borderWidth: 1,
    borderRadius: 16,
    padding: 18,
  },
  barsRow: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 4,
    marginTop: 14,
  },
  barsAxis: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 },
  periodRow: { flexDirection: 'row', gap: 6 },
  periodChip: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
  },

  lineGrid: { flexDirection: 'row', gap: 12 },
  lineTile: {
    flex: 1,
    backgroundColor: palette.surface,
    borderColor: palette.border,
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
  },

  issuesCard: {
    flex: 1,
    backgroundColor: palette.surface,
    borderColor: palette.border,
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
  },
  divider: { height: StyleSheet.hairlineWidth, marginVertical: 14 },
  miniIssue: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  miniIssueRail: { width: 4, height: 32, borderRadius: 2 },

  shiftCard: {
    backgroundColor: palette.surface,
    borderColor: palette.border,
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
  },
  avatar: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: BRAND.amber,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
  },
  avatarText: { color: '#1a1208', fontFamily: MONO, fontSize: 11, fontWeight: '700' },

  timelineCard: {
    backgroundColor: palette.surface,
    borderColor: palette.border,
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
  },
  timelineRow: { flexDirection: 'row', alignItems: 'center', height: 26, marginBottom: 4 },
  timelineTrack: {
    flex: 1,
    height: 22,
    backgroundColor: palette.surfaceAlt,
    borderRadius: 4,
    position: 'relative',
    overflow: 'hidden',
  },
  timelineGrid: { position: 'absolute', top: 0, bottom: 0, width: 1, backgroundColor: palette.border },
  timelineSeg: {
    position: 'absolute',
    top: 3,
    bottom: 3,
    borderRadius: 3,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 6,
    overflow: 'hidden',
  },
  nowLine: {
    position: 'absolute',
    top: -2,
    bottom: -2,
    width: 2,
    boxShadow: ' 0px 0px 4px rgba(245, 144, 33, 0.8)',
  },
  });
}
