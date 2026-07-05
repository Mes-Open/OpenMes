/**
 * Downtime history — production downtime events for the active line, in the
 * table pattern (Reason / Kind / Started / Duration). A time-window filter
 * (all / this shift / today / this week) narrows the list. Read via REST
 * useDowntimes; scoped to the operator's active line.
 */
import { format, parseISO, startOfWeek } from 'date-fns';
import { useMemo, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDowntimes } from '@/hooks/queries/useDowntime';
import { useAuthStore } from '@/stores/authStore';
import type { DowntimeReason, ProductionDowntime } from '@/api/downtime';

type FilterId = 'all' | 'shift' | 'today' | 'week';

const KIND_COLOR: Record<string, { fg: string; bg: string }> = {
  planned: { fg: colors.pending, bg: colors.pendingBg },
  changeover: { fg: colors.downtime, bg: colors.downtimeBg },
  unplanned: { fg: colors.blocked, bg: colors.blockedBg },
};

function shiftStart(now: Date): Date {
  const h = now.getHours();
  const d = new Date(now);
  if (h >= 6 && h < 14) d.setHours(6, 0, 0, 0);
  else if (h >= 14 && h < 22) d.setHours(14, 0, 0, 0);
  else if (h >= 22) d.setHours(22, 0, 0, 0);
  else {
    // before 06 → previous day's C-shift start
    d.setDate(d.getDate() - 1);
    d.setHours(22, 0, 0, 0);
  }
  return d;
}

export function DowntimeHistoryScreen() {
  const { t } = useTranslation();
  const activeLineId = useAuthStore((s) => s.activeLineId);

  const [filter, setFilter] = useState<FilterId>('today');

  // Server-side filter for date when "today" is picked, otherwise pull broader.
  const dateFilter = filter === 'today' ? format(new Date(), 'yyyy-MM-dd') : undefined;
  const query = useDowntimes({
    line_id: activeLineId ?? undefined,
    date: dateFilter,
  });

  const filtered = useMemo(() => {
    const events = query.data ?? [];
    const now = new Date();
    if (filter === 'all' || filter === 'today') return events;
    if (filter === 'shift') {
      const start = shiftStart(now).getTime();
      return events.filter((e) => {
        try {
          return parseISO(e.started_at).getTime() >= start;
        } catch {
          return false;
        }
      });
    }
    if (filter === 'week') {
      const start = startOfWeek(now, { weekStartsOn: 1 }).getTime();
      return events.filter((e) => {
        try {
          return parseISO(e.started_at).getTime() >= start;
        } catch {
          return false;
        }
      });
    }
    return events;
  }, [filter, query.data]);

  const options = useMemo(
    () => [
      { value: 'all', label: t('All') },
      { value: 'shift', label: t('This shift') },
      { value: 'today', label: t('Today') },
      { value: 'week', label: t('This week') },
    ],
    [t],
  );

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Downtime')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 150 }}>
          <Dropdown value={filter} onChange={(v) => setFilter(v as FilterId)} options={options} />
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
          <View style={[styles.row, styles.headerRow]}>
            <HCell flex={1.6}>{t('Reason')}</HCell>
            <HCell w={104}>{t('Kind')}</HCell>
            <HCell w={104}>{t('Started')}</HCell>
            <HCell w={72}>{t('Duration')}</HCell>
          </View>
          {filtered.map((e) => (
            <DowntimeRow key={e.id} event={e} />
          ))}
          {filtered.length === 0 ? <Text style={styles.empty}>{t('No downtime.')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function DowntimeRow({ event }: { event: ProductionDowntime }) {
  const { t } = useTranslation();
  const kind: NonNullable<DowntimeReason['kind']> = event.reason?.kind ?? 'unplanned';
  const meta = KIND_COLOR[kind] ?? KIND_COLOR.unplanned;
  const started = (() => {
    try {
      return format(parseISO(event.started_at), 'MM-dd HH:mm');
    } catch {
      return '—';
    }
  })();
  const dur = event.duration_minutes ?? Math.max(0, Math.floor((Date.now() - new Date(event.started_at).getTime()) / 60000));

  return (
    <View style={[styles.row, styles.dataRow]}>
      <View style={{ flex: 1.6 }}>
        <Text numberOfLines={1} style={styles.title}>{event.reason?.name ?? t('Unknown')}</Text>
        {event.notes ? <Text numberOfLines={1} style={styles.notes}>{event.notes}</Text> : null}
      </View>
      <View style={{ width: 104 }}>
        <View style={[styles.kindPill, { backgroundColor: meta.bg }]}>
          <Mono size={9} color={meta.fg} letterSpacing={0.5}>{t(kind.charAt(0).toUpperCase() + kind.slice(1)).toUpperCase()}</Mono>
        </View>
      </View>
      <View style={{ width: 104 }}>
        <Mono size={10} color={colors.muted}>{started}</Mono>
      </View>
      <View style={{ width: 72 }}>
        <Mono size={11} color={colors.ink}>{dur} {t('min')}</Mono>
      </View>
    </View>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{String(children).toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  notes: { fontSize: 11.5, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: 2 },
  kindPill: { alignSelf: 'flex-start', paddingVertical: 3, paddingHorizontal: 7, borderRadius: 4 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
