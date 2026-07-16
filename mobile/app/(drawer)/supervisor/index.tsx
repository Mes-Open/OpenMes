/**
 * Supervisor Dashboard — a port of the web supervisor/Dashboard.jsx: a line
 * filter, a six-KPI grid (work-order + issue counts), two 30-day bar charts
 * (throughput and issues by type) and a recent-issues table. Geist White,
 * light-only v1.
 *
 * KPIs and recent issues are line-filtered; the two bar charts use the plant
 * -wide analytics hooks (throughput / issue-stats), which don't take a line
 * filter on mobile yet.
 */
import { format, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useAnalyticsOverview, useIssueStats, useThroughput } from '@/hooks/queries/useAnalyticsOverview';
import { useIssues } from '@/hooks/queries/useIssues';
import { useAdminLines } from '@/hooks/queries/useLines';
import type { Issue } from '@/types/api';

interface DailyProduction {
  date: string;
  total_produced: number | string;
}
interface IssueTypeCount {
  type_name: string;
  count: number | string;
}

function num(v: number | string | null | undefined): number {
  if (v == null) return 0;
  return typeof v === 'string' ? parseFloat(v) : v;
}

export default function SupervisorDashboardPage() {
  const router = useRouter();
  const { t } = useTranslation();

  const linesQ = useAdminLines({});
  const lines = linesQ.data ?? [];

  const [lineId, setLineId] = useState<number | null>(null);
  const effectiveLineId = lineId ?? lines[0]?.id ?? null;

  const statsQ = useAnalyticsOverview(effectiveLineId);
  const throughputQ = useThroughput(30);
  const issueStatsQ = useIssueStats(30);
  const issuesQ = useIssues(effectiveLineId ? { line_id: effectiveLineId } : {});

  const stats = statsQ.data ?? {};

  const throughput = useMemo(() => {
    const data = (throughputQ.data ?? {}) as { daily_production?: DailyProduction[]; average_daily_throughput?: number };
    const daily = data.daily_production ?? [];
    return {
      labels: daily.map((d) => {
        try {
          return format(parseISO(d.date), 'MMM d');
        } catch {
          return d.date;
        }
      }),
      values: daily.map((d) => num(d.total_produced)),
      average: data.average_daily_throughput ?? 0,
    };
  }, [throughputQ.data]);

  const issuesByType = useMemo(() => {
    const data = (issueStatsQ.data ?? {}) as { by_type?: IssueTypeCount[] };
    const byType = data.by_type ?? [];
    return {
      labels: byType.map((r) => r.type_name),
      values: byType.map((r) => num(r.count)),
    };
  }, [issueStatsQ.data]);

  const recentIssues = useMemo(() => (issuesQ.data ?? []).slice(0, 10), [issuesQ.data]);

  const loading = linesQ.isLoading && !linesQ.data;
  const error = linesQ.isError && !linesQ.data;

  const refetchAll = () => {
    linesQ.refetch();
    statsQ.refetch();
    throughputQ.refetch();
    issueStatsQ.refetch();
    issuesQ.refetch();
  };
  const refreshing = statsQ.isFetching || issuesQ.isFetching || throughputQ.isFetching;

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Supervisor Dashboard')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 180 }}>
          <Dropdown
            options={lines.map((l) => ({ value: String(l.id), label: l.name }))}
            value={effectiveLineId == null ? '' : String(effectiveLineId)}
            onChange={(v) => setLineId(v ? Number(v) : null)}
            placeholder={t('All lines')}
          />
        </View>
      </View>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState error={linesQ.error} onRetry={refetchAll} />
      ) : (
        <ScrollView
          contentContainerStyle={styles.body}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refetchAll} tintColor={colors.accent} />}>
          {/* KPI grid */}
          <View style={styles.kpiGrid}>
            <Kpi label={t('Total WOs')} value={stats.total_work_orders} />
            <Kpi label={t('Active')} value={stats.active_work_orders} accent={colors.accent} />
            <Kpi label={t('Completed')} value={stats.completed_work_orders} accent={colors.running} />
            <Kpi label={t('Blocked')} value={stats.blocked_work_orders} accent={colors.blocked} />
            <Kpi label={t('Open Issues')} value={stats.open_issues} accent={colors.downtime} />
            <Kpi label={t('Blocking')} value={stats.blocking_issues} accent={colors.blocked} />
          </View>

          {/* Charts */}
          <View style={styles.card}>
            <Text style={styles.cardTitle}>
              {t('Throughput (30 days)')} · {t('avg')} {throughput.average}
            </Text>
            <BarList labels={throughput.labels} values={throughput.values} color={colors.accent} emptyLabel={t('No data.')} />
          </View>

          <View style={styles.card}>
            <Text style={styles.cardTitle}>{t('Issues by type (30 days)')}</Text>
            <BarList labels={issuesByType.labels} values={issuesByType.values} color={colors.downtime} emptyLabel={t('No data.')} />
          </View>

          {/* Recent issues */}
          <View style={styles.card}>
            <Text style={styles.cardTitle}>{t('Recent issues')}</Text>
            <View style={[styles.row, styles.headerRow]}>
              <HCell flex={1}>{t('Issue')}</HCell>
              <HCell w={70}>{t('WO')}</HCell>
              <HCell w={84}>{t('Status')}</HCell>
            </View>
            {recentIssues.map((issue) => (
              <IssueRow key={issue.id} issue={issue} onPress={() => router.push(`/(drawer)/issues/${issue.id}` as never)} />
            ))}
            {recentIssues.length === 0 ? <Text style={styles.empty}>{t('No issues.')} 🎉</Text> : null}
          </View>
        </ScrollView>
      )}
    </View>
  );
}

function Kpi({ label, value, accent }: { label: string; value?: number; accent?: string }) {
  return (
    <View style={styles.kpi}>
      <Mono size={9.5} color={colors.faint} letterSpacing={0.8}>
        {label.toUpperCase()}
      </Mono>
      <Mono size={26} color={accent ?? colors.ink} weight="600" style={{ marginTop: 6, letterSpacing: -0.5 }}>
        {value ?? 0}
      </Mono>
    </View>
  );
}

function BarList({
  labels,
  values,
  color,
  emptyLabel,
}: {
  labels: string[];
  values: number[];
  color: string;
  emptyLabel: string;
}) {
  if (labels.length === 0) return <Text style={styles.empty}>{emptyLabel}</Text>;
  const max = Math.max(...values, 1);
  return (
    <View style={{ gap: 12, marginTop: 4 }}>
      {labels.map((label, i) => (
        <View key={`${label}-${i}`} style={styles.barRow}>
          <Text style={styles.barLabel} numberOfLines={1}>
            {label}
          </Text>
          <View style={styles.barTrack}>
            <View style={[styles.barFill, { width: `${(values[i] / max) * 100}%`, backgroundColor: color }]} />
          </View>
          <Mono size={12} color={colors.muted} style={styles.barValue}>
            {values[i]}
          </Mono>
        </View>
      ))}
    </View>
  );
}

function IssueRow({ issue, onPress }: { issue: Issue; onPress: () => void }) {
  const title = issue.issue_type?.name ?? issue.description ?? `#${issue.id}`;
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, styles.dataRow, { opacity: pressed ? 0.6 : 1 }]}>
      <View style={{ flex: 1 }}>
        <Text numberOfLines={1} style={styles.issueTitle}>
          {title}
        </Text>
      </View>
      <View style={{ width: 70 }}>
        <Mono size={11} color={colors.muted} numberOfLines={1}>
          {issue.work_order?.order_no ?? '—'}
        </Mono>
      </View>
      <View style={{ width: 84 }}>
        <StatusPill status={issue.status} />
      </View>
    </Pressable>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>
        {String(children).toUpperCase()}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  body: { padding: 16, gap: 14, paddingBottom: 32 },

  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  kpi: {
    flexGrow: 1,
    flexBasis: '15%',
    minWidth: 100,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 14,
  },

  card: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 16 },
  cardTitle: { fontSize: 14, fontFamily: fonts.sans.native.semibold, color: colors.ink, marginBottom: 12 },

  barRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  barLabel: { width: 72, fontSize: 12.5, fontFamily: fonts.sans.native.regular, color: colors.muted, textAlign: 'right' },
  barTrack: { flex: 1, height: 7, borderRadius: 20, backgroundColor: colors.chip, overflow: 'hidden' },
  barFill: { height: 7, borderRadius: 20 },
  barValue: { width: 40 },

  row: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 11, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  issueTitle: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, paddingVertical: 14 },
});
