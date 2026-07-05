/**
 * Inbound Inspections — 1:1 with the web inspections index table
 * (Pages/inspections/Index.jsx): the shared DataTable with the web's column set
 * (Started / Material / Lot / Qty / Inspector / Status / Disposition) and the
 * per-row Perform/Open action. Keeps the two summary tiles (Pending / Failed 30d)
 * and the status filter; rows open the inspection detail (perform/disposition
 * live there). Data via the existing REST hooks.
 */
import { format, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useInspections,
  useInspectionStats,
} from '@/hooks/queries/useInspections';
import type { Inspection, InspectionStatus } from '@/api/inspections';

type FilterId = 'all' | InspectionStatus;

// Inspection status → the design-system pill kind (via components/ui/StatusPill,
// which resolves the token through statusKindFor). Matches the web colour map:
// pass → running/green, conditional → downtime, fail → blocked, pending.
const STATUS_PILL: Record<InspectionStatus, string> = {
  pending: 'pending',
  pass: 'accepted',
  fail: 'blocked',
  conditional_pass: 'paused',
};

const STATUS_LABEL: Record<InspectionStatus, string> = {
  pending: 'Pending',
  pass: 'Pass',
  fail: 'Fail',
  conditional_pass: 'Conditional',
};

const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const fmtStarted = (iso: string) => {
  try {
    return format(parseISO(iso), 'MMM d HH:mm');
  } catch {
    return '—';
  }
};

export function InspectionsList() {
  const router = useRouter();
  const { t } = useTranslation();
  const [filter, setFilter] = useState<FilterId>('all');

  const query = useInspections({ limit: 100 });
  const stats = useInspectionStats({ days: 30 });
  const all: Inspection[] = query.data ?? [];

  const pendingCount = useMemo(
    () => all.filter((i) => i.status === 'pending').length,
    [all],
  );
  const failed30d = stats.data?.fail_count ?? 0;

  const filtered = useMemo(() => {
    if (filter === 'all') return all;
    return all.filter((i) => i.status === filter);
  }, [all, filter]);

  const options = useMemo(
    () => [
      { value: 'all', label: t('All statuses') },
      { value: 'pending', label: t('Pending') },
      { value: 'pass', label: t('Pass') },
      { value: 'fail', label: t('Fail') },
      { value: 'conditional_pass', label: t('Conditional') },
    ],
    [t],
  );

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Inbound Inspections')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 170 }}>
          <Dropdown
            value={filter}
            onChange={(v) => setFilter(v as FilterId)}
            placeholder={t('All statuses')}
            options={options}
          />
        </View>
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <View style={styles.statRow}>
            <SummaryTile label={t('Pending')} value={pendingCount} tone={pendingCount > 0 ? colors.downtime : colors.faint} />
            <SummaryTile label={t('Failed (30d)')} value={failed30d} tone={failed30d > 0 ? colors.blocked : colors.running} />
          </View>

          <DataTable<Inspection>
            data={filtered}
            searchPlaceholder={t('Search inspections…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['lot_number']}
            emptyText={t('No inspections.')}
            onRowPress={(i) => router.push(`/quality/inspections/${i.id}` as never)}
            columns={[
              {
                key: 'started',
                label: t('Started'),
                width: 88,
                render: (i) => <Mono size={10} color={colors.muted}>{fmtStarted(i.started_at)}</Mono>,
              },
              {
                key: 'material',
                label: t('Material'),
                flex: 1,
                render: (i) => <Text numberOfLines={1} style={styles.material}>{i.material?.name ?? '—'}</Text>,
              },
              {
                key: 'lot',
                label: t('Lot'),
                width: 72,
                render: (i) => <Mono size={11} color={colors.ink} numberOfLines={1}>{i.lot_number}</Mono>,
              },
              {
                key: 'qty',
                label: t('Qty'),
                width: 70,
                align: 'right',
                render: (i) => (
                  <Mono size={11} color={colors.ink}>
                    {i.quantity_received != null ? String(i.quantity_received) : '—'}
                  </Mono>
                ),
              },
              {
                key: 'inspector',
                label: t('Inspector'),
                width: 100,
                render: (i) => <Text numberOfLines={1} style={styles.cellText}>{i.inspector?.name ?? '—'}</Text>,
              },
              {
                key: 'status',
                label: t('Status'),
                width: 94,
                render: (i) => (
                  <View>
                    <StatusPill
                      status={STATUS_PILL[i.status as InspectionStatus] ?? 'pending'}
                      label={t(STATUS_LABEL[i.status as InspectionStatus] ?? humanize(i.status))}
                    />
                    {i.issue_id ? <Mono size={10} color={colors.blocked}>{`NC #${i.issue_id}`}</Mono> : null}
                  </View>
                ),
              },
              {
                key: 'disposition',
                label: t('Disposition'),
                width: 92,
                render: (i) =>
                  i.disposition && i.disposition !== 'pending' ? (
                    <Text numberOfLines={1} style={styles.cellText}>{humanize(i.disposition)}</Text>
                  ) : (
                    <Text style={styles.dash}>—</Text>
                  ),
              },
            ]}
            actions={(i) => [
              {
                label: i.status === 'pending' ? t('Perform') : t('Open'),
                variant: 'primary',
                onPress: () => router.push(`/quality/inspections/${i.id}` as never),
              },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

function SummaryTile({ label, value, tone }: { label: string; value: number; tone: string }) {
  return (
    <View style={styles.statTile}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
      <Text style={[styles.statValue, { color: tone }]}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  statRow: { flexDirection: 'row', gap: 10, paddingHorizontal: 6, paddingBottom: 14 },
  statTile: {
    flex: 1,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    paddingVertical: 12,
    paddingHorizontal: 12,
    alignItems: 'center',
  },
  statValue: { fontSize: 24, fontFamily: fonts.mono.native.semibold, marginTop: 4 },
  material: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  dash: { fontSize: 13, color: colors.faintest },
});
