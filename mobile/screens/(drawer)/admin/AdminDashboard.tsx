/**
 * Admin Dashboard — 1:1 with the web Inertia dashboard (Pages/admin/Dashboard.jsx):
 * KPI cards → OEE Overview → Inbound QC (30d) → Materials → Scrap (30d) →
 * Non-conformances → Recent work orders → Open issues, with a line filter.
 * Data from GET /api/v1/admin/dashboard (useAdminDashboard).
 */
import { useState } from 'react';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { OeeGauge } from '@/components/ui/OeeGauge';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useAdminDashboard } from '@/hooks/queries/useAdminDashboard';
import type {
  AdminDashboard,
  DashIssue,
  DashOee,
  DashRecentWo,
} from '@/api/adminDashboard';

type Accent = 'blue' | 'gray' | 'red' | 'green' | 'yellow';
const ACCENT_FG: Record<Accent, string> = {
  blue: colors.accent,
  gray: colors.muted,
  red: colors.blocked,
  green: colors.running,
  yellow: colors.downtime,
};

type StatColor = 'red' | 'yellow' | 'green';
const STAT_TINT: Record<StatColor, string> = {
  red: colors.blockedBg,
  yellow: colors.downtimeBg,
  green: colors.runningBg,
};
const STAT_FG: Record<StatColor, string> = {
  red: colors.blocked,
  yellow: colors.downtime,
  green: colors.running,
};

function oeeBand(v: number | null): 'green' | 'yellow' | 'red' | 'gray' {
  if (v == null) return 'gray';
  if (v >= 85) return 'green';
  if (v >= 65) return 'yellow';
  return 'red';
}
const BAND_BG = { green: colors.runningBg, yellow: colors.downtimeBg, red: colors.blockedBg, gray: colors.chip };

const pct = (v: number | null | undefined) => (v == null ? '—' : `${Number(v).toFixed(0)}%`);

export function AdminDashboardScreen() {
  const { t, i18n } = useTranslation();
  const router = useRouter();
  const [lineId, setLineId] = useState('');
  const q = useAdminDashboard(lineId);

  if (q.isLoading && !q.data) return <LoadingState label={t('Loading…')} />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const d = q.data as AdminDashboard;

  const lineOptions = [
    { value: '', label: t('All lines') },
    ...d.lines.map((l) => ({ value: String(l.id), label: l.name })),
  ];

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      {/* Header */}
      <View style={styles.header}>
        <View style={{ flex: 1 }}>
          <Text style={styles.h1}>{t('Admin Dashboard')}</Text>
          <Text style={styles.subtitle}>
            {new Date().toLocaleString(i18n.language, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}{' '}
            {lineId ? `— ${d.lines.find((l) => String(l.id) === lineId)?.name ?? ''}` : t('— all lines')}
          </Text>
        </View>
        <View style={{ minWidth: 170 }}>
          <Dropdown options={lineOptions} value={lineId} onChange={(v) => setLineId(v as string)} placeholder={t('All lines')} />
        </View>
      </View>

      {/* KPI cards */}
      <View style={styles.kpiGrid}>
        <Kpi label={t('Total Work Orders')} value={d.stats.total_work_orders} onPress={() => router.push('/admin/work-orders' as never)} />
        <Kpi label={t('In Progress')} value={d.stats.in_progress} accent="blue" hint={t('incl. accepted')} onPress={() => router.push('/admin/work-orders?status=IN_PROGRESS' as never)} />
        <Kpi label={t('Pending')} value={d.stats.pending} accent="gray" onPress={() => router.push('/admin/work-orders?status=PENDING' as never)} />
        <Kpi label={t('Blocked')} value={d.stats.blocked} accent="red" onPress={() => router.push('/admin/work-orders?status=BLOCKED' as never)} />
        <Kpi label={t('Active Today')} value={d.stats.active_today} accent="green" hint={t('started or updated')} />
        <Kpi label={t('Open Issues')} value={d.stats.open_issues} accent="yellow" onPress={() => router.push('/admin/issues' as never)} />
        <Kpi label={t('Blocking Issues')} value={d.stats.blocking_issues} accent="red" onPress={() => router.push('/admin/issues' as never)} />
        <Kpi label={t('Active Lines')} value={d.stats.active_lines} onPress={() => router.push('/structure/lines' as never)} />
      </View>

      {/* OEE Overview */}
      {d.oee.length > 0 && (
        <SectionCard title={t('OEE Overview')} action={t('Full report')} onAction={() => router.push('/(drawer)/admin/oee' as never)}>
          <View style={styles.oeeGrid}>
            {d.oee.map((o) => (
              <OeeCard key={o.line_id} oee={o} />
            ))}
          </View>
        </SectionCard>
      )}

      {/* Inbound QC */}
      <SectionCard title={t('Inbound QC (30 days)')} action={t('View all')} onAction={() => router.push('/quality/inspections' as never)}>
        <View style={styles.statGrid}>
          <Stat label={t('Pending')} value={d.inbound_qc.pending} />
          <Stat label={t('Completed')} value={d.inbound_qc.completed_30d} />
          <Stat label={t('Failed')} value={d.inbound_qc.failed_30d} color="red" />
          <Stat label={t('Conditional')} value={d.inbound_qc.conditional_30d} color="yellow" />
          <Stat label={t('Pass rate')} value={d.inbound_qc.pass_rate_30d != null ? `${d.inbound_qc.pass_rate_30d}%` : '—'} color="green" />
        </View>
      </SectionCard>

      {/* Materials */}
      <SectionCard title={t('Materials')}>
        <View style={styles.statGrid}>
          <Stat label={t('Low stock')} value={d.materials.low_stock_count} color="red" />
          <Stat label={t('Expiring 30d')} value={d.materials.expiring_count} color="yellow" />
          <Stat label={t('Lots released')} value={d.materials.lots_total} />
          <Stat label={t('Quarantined')} value={d.materials.quarantined_count} color="red" />
          <Stat label={t('Reserved qty')} value={Number(d.materials.reserved_total).toFixed(0)} />
        </View>
      </SectionCard>

      {/* Scrap */}
      <SectionCard title={t('Scrap (30 days)')} action={t('Full report')} onAction={() => router.push('/admin/reports?tab=scrap' as never)}>
        <View style={styles.statGrid}>
          <Stat label={t('Total scrap')} value={Number(d.scrap.total_qty_30d ?? 0).toFixed(0)} color="red" />
          <Stat label={t('Scrap entries')} value={d.scrap.entries_30d ?? 0} />
          <Stat
            label={t('Top reason')}
            value={d.scrap.top_reason ? `${d.scrap.top_reason} (${Number(d.scrap.top_reason_qty ?? 0).toFixed(0)})` : '—'}
            color="yellow"
          />
        </View>
      </SectionCard>

      {/* Non-conformances */}
      <SectionCard title={t('Non-conformances')} action={t('Full report')} onAction={() => router.push('/admin/reports?tab=non_conformance' as never)}>
        <View style={styles.statGrid}>
          <Stat label={t('Open non-conformances')} value={d.non_conformance.open_total ?? 0} />
          <Stat label={t('Overdue actions')} value={d.non_conformance.overdue_actions ?? 0} color={d.non_conformance.overdue_actions > 0 ? 'red' : 'green'} />
          <Stat label={t('Scrap')} value={Number(d.non_conformance.disposition_summary?.scrap ?? 0)} color="yellow" />
        </View>
        {d.non_conformance.open_by_type.length > 0 ? (
          <View style={{ marginTop: 14, gap: 6 }}>
            <Mono size={9.5} color={colors.faint} letterSpacing={0.6}>
              {t('Open by type').toUpperCase()}
            </Mono>
            {d.non_conformance.open_by_type.map((tp) => {
              const max = Math.max(...d.non_conformance.open_by_type.map((x) => x.count), 1);
              return (
                <View key={tp.name} style={styles.barRow}>
                  <Text numberOfLines={1} style={styles.barLabel}>{tp.name}</Text>
                  <View style={styles.barTrack}>
                    <View style={[styles.barFill, { width: `${(tp.count / max) * 100}%` }]} />
                  </View>
                  <Mono size={11} color={colors.muted} style={{ width: 28, textAlign: 'right' }}>{String(tp.count)}</Mono>
                </View>
              );
            })}
          </View>
        ) : (
          <Text style={styles.empty}>{t('No open non-conformances.')}</Text>
        )}
      </SectionCard>

      {/* Recent work orders */}
      <SectionCard title={t('Recent work orders')} action={t('View all')} onAction={() => router.push('/admin/work-orders' as never)}>
        {d.recent_work_orders.length === 0 ? (
          <Text style={styles.empty}>{t('No active work orders.')}</Text>
        ) : (
          d.recent_work_orders.map((wo) => <WoRow key={wo.id} wo={wo} onPress={() => router.push(`/work-orders/${wo.id}` as never)} />)
        )}
      </SectionCard>

      {/* Open issues */}
      <SectionCard title={t('Open issues')} action={t('View all')} onAction={() => router.push('/admin/issues' as never)}>
        {d.open_issues.length === 0 ? (
          <Text style={styles.empty}>{t('No open issues.')} 🎉</Text>
        ) : (
          d.open_issues.map((i) => <IssueRow key={i.id} issue={i} />)
        )}
      </SectionCard>
    </ScrollView>
  );
}

function Kpi({ label, value, accent, hint, onPress }: { label: string; value: number | string; accent?: Accent; hint?: string; onPress?: () => void }) {
  const inner = (
    <>
      <Mono size={9.5} color={colors.faint} letterSpacing={0.8}>{label.toUpperCase()}</Mono>
      <Text style={[styles.kpiValue, { color: accent ? ACCENT_FG[accent] : colors.ink }]}>{value}</Text>
      {hint ? <Text style={styles.kpiHint}>{hint}</Text> : null}
    </>
  );
  if (onPress) {
    return (
      <Pressable onPress={onPress} style={({ pressed }) => [styles.kpi, { opacity: pressed ? 0.6 : 1 }]}>
        {inner}
      </Pressable>
    );
  }
  return <View style={styles.kpi}>{inner}</View>;
}

function Stat({ label, value, color }: { label: string; value: number | string; color?: StatColor }) {
  return (
    <View style={[styles.stat, { backgroundColor: color ? STAT_TINT[color] : colors.bg }]}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
      <Text style={[styles.statValue, { color: color ? STAT_FG[color] : colors.ink }]}>{value}</Text>
    </View>
  );
}

function OeeCard({ oee }: { oee: DashOee }) {
  const band = oeeBand(oee.oee_pct);
  return (
    <View style={[styles.oeeCard, { backgroundColor: BAND_BG[band] }]}>
      <Text numberOfLines={1} style={styles.oeeLine}>{oee.line_name}</Text>
      <OeeGauge value={oee.oee_pct} size={96} />
      <View style={styles.oeeApq}>
        <Mono size={9} color={colors.muted}>{`A:${pct(oee.availability_pct)}`}</Mono>
        <Mono size={9} color={colors.muted}>{`P:${pct(oee.performance_pct)}`}</Mono>
        <Mono size={9} color={colors.muted}>{`Q:${pct(oee.quality_pct)}`}</Mono>
      </View>
    </View>
  );
}

function WoRow({ wo, onPress }: { wo: DashRecentWo; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, { opacity: pressed ? 0.6 : 1 }]}>
      <Mono size={12} color={colors.accent} style={{ width: 96 }}>{wo.order_no}</Mono>
      <Text numberOfLines={1} style={styles.rowMid}>{wo.line_name ?? '—'}</Text>
      <StatusPill status={wo.status} />
      <Mono size={11} color={colors.muted} style={{ width: 64, textAlign: 'right' }}>
        {`${wo.produced_qty.toFixed(0)}/${wo.planned_qty.toFixed(0)}`}
      </Mono>
    </Pressable>
  );
}

function IssueRow({ issue }: { issue: DashIssue }) {
  const { t } = useTranslation();
  return (
    <View style={styles.issueRow}>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text numberOfLines={1} style={styles.issueTitle}>{issue.title}</Text>
        <Text numberOfLines={1} style={styles.issueSub}>
          {`${issue.type_name ?? '—'} · ${issue.work_order_id ? `WO #${issue.work_order_id}` : t('no WO')}`}
        </Text>
      </View>
      {issue.is_blocking ? (
        <View style={styles.blockingBadge}>
          <Mono size={9} color={colors.blocked} weight="600">{t('blocking').toUpperCase()}</Mono>
        </View>
      ) : null}
      <StatusPill status={issue.status} />
    </View>
  );
}

function SectionCard({ title, action, onAction, children }: { title: string; action?: string; onAction?: () => void; children: React.ReactNode }) {
  return (
    <View style={styles.card}>
      <View style={styles.cardHead}>
        <Text style={styles.cardTitle}>{title}</Text>
        {action && onAction ? (
          <Pressable onPress={onAction} hitSlop={8}>
            <Text style={styles.cardAction}>{action} →</Text>
          </Pressable>
        ) : null}
      </View>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 16, gap: 16, maxWidth: 1120, width: '100%', alignSelf: 'center' },
  header: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  subtitle: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: 4 },
  h1: { fontSize: 24, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },

  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  kpi: { flexGrow: 1, flexBasis: '22%', minWidth: 150, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14, gap: 6 },
  kpiValue: { fontFamily: fonts.mono.native.medium, fontSize: 26, letterSpacing: -0.5 },
  kpiHint: { fontSize: 11, color: colors.faint, fontFamily: fonts.sans.native.regular },

  card: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 16 },
  cardHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 },
  cardTitle: { fontSize: 14, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  cardAction: { fontSize: 12.5, color: colors.accent, fontFamily: fonts.sans.native.medium },

  oeeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  oeeCard: { flexGrow: 1, flexBasis: '18%', minWidth: 120, alignItems: 'center', borderWidth: 1, borderColor: colors.line, borderRadius: radius.sm, padding: 12, gap: 2 },
  oeeLine: { fontSize: 12.5, fontFamily: fonts.sans.native.medium, color: colors.ink, marginBottom: 2 },
  oeeApq: { flexDirection: 'row', gap: 8, marginTop: 2 },

  statGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  stat: { flexGrow: 1, flexBasis: '18%', minWidth: 120, borderWidth: 1, borderColor: colors.line, borderRadius: radius.sm, padding: 12, gap: 6 },
  statValue: { fontFamily: fonts.mono.native.medium, fontSize: 21, letterSpacing: -0.4 },

  barRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  barLabel: { width: 130, fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  barTrack: { flex: 1, height: 14, backgroundColor: colors.chip, borderRadius: 4, overflow: 'hidden' },
  barFill: { height: 14, backgroundColor: colors.blocked, borderRadius: 4 },

  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 9, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2 },
  rowMid: { flex: 1, fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },

  issueRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 9, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2 },
  issueTitle: { fontSize: 13.5, fontFamily: fonts.sans.native.medium, color: colors.ink },
  issueSub: { fontSize: 11, color: colors.faint, fontFamily: fonts.sans.native.regular, marginTop: 1 },
  blockingBadge: { backgroundColor: colors.blockedBg, borderRadius: 999, paddingHorizontal: 8, paddingVertical: 3 },

  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, paddingVertical: 4 },
});
