import { FontAwesome } from '@expo/vector-icons';
import { LegendList } from '@legendapp/list';
import { addDays, format, isToday, parseISO, startOfDay } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useMaintenanceEvents } from '@/hooks/queries/useMaintenance';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { MaintenanceEvent, MaintenanceEventStatus } from '@/api/maintenance';

/**
 * Maintenance events — chronologically grouped: Overdue (red), Today,
 * This week, Later, Past. Section headers render inline in the same list
 * so the user can scroll a single column. Status filter chips above apply
 * across all groups.
 */
export function MaintenanceEventsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const canCreate = isSupervisorOrAdmin(useAuthStore((s) => s.user));

  const [statusFilter, setStatusFilter] = useState<MaintenanceEventStatus | 'all'>('all');

  const query = useMaintenanceEvents({
    status: statusFilter === 'all' ? undefined : statusFilter,
  });
  const events: MaintenanceEvent[] = query.data?.data ?? [];

  // Bucket events into chronological groups. The list is one flat sequence
  // of headers + items so LegendList can virtualize the whole thing.
  type Row =
    | { kind: 'header'; id: string; label: string; count: number; accent: string }
    | { kind: 'event'; event: MaintenanceEvent };
  const rows: Row[] = useMemo(() => groupEvents(events, palette), [events, palette]);

  const FILTERS: { id: MaintenanceEventStatus | 'all'; label: string }[] = [
    { id: 'all', label: 'All' },
    { id: 'pending', label: 'Pending' },
    { id: 'in_progress', label: 'Running' },
    { id: 'completed', label: 'Done' },
    { id: 'cancelled', label: 'Cancelled' },
  ];

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        title={t('Maintenance events')}
        subtitle={`MAINTENANCE · ${events.length} ${t('EVENTS').toUpperCase()}`}
      />

      <View style={styles.filtersRow}>
        {FILTERS.map((f) => {
          const on = f.id === statusFilter;
          return (
            <Pressable
              key={f.id}
              onPress={() => setStatusFilter(f.id)}
              style={({ pressed }) => [
                styles.filterChip,
                {
                  backgroundColor: on ? palette.text : palette.surface,
                  borderColor: on ? palette.text : palette.border,
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <Mono
                size={10.5}
                weight="700"
                letterSpacing={0.5}
                color={on ? palette.background : palette.text}>
                {t(f.label).toUpperCase()}
              </Mono>
            </Pressable>
          );
        })}
      </View>

      {query.isLoading ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <LegendList
          data={rows}
          keyExtractor={(r: Row) =>
            r.kind === 'header' ? `h-${r.id}` : `e-${r.event.id}`
          }
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} />}
          ListEmptyComponent={<EmptyState title={t('No events')} />}
          ListFooterComponent={
            canCreate ? (
              <Pressable
                onPress={() => router.push('/maintenance/events/new' as never)}
                style={({ pressed }) => [
                  styles.addBtn,
                  { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
                ]}>
                <FontAwesome name="plus" size={12} color={palette.text} />
                <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
                  {t('NEW EVENT')}
                </Mono>
              </Pressable>
            ) : null
          }
          renderItem={({ item }) =>
            item.kind === 'header' ? (
              <View style={styles.headerRow}>
                <View style={[styles.headerAccent, { backgroundColor: item.accent }]} />
                <Mono
                  size={10}
                  color={palette.textFaint}
                  weight="700"
                  letterSpacing={0.8}>
                  {t(item.label).toUpperCase()} · {item.count}
                </Mono>
              </View>
            ) : (
              <Pressable
                onPress={() => router.push(`/maintenance/events/${item.event.id}` as never)}
                style={({ pressed }) => [
                  styles.eventRow,
                  {
                    backgroundColor: palette.surface,
                    borderColor: palette.border,
                    borderLeftColor: typeIcon(item.event).color,
                    opacity: pressed ? 0.85 : 1,
                  },
                ]}>
                <View
                  style={[
                    styles.iconBadge,
                    { backgroundColor: `${typeIcon(item.event).color}22` },
                  ]}>
                  <FontAwesome
                    name={typeIcon(item.event).icon}
                    size={14}
                    color={typeIcon(item.event).color}
                  />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={[styles.title, { color: palette.text }]} numberOfLines={1}>
                    {item.event.title}
                  </Text>
                  <Mono
                    size={10.5}
                    color={palette.textFaint}
                    letterSpacing={0.4}
                    style={{ marginTop: 4 }}>
                    {[
                      item.event.tool?.name,
                      item.event.line?.name,
                      item.event.scheduled_at
                        ? formatWhen(item.event.scheduled_at)
                        : null,
                    ]
                      .filter(Boolean)
                      .join('  ·  ')}
                  </Mono>
                </View>
                <StatusPill
                  status={statusLabel(item.event.status)}
                  label={item.event.status.replace('_', ' ')}
                />
              </Pressable>
            )
          }
          ItemSeparatorComponent={() => <View style={{ height: 6 }} />}
        />
      )}
    </View>
  );
}

// ── Helpers ────────────────────────────────────────────────────────────────

function groupEvents(
  events: MaintenanceEvent[],
  palette: typeof Colors.light,
): (
  | { kind: 'header'; id: string; label: string; count: number; accent: string }
  | { kind: 'event'; event: MaintenanceEvent }
)[] {
  const now = new Date();
  const today = startOfDay(now);
  const weekEnd = addDays(today, 7);

  const buckets: Record<
    'overdue' | 'today' | 'week' | 'later' | 'past',
    MaintenanceEvent[]
  > = { overdue: [], today: [], week: [], later: [], past: [] };

  for (const e of events) {
    // Completed/cancelled events go to Past regardless of scheduled time.
    if (e.status === 'completed' || e.status === 'cancelled') {
      buckets.past.push(e);
      continue;
    }
    if (!e.scheduled_at) {
      buckets.later.push(e);
      continue;
    }
    const when = parseDate(e.scheduled_at);
    if (!when) {
      buckets.later.push(e);
      continue;
    }
    if (when < today) buckets.overdue.push(e);
    else if (isToday(when)) buckets.today.push(e);
    else if (when < weekEnd) buckets.week.push(e);
    else buckets.later.push(e);
  }

  const sortByDate = (list: MaintenanceEvent[]) =>
    [...list].sort((a, b) => {
      const ta = a.scheduled_at ? +parseDate(a.scheduled_at)! : 0;
      const tb = b.scheduled_at ? +parseDate(b.scheduled_at)! : 0;
      return ta - tb;
    });

  const sections: Array<{
    id: string;
    label: string;
    accent: string;
    items: MaintenanceEvent[];
  }> = [
    { id: 'overdue', label: 'Overdue', accent: palette.danger, items: sortByDate(buckets.overdue) },
    { id: 'today', label: 'Today', accent: BRAND.amber, items: sortByDate(buckets.today) },
    { id: 'week', label: 'This week', accent: palette.success, items: sortByDate(buckets.week) },
    { id: 'later', label: 'Later', accent: palette.textMuted, items: sortByDate(buckets.later) },
    { id: 'past', label: 'Past', accent: palette.textFaint, items: sortByDate(buckets.past).reverse() },
  ];

  const rows: ReturnType<typeof groupEvents> = [];
  for (const s of sections) {
    if (s.items.length === 0) continue;
    rows.push({ kind: 'header', id: s.id, label: s.label, count: s.items.length, accent: s.accent });
    for (const ev of s.items) rows.push({ kind: 'event', event: ev });
  }
  return rows;
}

function parseDate(iso: string): Date | null {
  try {
    const d = parseISO(iso);
    return Number.isNaN(d.getTime()) ? null : d;
  } catch {
    return null;
  }
}

function formatWhen(iso: string): string {
  const d = parseDate(iso);
  if (!d) return iso.slice(0, 16).replace('T', ' ');
  return format(d, isToday(d) ? "'today' HH:mm" : 'dd MMM HH:mm');
}

const STATUS_MAP: Record<MaintenanceEventStatus, string> = {
  pending: 'PENDING',
  in_progress: 'IN_PROGRESS',
  completed: 'DONE',
  cancelled: 'CANCELLED',
};
function statusLabel(s: MaintenanceEventStatus) {
  return STATUS_MAP[s];
}

function typeIcon(e: MaintenanceEvent): {
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  color: string;
} {
  switch (e.event_type) {
    case 'inspection':
      return { icon: 'shield', color: '#1C9A55' };
    case 'planned':
      return { icon: 'cog', color: '#EA5A2B' };
    case 'corrective':
    default:
      return { icon: 'wrench', color: '#D6442F' };
  }
}

const styles = StyleSheet.create({
  filtersRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    paddingHorizontal: 18,
    paddingTop: 8,
    paddingBottom: 12,
  },
  filterChip: {
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 4,
    borderWidth: 1,
  },
  list: { paddingHorizontal: 18, paddingBottom: 32 },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingTop: 14,
    paddingBottom: 6,
  },
  headerAccent: { width: 8, height: 8, borderRadius: 4 },
  eventRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderLeftWidth: 3,
  },
  iconBadge: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: { fontSize: 14, fontWeight: '600' },
  addBtn: {
    marginTop: 14,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
});
