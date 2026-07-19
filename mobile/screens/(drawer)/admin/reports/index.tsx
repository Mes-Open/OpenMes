import { format, subDays } from 'date-fns';
import { useState } from 'react';
import { useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useLines } from '@/hooks/queries/useUsers';
import {
  useBatchCompletion,
  useDowntimeReport,
  useNetRequirementsReport,
  useNonConformanceReport,
  useProductionCostReport,
  useProductionSummary,
  useScrapReport,
} from '@/hooks/queries/useReports';
import { reportExportCsvUrl, type ReportType } from '@/api/reports';

type TabKey = ReportType | 'scrap' | 'non_conformance' | 'net_requirements' | 'production_cost';

const TABS: { key: TabKey; label: string }[] = [
  { key: 'production_summary', label: 'Production' },
  { key: 'batch_completion', label: 'Batches' },
  { key: 'downtime', label: 'Downtime' },
  { key: 'production_cost', label: 'Cost' },
  { key: 'scrap', label: 'Scrap' },
  { key: 'non_conformance', label: 'Non-conf.' },
  { key: 'net_requirements', label: 'Net req.' },
];

const EXPORTABLE: TabKey[] = ['production_summary', 'batch_completion', 'downtime'];

interface Filters {
  start_date: string;
  end_date: string;
  line_id?: number;
}

export function ReportsScreen() {
  const { t } = useTranslation();

  // A `?tab=` deep-link (from the sidebar's per-report entries) picks the
  // initial tab; otherwise default to the Production summary.
  const { tab: tabParam } = useLocalSearchParams<{ tab?: string }>();
  const initialTab = TABS.some((t) => t.key === tabParam) ? (tabParam as TabKey) : 'production_summary';
  const [tab, setTab] = useState<TabKey>(initialTab);
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
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Reports')}</Text>
      </View>
      <ScrollView style={styles.screen} contentContainerStyle={styles.container}>
        <View style={styles.tabs}>
          {TABS.map((tb) => {
            const active = tb.key === tab;
            return (
              <Pressable
                key={tb.key}
                onPress={() => setTab(tb.key)}
                style={[styles.tab, active ? styles.tabActive : styles.tabInactive]}>
                <Text style={[styles.tabLabel, { color: active ? '#FFFFFF' : colors.muted }]}>{t(tb.label)}</Text>
              </Pressable>
            );
          })}
        </View>

        <View style={[styles.card, { gap: 12 }]}>
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
              <Mono size={10} color={colors.faint} letterSpacing={0.8}>{t('Line').toUpperCase()}</Mono>
              <View style={styles.lineChips}>
                <Chip label={t('All lines')} active={lineId == null} onPress={() => setLineId(null)} />
                {lines.map((l) => (
                  <Chip key={l.id} label={l.name} active={l.id === lineId} onPress={() => setLineId(l.id)} />
                ))}
              </View>
            </View>
          ) : null}
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Button
              title="Run report"
              onPress={() => setSubmitted(true)}
              style={{ flex: 1 }}
              leftIcon={<FontAwesome name="bolt" size={13} color="#FFFFFF" />}
            />
            {EXPORTABLE.includes(tab) ? (
              <Button
                title="Export CSV"
                variant="outline"
                leftIcon={<FontAwesome name="download" size={13} color={colors.ink} />}
                onPress={() =>
                  WebBrowser.openBrowserAsync(
                    reportExportCsvUrl(tab as ReportType, {
                      start_date: startDate,
                      end_date: endDate,
                      line_id: lineId ?? undefined,
                    }),
                  )
                }
                style={{ flex: 1 }}
              />
            ) : null}
          </View>
        </View>

        {tab === 'production_summary' ? <ProductionSummary filters={filters} /> : null}
        {tab === 'batch_completion' ? <BatchCompletion filters={filters} /> : null}
        {tab === 'downtime' ? <Downtime filters={filters} /> : null}
        {tab === 'production_cost' ? <ProductionCostView filters={filters} /> : null}
        {tab === 'scrap' ? <ScrapReportView filters={filters} /> : null}
        {tab === 'non_conformance' ? <NonConformanceView filters={filters} /> : null}
        {tab === 'net_requirements' ? <NetRequirementsView filters={filters} /> : null}
      </ScrollView>
    </View>
  );
}

function Chip({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={[styles.chip, active ? styles.chipActive : styles.chipInactive]}>
      <Text style={[styles.chipLabel, { color: active ? '#FFFFFF' : colors.muted }]}>{label}</Text>
    </Pressable>
  );
}

function ProductionSummary({ filters }: { filters: Filters | null }) {
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
      <View style={styles.hero}>
        <Mono size={10} color="rgba(255,255,255,0.6)" letterSpacing={0.6}>{d.line.toUpperCase()}</Mono>
        <Mono size={10} color="rgba(255,255,255,0.6)" letterSpacing={0.6} style={{ marginTop: 4 }}>{d.period.start} → {d.period.end}</Mono>
        <View style={styles.heroNumRow}>
          <Text style={styles.heroNum}>{pct}</Text>
          <Text style={styles.heroUnit}>%</Text>
        </View>
        <Mono size={11} color="rgba(255,255,255,0.6)" style={{ marginTop: 4 }}>
          {fmt(d.production.total_produced)}/{fmt(d.production.total_planned)} {t('Produced').toUpperCase()}
        </Mono>
        <View style={styles.heroBar}>
          <View style={[styles.heroBarFill, { width: `${pct}%` }]} />
        </View>
      </View>

      <View style={styles.grid}>
        <Kpi label={t('Total')} value={d.work_orders.total} />
        <Kpi label={t('Completed')} value={d.work_orders.completed} tone="success" />
        <Kpi label={t('In progress')} value={d.work_orders.in_progress} tone="primary" />
        <Kpi label={t('Not Started')} value={d.work_orders.pending} />
        <Kpi label={t('Blocked')} value={d.work_orders.blocked} tone="danger" />
        <Kpi label={t('Cancelled')} value={d.work_orders.cancelled} />
      </View>

      {d.by_product_type.length > 0 ? (
        <View style={[styles.card, { gap: 10 }]}>
          <SectionLabel>By product type</SectionLabel>
          {d.by_product_type.map((pt, idx) => {
            const ptPct = pt.planned_qty > 0 ? Math.round((pt.produced_qty / pt.planned_qty) * 100) : 0;
            return (
              <View key={idx} style={{ gap: 6 }}>
                <View style={styles.row}>
                  <Text style={{ flex: 1, color: colors.ink, fontFamily: fonts.sans.native.semibold, fontSize: 14 }}>{pt.product_type}</Text>
                  <Mono size={11} color={colors.muted}>{fmt(pt.produced_qty)}/{fmt(pt.planned_qty)} · {ptPct}%</Mono>
                </View>
                <View style={styles.miniBar}>
                  <View style={[styles.miniBarFill, { width: `${ptPct}%`, backgroundColor: colors.accent }]} />
                </View>
              </View>
            );
          })}
        </View>
      ) : null}
    </View>
  );
}

function BatchCompletion({ filters }: { filters: Filters | null }) {
  const { t } = useTranslation();
  const q = useBatchCompletion(filters);
  if (!filters) return null;
  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const d = q.data;

  return (
    <View style={{ gap: 14 }}>
      <View style={styles.grid}>
        <Kpi label={t('Batches')} value={d.summary.total_batches} />
        <Kpi label={t('Total produced')} value={fmt(d.summary.total_produced)} tone="success" />
        <Kpi label={t('Avg batch size')} value={fmt(d.summary.average_batch_size ?? 0)} tone="primary" />
      </View>
      <SectionLabel right={<Mono size={11} color={colors.faint}>{Math.min(50, d.batches.length)} {t('of').toUpperCase()} {d.batches.length}</Mono>}>
        Batch list
      </SectionLabel>
      {d.batches.slice(0, 50).map((b) => (
        <View key={b.batch_id} style={styles.card}>
          <View style={styles.row}>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Mono size={11} color={colors.faint}>{b.work_order_no} · {t('Batch').toUpperCase()} #{b.batch_id}</Mono>
              <Text style={{ color: colors.ink, fontFamily: fonts.sans.native.semibold, fontSize: 14, marginTop: 3 }}>{b.product_type}</Text>
              <Mono size={11} color={colors.faint} style={{ marginTop: 4 }}>{b.line.toUpperCase()} · {fmt(b.produced_qty)}/{fmt(b.target_qty)} {t('pcs').toUpperCase()}</Mono>
            </View>
            {b.cycle_time_hours != null ? (
              <View style={styles.timePill}>
                <Mono size={10} color={colors.faint}>{t('Cycle').toUpperCase()}</Mono>
                <Mono size={14} color={colors.ink} weight="600">{b.cycle_time_hours}h</Mono>
              </View>
            ) : null}
          </View>
        </View>
      ))}
    </View>
  );
}

function Downtime({ filters }: { filters: Filters | null }) {
  const { t } = useTranslation();
  const q = useDowntimeReport(filters);
  if (!filters) return null;
  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const d = q.data;

  const maxHours = d.by_type.reduce((m, t) => Math.max(m, t.downtime_hours), 0);

  return (
    <View style={{ gap: 14 }}>
      <View style={styles.grid}>
        <Kpi label={t('Issues')} value={d.summary.total_issues} />
        <Kpi label={t('Open')} value={d.summary.open_issues} tone="danger" />
        <Kpi label={t('Resolved')} value={d.summary.resolved_issues} tone="success" />
        <Kpi label={t('Downtime')} value={`${d.summary.total_downtime_hours}h`} tone="warning" />
      </View>
      {d.by_type.length > 0 ? (
        <View style={[styles.card, { gap: 12 }]}>
          <SectionLabel>Downtime by type</SectionLabel>
          {d.by_type.map((tp, idx) => {
            const pct = maxHours > 0 ? (tp.downtime_hours / maxHours) * 100 : 0;
            return (
              <View key={idx} style={{ gap: 6 }}>
                <View style={styles.row}>
                  <Text style={{ flex: 1, color: colors.ink, fontFamily: fonts.sans.native.semibold, fontSize: 14 }}>{tp.type}</Text>
                  <Mono size={11} color={colors.muted}>{tp.count} · {tp.downtime_hours}H</Mono>
                </View>
                <View style={styles.miniBar}>
                  <View style={[styles.miniBarFill, { width: `${pct}%`, backgroundColor: colors.downtime }]} />
                </View>
              </View>
            );
          })}
        </View>
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
  const valueColor =
    tone === 'primary'
      ? colors.accent
      : tone === 'danger'
      ? colors.blocked
      : tone === 'success'
      ? colors.running
      : tone === 'warning'
      ? colors.downtime
      : colors.ink;
  return (
    <View style={[styles.card, styles.kpi]}>
      <Mono size={10} color={colors.faint} letterSpacing={0.8}>{label.toUpperCase()}</Mono>
      <Mono size={22} color={valueColor} weight="600">{value}</Mono>
    </View>
  );
}

function ScrapReportView({ filters }: { filters: Filters | null }) {
  const { t } = useTranslation();
  const q = useScrapReport(filters);
  if (!filters) return null;
  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const p = q.data.pareto;
  const reasons = p?.reasons ?? [];
  const max = Math.max(...reasons.map((r) => r.qty), 1);

  return (
    <View style={{ gap: 14 }}>
      <View style={styles.grid}>
        <Kpi label={t('Total scrap')} value={fmt(p?.total_qty ?? 0)} tone="danger" />
        <Kpi label={t('Scrap entries')} value={p?.total_entries ?? 0} />
        <Kpi label={t('Top reason')} value={reasons[0]?.name ?? '—'} />
      </View>
      <View style={[styles.card, { gap: 10 }]}>
        <SectionLabel>By reason</SectionLabel>
        {reasons.length === 0 ? (
          <Text style={styles.emptyText}>{t('No scrap in this period.')}</Text>
        ) : (
          reasons.map((r, idx) => (
            <View key={idx} style={{ gap: 6 }}>
              <View style={styles.row}>
                <Text style={{ flex: 1, color: colors.ink, fontFamily: fonts.sans.native.semibold, fontSize: 13 }}>{r.name}</Text>
                <Mono size={11} color={colors.muted}>{fmt(r.qty)}</Mono>
              </View>
              <View style={styles.miniBar}>
                <View style={[styles.miniBarFill, { width: `${(r.qty / max) * 100}%`, backgroundColor: colors.blocked }]} />
              </View>
            </View>
          ))
        )}
      </View>
    </View>
  );
}

function NonConformanceView({ filters }: { filters: Filters | null }) {
  const { t } = useTranslation();
  const q = useNonConformanceReport(filters);
  if (!filters) return null;
  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const d = q.data;
  const pareto = Array.isArray(d.pareto) ? d.pareto : [];
  const openTotal = pareto.reduce((s, t) => s + (t.count ?? 0), 0);
  const max = Math.max(...pareto.map((t) => t.count), 1);

  return (
    <View style={{ gap: 14 }}>
      <View style={styles.grid}>
        <Kpi label={t('Open NCRs')} value={openTotal} />
        <Kpi label={t('Overdue actions')} value={d.overdue_actions ?? 0} tone={(d.overdue_actions ?? 0) > 0 ? 'danger' : 'success'} />
        <Kpi label={t('Scrap dispo.')} value={fmt(d.disposition_summary?.scrap ?? 0)} />
      </View>
      <View style={[styles.card, { gap: 10 }]}>
        <SectionLabel>Open by type</SectionLabel>
        {pareto.length === 0 ? (
          <Text style={styles.emptyText}>{t('No open non-conformances.')}</Text>
        ) : (
          pareto.map((tp, idx) => (
            <View key={idx} style={{ gap: 6 }}>
              <View style={styles.row}>
                <Text style={{ flex: 1, color: colors.ink, fontFamily: fonts.sans.native.semibold, fontSize: 13 }}>{tp.name}</Text>
                <Mono size={11} color={colors.muted}>{tp.count}</Mono>
              </View>
              <View style={styles.miniBar}>
                <View style={[styles.miniBarFill, { width: `${(tp.count / max) * 100}%`, backgroundColor: colors.blocked }]} />
              </View>
            </View>
          ))
        )}
      </View>
    </View>
  );
}

function NetRequirementsView({ filters }: { filters: Filters | null }) {
  const { t } = useTranslation();
  const q = useNetRequirementsReport(filters);
  if (!filters) return null;
  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const rows = q.data.requirements ?? [];
  const short = rows.filter((r) => r.is_short).length;

  return (
    <View style={{ gap: 14 }}>
      <View style={styles.grid}>
        <Kpi label={t('Materials')} value={rows.length} />
        <Kpi label={t('Short')} value={short} tone={short > 0 ? 'danger' : 'success'} />
      </View>
      <View style={[styles.card, { gap: 4 }]}>
        <SectionLabel>Net requirements</SectionLabel>
        {rows.length === 0 ? (
          <Text style={styles.emptyText}>{t('No shortages — all materials covered.')}</Text>
        ) : (
          rows.map((r) => (
            <View key={r.material_id} style={[styles.row, styles.rowBorder]}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text numberOfLines={1} style={{ color: colors.ink, fontFamily: fonts.sans.native.semibold, fontSize: 13 }}>{r.name}</Text>
                <Mono size={9} color={colors.faint}>{`${r.code ?? ''} ${r.unit_of_measure ?? ''}`.trim()}</Mono>
              </View>
              <Mono size={11} color={colors.muted} style={{ width: 150, textAlign: 'right' }}>
                {`${fmt(r.required_qty)} ${t('req')} · ${fmt(r.available_qty)} ${t('avail')}`}
              </Mono>
              <Mono size={12} color={r.is_short ? colors.blocked : colors.running} weight="600" style={{ width: 70, textAlign: 'right' }}>
                {fmt(r.net_qty)}
              </Mono>
            </View>
          ))
        )}
      </View>
    </View>
  );
}

function ProductionCostView({ filters }: { filters: Filters | null }) {
  const { t } = useTranslation();
  const q = useProductionCostReport(filters);
  if (!filters) return null;
  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const s = q.data.summary;
  const cur = q.data.currency ?? s.currency ?? '';
  const orders = q.data.orders ?? [];
  const money = (n: number | null | undefined) => `${fmt(n ?? 0)} ${cur}`.trim();

  return (
    <View style={{ gap: 14 }}>
      <View style={styles.grid}>
        <Kpi label={t('Total cost')} value={money(s.total_cost)} />
        <Kpi label={t('Material')} value={money(s.material_cost)} />
        <Kpi label={t('Labor')} value={money(s.labor_cost)} tone="primary" />
        <Kpi label={t('Additional')} value={money(s.additional_cost)} />
        <Kpi label={t('Avg / unit')} value={s.avg_cost_per_unit != null ? money(s.avg_cost_per_unit) : '—'} tone="success" />
        <Kpi label={t('Orders')} value={s.orders} />
      </View>
      <View style={[styles.card, { gap: 4 }]}>
        <SectionLabel>Finished work orders</SectionLabel>
        {orders.length === 0 ? (
          <Text style={styles.emptyText}>{t('No finished work orders in this period.')}</Text>
        ) : (
          orders.map((o) => (
            <View key={o.id} style={[styles.row, styles.rowBorder]}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono size={11} color={colors.accent}>{o.order_no}</Mono>
                <Text numberOfLines={1} style={{ color: colors.muted, fontSize: 11, fontFamily: fonts.sans.native.regular }}>
                  {`${o.product_name ?? '—'} · ${fmt(o.produced_qty)} ${t('pcs')}`}
                </Text>
              </View>
              <View style={{ alignItems: 'flex-end' }}>
                <Mono size={12} color={colors.ink} weight="600">{money(o.total_cost)}</Mono>
                <Mono size={9} color={colors.faint}>{o.cost_per_unit != null ? `${money(o.cost_per_unit)}/u` : ''}</Mono>
              </View>
            </View>
          ))
        )}
      </View>
    </View>
  );
}

function fmt(n: number) {
  return Math.round(n * 100) / 100;
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  container: { padding: 18, gap: 14, paddingBottom: 32 },
  card: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  tabs: { flexDirection: 'row', gap: 8, flexWrap: 'wrap' },
  tab: { flex: 1, paddingVertical: 10, paddingHorizontal: 12, borderRadius: radius.pill, borderWidth: 1, alignItems: 'center' },
  tabActive: { backgroundColor: colors.accent, borderColor: colors.accent },
  tabInactive: { backgroundColor: 'transparent', borderColor: colors.line },
  tabLabel: { fontSize: 12, fontFamily: fonts.sans.native.semibold },
  dateRow: { flexDirection: 'row', gap: 8 },
  lineChips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  chip: { paddingVertical: 6, paddingHorizontal: 11, borderRadius: radius.pill, borderWidth: 1 },
  chipActive: { backgroundColor: colors.ink, borderColor: colors.ink },
  chipInactive: { backgroundColor: 'transparent', borderColor: colors.line },
  chipLabel: { fontSize: 12, fontFamily: fonts.sans.native.semibold },
  hero: { borderRadius: radius.sheet, padding: 18, backgroundColor: colors.ink },
  heroNumRow: { flexDirection: 'row', alignItems: 'baseline', gap: 4, marginTop: 12 },
  heroNum: { color: '#FFFFFF', fontSize: 56, fontFamily: fonts.mono.native.medium, letterSpacing: -2, lineHeight: 56 },
  heroUnit: { color: 'rgba(255,255,255,0.6)', fontSize: 22, fontFamily: fonts.mono.native.regular },
  heroBar: { height: 4, backgroundColor: 'rgba(255,255,255,0.15)', borderRadius: 2, marginTop: 14, overflow: 'hidden' },
  heroBarFill: { height: '100%', backgroundColor: colors.accent },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  kpi: { flexBasis: '48%', flexGrow: 1, gap: 6 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  rowBorder: { paddingVertical: 9, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2 },
  miniBar: { height: 4, borderRadius: 2, overflow: 'hidden', backgroundColor: colors.chip },
  miniBarFill: { height: '100%' },
  timePill: { alignItems: 'center', paddingVertical: 6, paddingHorizontal: 10, borderRadius: radius.sm, gap: 2, backgroundColor: colors.chip },
  emptyText: { color: colors.faint, fontSize: 13, fontFamily: fonts.sans.native.regular },
});
