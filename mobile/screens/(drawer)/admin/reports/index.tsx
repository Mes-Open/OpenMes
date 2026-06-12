import { format, subDays } from 'date-fns';
import { useState } from 'react';
import * as WebBrowser from 'expo-web-browser';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useLines } from '@/hooks/queries/useUsers';
import {
  useBatchCompletion,
  useDowntimeReport,
  useProductionSummary,
} from '@/hooks/queries/useReports';
import { reportExportCsvUrl, type ReportType } from '@/api/reports';

const TABS: { key: ReportType; label: string }[] = [
  { key: 'production_summary', label: 'Production' },
  { key: 'batch_completion', label: 'Batches' },
  { key: 'downtime', label: 'Downtime' },
];

interface Filters {
  start_date: string;
  end_date: string;
  line_id?: number;
}

export function ReportsScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const [tab, setTab] = useState<ReportType>('production_summary');
  const today = format(new Date(), 'yyyy-MM-dd');
  const monthAgo = format(subDays(new Date(), 30), 'yyyy-MM-dd');
  const [startDate, setStartDate] = useState(monthAgo);
  const [endDate, setEndDate] = useState(today);
  const [lineId, setLineId] = useState<number | null>(null);
  const [submitted, setSubmitted] = useState(true);

  const filters: Filters | null = submitted
    ? { start_date: startDate, end_date: endDate, line_id: lineId ?? undefined }
    : null;

  const linesQuery = useLines();
  const lines = linesQuery.data ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title="Reports"
        subtitle={`ANALYTICS · ${startDate} → ${endDate}`}
      />
      <ScrollView
        style={{ flex: 1, backgroundColor: palette.background }}
        contentContainerStyle={styles.container}>

      <View style={styles.tabs}>
        {TABS.map((t) => {
          const active = t.key === tab;
          return (
            <Pressable
              key={t.key}
              onPress={() => setTab(t.key)}
              style={[
                styles.tab,
                {
                  backgroundColor: active ? '#241a08' : 'transparent',
                  borderColor: active ? BRAND.amber : palette.border,
                },
              ]}>
              <Text
                style={{
                  fontSize: 12,
                  fontWeight: '600',
                  color: active ? BRAND.amber : palette.textMuted,
                }}>
                {t.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Filters</SectionLabel>
        <View style={styles.dateRow}>
          <Field
            label="Start"
            value={startDate}
            onChangeText={setStartDate}
            autoCapitalize="none"
            autoCorrect={false}
            placeholder="YYYY-MM-DD"
            style={{ flex: 1 } as never}
          />
          <Field
            label="End"
            value={endDate}
            onChangeText={setEndDate}
            autoCapitalize="none"
            autoCorrect={false}
            placeholder="YYYY-MM-DD"
            style={{ flex: 1 } as never}
          />
        </View>
        {lines.length > 0 ? (
          <View style={{ gap: 8 }}>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.8}>LINE</Mono>
            <View style={styles.lineChips}>
              <Chip label="All lines" active={lineId == null} onPress={() => setLineId(null)} />
              {lines.map((l) => (
                <Chip
                  key={l.id}
                  label={l.name}
                  active={l.id === lineId}
                  onPress={() => setLineId(l.id)}
                />
              ))}
            </View>
          </View>
        ) : null}
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <Button
            title="Run report"
            onPress={() => setSubmitted(true)}
            style={{ flex: 1 }}
            leftIcon={<FontAwesome name="bolt" size={13} color="#1a1208" />}
          />
          <Button
            title="Export CSV"
            variant="outline"
            leftIcon={<FontAwesome name="download" size={13} color={palette.text} />}
            onPress={() =>
              WebBrowser.openBrowserAsync(
                reportExportCsvUrl(tab, {
                  start_date: startDate,
                  end_date: endDate,
                  line_id: lineId ?? undefined,
                }),
              )
            }
            style={{ flex: 1 }}
          />
        </View>
      </Card>

      {tab === 'production_summary' ? <ProductionSummary filters={filters} /> : null}
      {tab === 'batch_completion' ? <BatchCompletion filters={filters} /> : null}
      {tab === 'downtime' ? <Downtime filters={filters} /> : null}
      </ScrollView>
    </View>
  );
}

function Chip({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Pressable
      onPress={onPress}
      style={[
        styles.chip,
        {
          backgroundColor: active ? palette.surfaceInverse : 'transparent',
          borderColor: active ? palette.surfaceInverse : palette.border,
        },
      ]}>
      <Text
        style={{
          fontSize: 12,
          fontWeight: '600',
          color: active ? (scheme === 'dark' ? '#171715' : '#fff') : palette.textMuted,
        }}>
        {label}
      </Text>
    </Pressable>
  );
}

function ProductionSummary({ filters }: { filters: Filters | null }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const q = useProductionSummary(filters);
  if (!filters) return null;
  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const d = q.data;
  const pct = d.production.completion_rate ?? 0;

  return (
    <View style={{ gap: 14 }}>
      {/* Hero block */}
      <View style={[styles.hero, { backgroundColor: palette.surfaceInverse }]}>
        <Mono size={10} color="#9a9aa2" letterSpacing={0.6}>
          {d.line.toUpperCase()}
        </Mono>
        <Mono size={10} color="#9a9aa2" letterSpacing={0.6} style={{ marginTop: 4 }}>
          {d.period.start} → {d.period.end}
        </Mono>
        <View style={styles.heroNumRow}>
          <Text style={styles.heroNum}>{pct}</Text>
          <Text style={styles.heroUnit}>%</Text>
        </View>
        <Mono size={11} color="#9a9aa2" style={{ marginTop: 4 }}>
          {fmt(d.production.total_produced)}/{fmt(d.production.total_planned)} PRODUCED
        </Mono>
        <View style={styles.heroBar}>
          <View style={[styles.heroBarFill, { width: `${pct}%` }]} />
        </View>
      </View>

      <View style={styles.grid}>
        <Kpi label="Total" value={d.work_orders.total} />
        <Kpi label="Completed" value={d.work_orders.completed} tone="success" />
        <Kpi label="In progress" value={d.work_orders.in_progress} tone="primary" />
        <Kpi label={t('Not Started')} value={d.work_orders.pending} />
        <Kpi label="Blocked" value={d.work_orders.blocked} tone="danger" />
        <Kpi label="Cancelled" value={d.work_orders.cancelled} />
      </View>

      {d.by_product_type.length > 0 ? (
        <Card style={{ gap: 10 }}>
          <SectionLabel>By product type</SectionLabel>
          {d.by_product_type.map((pt, idx) => {
            const ptPct = pt.planned_qty > 0 ? Math.round((pt.produced_qty / pt.planned_qty) * 100) : 0;
            return (
              <View key={idx} style={{ gap: 6 }}>
                <View style={styles.row}>
                  <Text style={{ flex: 1, color: palette.text, fontWeight: '600', fontSize: 14 }}>
                    {pt.product_type}
                  </Text>
                  <Mono size={11} color={palette.textMuted}>
                    {fmt(pt.produced_qty)}/{fmt(pt.planned_qty)} · {ptPct}%
                  </Mono>
                </View>
                <View style={[styles.miniBar, { backgroundColor: palette.surfaceAlt }]}>
                  <View style={[styles.miniBarFill, { width: `${ptPct}%`, backgroundColor: BRAND.amber }]} />
                </View>
              </View>
            );
          })}
        </Card>
      ) : null}
    </View>
  );
}

function BatchCompletion({ filters }: { filters: Filters | null }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const q = useBatchCompletion(filters);
  if (!filters) return null;
  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const d = q.data;

  return (
    <View style={{ gap: 14 }}>
      <View style={styles.grid}>
        <Kpi label="Batches" value={d.summary.total_batches} />
        <Kpi label="Total produced" value={fmt(d.summary.total_produced)} tone="success" />
        <Kpi label="Avg batch size" value={fmt(d.summary.average_batch_size ?? 0)} tone="primary" />
      </View>
      <SectionLabel
        right={
          <Mono size={11} color={palette.textFaint}>
            {Math.min(50, d.batches.length)} OF {d.batches.length}
          </Mono>
        }>
        Batch list
      </SectionLabel>
      {d.batches.slice(0, 50).map((b) => (
        <Card key={b.batch_id}>
          <View style={styles.row}>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Mono size={11} color={palette.textFaint}>
                {b.work_order_no} · BATCH #{b.batch_id}
              </Mono>
              <Text style={{ color: palette.text, fontWeight: '600', fontSize: 14, marginTop: 3 }}>
                {b.product_type}
              </Text>
              <Mono size={11} color={palette.textFaint} style={{ marginTop: 4 }}>
                {b.line.toUpperCase()} · {fmt(b.produced_qty)}/{fmt(b.target_qty)} PCS
              </Mono>
            </View>
            {b.cycle_time_hours != null ? (
              <View style={[styles.timePill, { backgroundColor: palette.surfaceAlt }]}>
                <Mono size={10} color={palette.textFaint}>CYCLE</Mono>
                <Text style={[styles.timeValue, { color: palette.text, fontFamily: MONO }]}>
                  {b.cycle_time_hours}h
                </Text>
              </View>
            ) : null}
          </View>
        </Card>
      ))}
    </View>
  );
}

function Downtime({ filters }: { filters: Filters | null }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const q = useDowntimeReport(filters);
  if (!filters) return null;
  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const d = q.data;

  const maxHours = d.by_type.reduce((m, t) => Math.max(m, t.downtime_hours), 0);

  return (
    <View style={{ gap: 14 }}>
      <View style={styles.grid}>
        <Kpi label="Issues" value={d.summary.total_issues} />
        <Kpi label="Open" value={d.summary.open_issues} tone="danger" />
        <Kpi label="Resolved" value={d.summary.resolved_issues} tone="success" />
        <Kpi label="Downtime" value={`${d.summary.total_downtime_hours}h`} tone="warning" />
      </View>
      {d.by_type.length > 0 ? (
        <Card style={{ gap: 12 }}>
          <SectionLabel>Downtime by type</SectionLabel>
          {d.by_type.map((t, idx) => {
            const pct = maxHours > 0 ? (t.downtime_hours / maxHours) * 100 : 0;
            return (
              <View key={idx} style={{ gap: 6 }}>
                <View style={styles.row}>
                  <Text style={{ flex: 1, color: palette.text, fontWeight: '600', fontSize: 14 }}>
                    {t.type}
                  </Text>
                  <Mono size={11} color={palette.textMuted}>
                    {t.count} · {t.downtime_hours}H
                  </Mono>
                </View>
                <View style={[styles.miniBar, { backgroundColor: palette.surfaceAlt }]}>
                  <View
                    style={[
                      styles.miniBarFill,
                      { width: `${pct}%`, backgroundColor: palette.warning },
                    ]}
                  />
                </View>
              </View>
            );
          })}
        </Card>
      ) : null}
    </View>
  );
}

function Kpi({
  label,
  value,
  tone,
}: {
  label: string;
  value: number | string;
  tone?: 'danger' | 'success' | 'warning' | 'primary';
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const valueColor =
    tone === 'primary'
      ? palette.tint
      : tone === 'danger'
      ? palette.danger
      : tone === 'success'
      ? palette.success
      : tone === 'warning'
      ? palette.warning
      : palette.text;
  return (
    <Card style={styles.kpi}>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.8}>
        {label.toUpperCase()}
      </Mono>
      <Text style={[styles.kpiValue, { color: valueColor, fontFamily: MONO }]}>{value}</Text>
    </Card>
  );
}

function fmt(n: number) {
  return Math.round(n * 100) / 100;
}

const styles = StyleSheet.create({
  container: { padding: 18, gap: 14 },
  header: { gap: 4 },
  heading: { fontSize: 26, fontWeight: '600', letterSpacing: -0.5, marginTop: 4 },
  tabs: { flexDirection: 'row', gap: 8 },
  tab: {
    flex: 1,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
    alignItems: 'center',
  },
  dateRow: { flexDirection: 'row', gap: 8 },
  lineChips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  chip: { paddingVertical: 6, paddingHorizontal: 11, borderRadius: 999, borderWidth: 1 },
  hero: { borderRadius: 18, padding: 18 },
  heroNumRow: { flexDirection: 'row', alignItems: 'baseline', gap: 4, marginTop: 12 },
  heroNum: {
    color: '#fff',
    fontSize: 56,
    fontWeight: '500',
    fontFamily: MONO,
    letterSpacing: -2,
    lineHeight: 56,
  },
  heroUnit: { color: '#9a9aa2', fontSize: 22, fontFamily: MONO },
  heroBar: { height: 4, backgroundColor: '#26262d', borderRadius: 2, marginTop: 14, overflow: 'hidden' },
  heroBarFill: { height: '100%', backgroundColor: BRAND.amber },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  kpi: { flexBasis: '48%', flexGrow: 1, gap: 6 },
  kpiValue: { fontSize: 22, fontWeight: '600' },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  miniBar: { height: 4, borderRadius: 2, overflow: 'hidden' },
  miniBarFill: { height: '100%' },
  timePill: {
    alignItems: 'center',
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 8,
    gap: 2,
  },
  timeValue: { fontSize: 14, fontWeight: '600' },
});
