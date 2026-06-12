import { format, formatDistanceToNowStrict, parseISO, startOfDay, startOfWeek } from 'date-fns';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDowntimes } from '@/hooks/queries/useDowntime';
import { useAuthStore } from '@/stores/authStore';
import type { DowntimeReason, ProductionDowntime } from '@/api/downtime';

type FilterId = 'all' | 'shift' | 'today' | 'week';

const FILTERS: { id: FilterId; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'shift', label: 'This shift' },
  { id: 'today', label: 'Today' },
  { id: 'week', label: 'This week' },
];

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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const lineName = useAuthStore((s) =>
    s.user?.lines?.find((l) => l.id === s.activeLineId)?.name ?? '',
  );

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
    if (filter === 'all') return events;
    if (filter === 'today') return events;
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

  const summary = useMemo(() => {
    const all = filtered;
    let planned = 0;
    let unplanned = 0;
    let changeover = 0;
    for (const e of all) {
      const min = e.duration_minutes ?? 0;
      const kind = e.reason?.kind;
      if (kind === 'planned') planned += min;
      else if (kind === 'changeover') changeover += min;
      else unplanned += min;
    }
    return {
      total: planned + unplanned + changeover,
      planned,
      unplanned,
      changeover,
    };
  }, [filtered]);

  if (query.isLoading) return <LoadingState />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title="Downtime history"
        subtitle={`${lineName ? `${lineName.toUpperCase()} · ` : ''}${filtered.length} EVENT${filtered.length === 1 ? '' : 'S'}`}
      />
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} />}>
        <View style={styles.statsRow}>
          {[
            { l: 'TOTAL', v: summary.total, c: palette.text },
            { l: 'PLANNED', v: summary.planned, c: palette.info },
            { l: 'CHANGEOVER', v: summary.changeover, c: palette.warning },
            { l: 'UNPLANNED', v: summary.unplanned, c: palette.danger },
          ].map((s) => (
            <View
              key={s.l}
              style={[styles.statTile, { backgroundColor: palette.surface, borderColor: palette.border }]}>
              <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>{s.l}</Mono>
              <Text style={{ color: s.c, fontFamily: MONO, fontSize: 18, fontWeight: '700', marginTop: 4 }}>
                {s.v}
                <Text style={{ fontSize: 11, color: palette.textFaint }}>min</Text>
              </Text>
            </View>
          ))}
        </View>

        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={{ flexDirection: 'row', gap: 6 }}>
          {FILTERS.map((f) => {
            const active = f.id === filter;
            return (
              <Pressable
                key={f.id}
                onPress={() => setFilter(f.id)}
                style={[
                  styles.filter,
                  {
                    backgroundColor: active ? palette.surfaceInverse : palette.surface,
                    borderColor: active ? palette.surfaceInverse : palette.border,
                  },
                ]}>
                <Mono
                  size={11}
                  color={active ? (scheme === 'dark' ? '#171715' : '#fff') : palette.text}
                  weight="600"
                  letterSpacing={0.5}>
                  {f.label.toUpperCase()}
                </Mono>
              </Pressable>
            );
          })}
        </ScrollView>

        <SectionLabel>Events</SectionLabel>
        {filtered.length === 0 ? (
          <Card>
            <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 14 }}>
              NO DOWNTIME — KEEP IT UP.
            </Mono>
          </Card>
        ) : (
          <Card style={{ padding: 0, overflow: 'hidden' }}>
            {filtered.map((e, i) => (
              <Row
                key={e.id}
                e={e}
                last={i === filtered.length - 1}
                palette={palette}
              />
            ))}
          </Card>
        )}
      </ScrollView>
    </View>
  );
}

function Row({
  e,
  last,
  palette,
}: {
  e: ProductionDowntime;
  last: boolean;
  palette: typeof Colors.light;
}) {
  const kind = e.reason?.kind ?? 'unplanned';
  const kindMeta = kindAppearance(kind, palette);
  const railColor = kindMeta.color;
  const ago = (() => {
    try {
      return formatDistanceToNowStrict(parseISO(e.started_at), { addSuffix: true });
    } catch {
      return '';
    }
  })();
  const dur = e.duration_minutes ?? Math.max(0, Math.floor((Date.now() - new Date(e.started_at).getTime()) / 60000));

  return (
    <View
      style={[
        styles.row,
        {
          borderLeftColor: railColor,
          borderBottomColor: last ? 'transparent' : palette.border,
          borderBottomWidth: last ? 0 : StyleSheet.hairlineWidth,
        },
      ]}>
      <View style={{ flex: 1, minWidth: 0 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
          <Text style={[styles.reasonName, { color: palette.text }]}>
            {e.reason?.name ?? 'Unknown'}
          </Text>
          <View
            style={[
              styles.tag,
              { backgroundColor: kindMeta.soft },
            ]}>
            <Mono size={9} color={kindMeta.color} weight="700" letterSpacing={0.5}>
              {kindMeta.label}
            </Mono>
          </View>
        </View>
        {e.notes ? (
          <Text style={[styles.notes, { color: palette.textMuted }]} numberOfLines={2}>
            "{e.notes}"
          </Text>
        ) : null}
        <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
          {ago.toUpperCase()}
          {e.reported_by_user?.username ? ` · ${e.reported_by_user.username.toUpperCase()}` : ''}
          {!e.ended_at ? ' · LIVE' : ''}
        </Mono>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Mono size={18} color={railColor} weight="700">{dur}</Mono>
        <Mono size={9} color={palette.textFaint} letterSpacing={0.4}>MIN</Mono>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  statsRow: { flexDirection: 'row', gap: 8 },
  statTile: { flex: 1, padding: 10, borderRadius: 10, borderWidth: 1 },
  filter: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
  row: {
    padding: 12,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
    borderLeftWidth: 3,
  },
  reasonName: { fontSize: 13, fontWeight: '600' },
  tag: { paddingVertical: 2, paddingHorizontal: 5, borderRadius: 3 },
  notes: { fontSize: 12, fontStyle: 'italic', marginTop: 4 },
});

/** Per-kind row appearance. Planned = blue (informational, no OEE loss);
 *  changeover = amber (counts as availability loss but expected); unplanned =
 *  red (true loss). Mirrors backend `DowntimeKind::badgeColor`. */
function kindAppearance(
  kind: NonNullable<DowntimeReason['kind']>,
  palette: typeof Colors.light,
): { color: string; soft: string; label: string } {
  if (kind === 'planned') {
    return { color: palette.info, soft: palette.infoSoft, label: 'PLANNED' };
  }
  if (kind === 'changeover') {
    return { color: palette.warning, soft: palette.warningSoft, label: 'CHANGEOVER' };
  }
  return { color: palette.danger, soft: palette.dangerSoft, label: 'UNPLANNED' };
}
