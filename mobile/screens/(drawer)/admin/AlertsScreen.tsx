/**
 * Admin Alerts — 1:1 with the web alerts page (Pages/admin/alerts/Index.jsx):
 * Blocking Issues (red cards) · Overdue Work Orders · Blocked Work Orders ·
 * Open Issues (non-blocking). Data from GET /api/v1/alerts (useAlerts).
 */
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useAlerts } from '@/hooks/queries/useAlerts';
import type { AlertBlocked, AlertIssue, AlertOverdue, AlertsData } from '@/api/alerts';

function timeAgo(d: string | null | undefined): string {
  if (!d) return '';
  const ts = Date.parse(d);
  if (Number.isNaN(ts)) return '';
  const sec = Math.round((Date.now() - ts) / 1000);
  const abs = Math.abs(sec);
  const past = sec >= 0;
  const units: [string, number][] = [
    ['year', 31536000],
    ['month', 2592000],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ];
  for (const [name, s] of units) {
    if (abs >= s) {
      const n = Math.floor(abs / s);
      return past ? `${n} ${name}${n > 1 ? 's' : ''} ago` : `in ${n} ${name}${n > 1 ? 's' : ''}`;
    }
  }
  return past ? 'just now' : 'soon';
}

function fmtDate(d: string | null | undefined): string {
  if (!d) return '';
  const dt = new Date(d);
  return Number.isNaN(dt.getTime())
    ? ''
    : dt.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}

export function AlertsScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useAlerts();

  if (q.isLoading && !q.data) return <LoadingState label={t('Loading…')} />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const d = q.data as AlertsData;

  const goWo = (id: number) => router.push(`/work-orders/${id}` as never);

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.h1}>{t('Alerts')}</Text>
        {d.total > 0 ? (
          <View style={styles.totalBadge}>
            <Text style={styles.totalText}>{d.total}</Text>
          </View>
        ) : null}
        <View style={{ flex: 1 }} />
        <View style={styles.liveRow}>
          <View style={styles.liveDot} />
          <Mono size={10} color={colors.faint} letterSpacing={0.6}>{t('LIVE')}</Mono>
        </View>
      </View>

      {d.total === 0 ? (
        <View style={styles.allClear}>
          <Text style={styles.allClearTitle}>{t('All clear')}</Text>
          <Text style={styles.allClearSub}>{t('No active alerts at this time.')}</Text>
        </View>
      ) : (
        <>
          {/* Blocking Issues — placeholder when empty but other alerts exist. */}
          {d.blocking_issues.length > 0 ? (
            <View style={styles.section}>
              <SectionTitle color={colors.blocked} label={t('Blocking Issues')} count={d.blocking_issues.length} />
              <View style={{ gap: 10 }}>
                {d.blocking_issues.map((i) => (
                  <BlockingCard key={i.id} issue={i} onWo={goWo} onView={() => router.push('/admin/issues' as never)} />
                ))}
              </View>
            </View>
          ) : (
            <EmptyGroup text={t('No blocking issues')} />
          )}

          {/* Overdue Work Orders */}
          {d.overdue_orders.length > 0 && (
            <View style={styles.section}>
              <SectionTitle color={colors.downtime} label={t('Overdue Work Orders')} count={d.overdue_orders.length} />
              <View style={styles.card}>
                {d.overdue_orders.map((o) => (
                  <OverdueRow key={o.id} o={o} onPress={() => goWo(o.id)} />
                ))}
              </View>
            </View>
          )}

          {/* Blocked Work Orders */}
          {d.blocked_orders.length > 0 && (
            <View style={styles.section}>
              <SectionTitle color={colors.downtime} label={t('Blocked Work Orders')} count={d.blocked_orders.length} />
              <View style={styles.card}>
                {d.blocked_orders.map((o) => (
                  <BlockedRow key={o.id} o={o} onPress={() => goWo(o.id)} />
                ))}
              </View>
            </View>
          )}

          {/* Placeholder when there are alerts elsewhere but no work-order alerts. */}
          {d.overdue_orders.length === 0 && d.blocked_orders.length === 0 && (
            <EmptyGroup text={t('No work order alerts')} />
          )}

          {/* Open (non-blocking) Issues */}
          {d.non_blocking_issues.length > 0 && (
            <View style={styles.section}>
              <SectionTitle color={colors.downtime} label={`${t('Open Issues')} (${d.non_blocking_issues.length})`} plain />
              <View style={styles.card}>
                {d.non_blocking_issues.map((i) => (
                  <OpenIssueRow key={i.id} issue={i} onPress={() => i.order && goWo(i.order.id)} />
                ))}
              </View>
            </View>
          )}
        </>
      )}
    </ScrollView>
  );
}

function EmptyGroup({ text }: { text: string }) {
  return (
    <View style={styles.emptyGroup}>
      <Text style={styles.emptyGroupText}>{text}</Text>
    </View>
  );
}

function SectionTitle({ color, label, count, plain }: { color: string; label: string; count?: number; plain?: boolean }) {
  return (
    <View style={styles.sectionTitle}>
      <Text style={[styles.sectionLabel, { color }]}>{label}</Text>
      {!plain && count != null ? (
        <View style={[styles.countChip, { backgroundColor: colors.blockedBg }]}>
          <Mono size={10} color={colors.blocked} weight="600">{String(count)}</Mono>
        </View>
      ) : null}
    </View>
  );
}

function BlockingCard({ issue, onWo, onView }: { issue: AlertIssue; onWo: (id: number) => void; onView: () => void }) {
  const { t } = useTranslation();
  return (
    <View style={styles.blockingCard}>
      <View style={{ flex: 1, minWidth: 0 }}>
        <View style={styles.blockingHead}>
          <Text style={styles.blockingType}>{issue.type_name ?? t('Issue')}</Text>
          <StatusPill status={issue.status} />
        </View>
        {issue.description ? <Text style={styles.blockingDesc}>{issue.description}</Text> : null}
        <View style={styles.metaRow}>
          {issue.order ? (
            <Pressable onPress={() => onWo(issue.order!.id)} hitSlop={6}>
              <Text style={styles.metaText}>
                {t('Work Order')}: <Text style={styles.woLink}>{issue.order.order_no}</Text>
              </Text>
            </Pressable>
          ) : null}
          <Text style={styles.metaText}>{`${t('Reported by')}: ${issue.reporter_name ?? '—'}`}</Text>
          <Text style={styles.metaText}>{timeAgo(issue.created_at)}</Text>
        </View>
      </View>
      <Pressable onPress={onView} hitSlop={6}>
        <Text style={styles.viewLink}>{t('View issues')} →</Text>
      </Pressable>
    </View>
  );
}

function OverdueRow({ o, onPress }: { o: AlertOverdue; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, { opacity: pressed ? 0.6 : 1 }]}>
      <View style={{ width: 110 }}>
        <Mono size={12} color={colors.accent}>{o.order_no}</Mono>
        <Mono size={9} color={colors.downtime}>{fmtDate(o.due_date)}</Mono>
      </View>
      <Text numberOfLines={1} style={styles.rowMid}>{o.line_name ?? '—'}</Text>
      <Mono size={11} color={colors.blocked} weight="600">{timeAgo(o.due_date)}</Mono>
      <StatusPill status={o.status} />
    </Pressable>
  );
}

function BlockedRow({ o, onPress }: { o: AlertBlocked; onPress: () => void }) {
  const { t } = useTranslation();
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, { opacity: pressed ? 0.6 : 1 }]}>
      <Mono size={12} color={colors.accent} style={{ width: 110 }}>{o.order_no}</Mono>
      <Text numberOfLines={1} style={styles.rowMid}>{o.line_name ?? '—'}</Text>
      <Mono size={10} color={colors.muted}>{`${t('Blocked')} ${timeAgo(o.updated_at)}`}</Mono>
    </Pressable>
  );
}

function OpenIssueRow({ issue, onPress }: { issue: AlertIssue; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, { opacity: pressed ? 0.6 : 1 }]}>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text numberOfLines={1} style={styles.issueTitle}>{issue.title ?? issue.description ?? '—'}</Text>
        <Text numberOfLines={1} style={styles.issueSub}>
          {`${issue.type_name ?? '—'}${issue.order ? ` · ${issue.order.order_no}` : ''} · ${timeAgo(issue.created_at)}`}
        </Text>
      </View>
      <StatusPill status={issue.status} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 16, gap: 18, maxWidth: 1120, width: '100%', alignSelf: 'center' },

  header: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  h1: { fontSize: 24, fontFamily: fonts.sans.native.bold, color: colors.ink, letterSpacing: -0.4 },
  totalBadge: { minWidth: 26, height: 26, borderRadius: 13, paddingHorizontal: 6, backgroundColor: colors.blocked, alignItems: 'center', justifyContent: 'center' },
  totalText: { color: '#fff', fontFamily: fonts.sans.native.bold, fontSize: 13 },
  liveRow: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  liveDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.running },

  allClear: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingVertical: 48, alignItems: 'center', gap: 4 },
  allClearTitle: { fontSize: 18, fontFamily: fonts.sans.native.semibold, color: colors.muted },
  allClearSub: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular },

  emptyGroup: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingVertical: 28, alignItems: 'center' },
  emptyGroupText: { fontSize: 13.5, color: colors.muted, fontFamily: fonts.sans.native.medium },

  section: { gap: 10 },
  sectionTitle: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  sectionLabel: { fontSize: 16, fontFamily: fonts.sans.native.bold },
  countChip: { borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2 },

  card: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingHorizontal: 14 },

  blockingCard: { flexDirection: 'row', gap: 12, backgroundColor: colors.blockedBg, borderLeftWidth: 4, borderLeftColor: colors.blocked, borderRadius: radius.sm, padding: 14 },
  blockingHead: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  blockingType: { fontSize: 14, fontFamily: fonts.sans.native.semibold, color: colors.blocked },
  blockingDesc: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: 4 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 14, flexWrap: 'wrap', marginTop: 8 },
  metaText: { fontSize: 11.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  woLink: { color: colors.accent, fontFamily: fonts.mono.native.medium },
  viewLink: { fontSize: 11.5, color: colors.blocked, fontFamily: fonts.sans.native.medium },

  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 11, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2 },
  rowMid: { flex: 1, fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },
  issueTitle: { fontSize: 13.5, fontFamily: fonts.sans.native.medium, color: colors.ink },
  issueSub: { fontSize: 11, color: colors.faint, fontFamily: fonts.sans.native.regular, marginTop: 1 },
});
