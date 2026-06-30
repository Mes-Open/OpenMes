import { format, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useInspections,
  useInspectionStats,
} from '@/hooks/queries/useInspections';
import type {
  Inspection,
  InspectionDisposition,
  InspectionStatus,
} from '@/api/inspections';

type FilterId = 'all' | InspectionStatus;

const STATUS_COLOR: Record<InspectionStatus, string> = {
  pending: BRAND.amber,
  pass: '#1C9A55',
  fail: '#D6442F',
  conditional_pass: '#EA5A2B',
};

const STATUS_LABEL: Record<InspectionStatus, string> = {
  pending: 'Open',
  pass: 'Pass',
  fail: 'Fail',
  conditional_pass: 'Conditional',
};

/**
 * Inspections hub — operator/supervisor entry point. Top hero shows 4 KPI
 * tiles (OPEN / PASS / FAIL / QUAR). Filter chips below scope the list.
 */
export function InspectionsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [filter, setFilter] = useState<FilterId>('all');

  const query = useInspections({ limit: 100 });
  const stats = useInspectionStats({ days: 30 });
  const all: Inspection[] = query.data ?? [];

  const counts = useMemo(() => {
    const c = { all: all.length, pending: 0, pass: 0, fail: 0, conditional_pass: 0, quar: 0 };
    for (const i of all) {
      const s = i.status as InspectionStatus;
      if (s in c) c[s] += 1;
      if ((i.disposition as InspectionDisposition) === 'quarantined') c.quar += 1;
    }
    return c;
  }, [all]);

  const filtered = useMemo(() => {
    if (filter === 'all') return all;
    return all.filter((i) => i.status === filter);
  }, [all, filter]);

  const failRate = (() => {
    const s = stats.data;
    if (!s || s.total_completed === 0) return null;
    return s.fail_count / s.total_completed;
  })();

  return (
    <ListScreen
      title={t('Inspections')}
      eyebrow={`${t('QUALITY').toUpperCase()} · ${counts.pending} ${t('ACTIVE').toUpperCase()}${
        failRate != null
          ? ` · ${Math.round(failRate * 100)}% ${t('FAIL RATE').toUpperCase()}`
          : ''
      }`}
      newRoute={undefined}
      filters={[
        { id: 'all', label: t('All'), count: counts.all },
        { id: 'pending', label: t('Pending'), count: counts.pending },
        { id: 'pass', label: t('Pass'), count: counts.pass },
        { id: 'fail', label: t('Fail'), count: counts.fail },
        { id: 'conditional_pass', label: t('Conditional'), count: counts.conditional_pass },
      ]}
      activeFilter={filter}
      onFilterChange={(id) => setFilter(id as FilterId)}
      extraHeader={
        <View style={styles.heroRow}>
          {[
            { l: 'OPEN', v: counts.pending, c: BRAND.amber },
            { l: 'PASS', v: counts.pass, c: '#1C9A55' },
            { l: 'FAIL', v: counts.fail, c: '#D6442F' },
            { l: 'QUAR', v: counts.quar, c: '#7c3aed' },
          ].map((s) => (
            <View
              key={s.l}
              style={[
                styles.heroTile,
                { backgroundColor: palette.surface, borderColor: palette.border },
              ]}>
              <Text style={[styles.heroNum, { color: s.c, fontFamily: MONO }]}>
                {s.v}
              </Text>
              <Mono size={9} color={palette.textFaint} letterSpacing={0.5} style={{ marginTop: 2 }}>
                {t(s.l).toUpperCase()}
              </Mono>
            </View>
          ))}
        </View>
      }
      items={filtered}
      keyExtractor={(i) => String(i.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No inspections')}
      emptySubtitle={t('Inbound lots will show here once received.')}
      renderItem={(item) => {
        const status = item.status as InspectionStatus;
        const color = STATUS_COLOR[status] ?? palette.textMuted;
        const label = STATUS_LABEL[status] ?? item.status;
        const startedFmt = (() => {
          try {
            return format(parseISO(item.started_at), 'MMM d HH:mm');
          } catch {
            return '';
          }
        })();
        const planName = item.plan?.name ?? t('Ad-hoc inspection');
        return (
          <Pressable
            onPress={() =>
              router.push(`/quality/inspections/${item.id}` as never)
            }
            style={({ pressed }) => [
              styles.row,
              {
                backgroundColor: palette.surface,
                borderColor: palette.border,
                opacity: pressed ? 0.9 : 1,
              },
            ]}>
            <View style={[styles.rail, { backgroundColor: color }]} />
            <View style={{ flex: 1, minWidth: 0 }}>
              <Mono size={11.5} color={palette.text} weight="700" letterSpacing={0.3}>
                INSP-{String(item.id).padStart(4, '0')}
              </Mono>
              <Text
                style={[styles.planName, { color: palette.text }]}
                numberOfLines={1}>
                {planName}
              </Text>
              <Mono size={10} color={palette.textFaint} letterSpacing={0.3} style={{ marginTop: 4 }}>
                LOT {item.lot_number.toUpperCase()}
                {startedFmt ? ` · ${startedFmt.toUpperCase()}` : ''}
              </Mono>
            </View>
            <View style={[styles.statePill, { backgroundColor: `${color}22` }]}>
              <Mono size={9.5} color={color} weight="700" letterSpacing={0.5}>
                {label.toUpperCase()}
              </Mono>
            </View>
          </Pressable>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  heroRow: { flexDirection: 'row', gap: 8 },
  heroTile: {
    flex: 1,
    paddingVertical: 10,
    paddingHorizontal: 8,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
  },
  heroNum: { fontSize: 18, fontWeight: '700', letterSpacing: -0.3 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  rail: { width: 6, alignSelf: 'stretch', borderRadius: 3 },
  planName: { fontSize: 12.5, marginTop: 3 },
  statePill: { paddingVertical: 3, paddingHorizontal: 7, borderRadius: 4 },
});
