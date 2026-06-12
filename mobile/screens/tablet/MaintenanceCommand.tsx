import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useMaintenanceEvents,
  useMaintenanceSchedules,
} from '@/hooks/queries/useMaintenance';
import type { MaintenanceEvent } from '@/api/maintenance';

const STATE_COLOR: Record<string, string> = {
  pending: BRAND.amber,
  in_progress: BRAND.amber,
  completed: '#1C9A55',
  cancelled: '#9B9892',
  overdue: '#D6442F',
};

function eventStateLabel(e: MaintenanceEvent): keyof typeof STATE_COLOR {
  if (e.status === 'completed') return 'completed';
  if (e.status === 'cancelled') return 'cancelled';
  // Overdue when scheduled in the past and not started
  if (
    e.scheduled_at &&
    new Date(e.scheduled_at).getTime() < Date.now() &&
    e.status !== 'in_progress'
  ) {
    return 'overdue';
  }
  return e.status === 'in_progress' ? 'in_progress' : 'pending';
}

/**
 * Tablet Maintenance Command — 3-pane:
 *  - LEFT: schedules (templates).
 *  - CENTER: generated events scoped to the selected schedule.
 *  - RIGHT: event detail with the static checklist + Reassign / Start CTAs.
 */
export function TabletMaintenanceCommand() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const schedulesQ = useMaintenanceSchedules();
  const schedules = schedulesQ.data ?? [];
  const eventsQ = useMaintenanceEvents({ per_page: 100 });
  const events: MaintenanceEvent[] = eventsQ.data?.data ?? [];

  const [scheduleId, setScheduleId] = useState<number | null>(null);
  const [eventId, setEventId] = useState<number | null>(null);

  const filtered = useMemo(() => {
    if (scheduleId == null) return events;
    // Events generated from a schedule don't currently carry a schedule_id on
    // the mobile MaintenanceEvent type, so we fall through to all-events here.
    // When that field exists, swap the filter.
    return events;
  }, [events, scheduleId]);

  const selected = useMemo(() => {
    if (filtered.length === 0) return null;
    return filtered.find((e) => e.id === eventId) ?? filtered[0];
  }, [filtered, eventId]);

  const openCount = events.filter(
    (e) => e.status === 'pending' || e.status === 'in_progress',
  ).length;
  const overdueCount = events.filter((e) => eventStateLabel(e) === 'overdue').length;

  return (
    <TabletShell
      eyebrow={`${t('MAINTENANCE COMMAND').toUpperCase()} · ${openCount} ${t('OPEN').toUpperCase()} · ${overdueCount} ${t('OVERDUE').toUpperCase()}`}
      title={t('Schedules & events')}
      right={
        <Pressable
          onPress={() => router.push('/maintenance/schedules' as never)}
          style={({ pressed }) => [
            styles.newBtn,
            { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
          ]}>
          <FontAwesome name="plus" size={12} color="#1a1208" />
          <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.5}>
            {t('NEW SCHEDULE').toUpperCase()}
          </Mono>
        </Pressable>
      }>
      <View style={styles.grid}>
        {/* LEFT — schedules */}
        <View
          style={[
            styles.panel,
            styles.schedulesPanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
            {t('SCHEDULES').toUpperCase()} · {schedules.length}
          </Mono>
          <ScrollView style={{ flex: 1, marginTop: 12 }} contentContainerStyle={{ gap: 6 }}>
            <Pressable
              onPress={() => setScheduleId(null)}
              style={({ pressed }) => [
                styles.scheduleRow,
                {
                  backgroundColor:
                    scheduleId == null ? BRAND.amberSoft : 'transparent',
                  borderColor: scheduleId == null ? BRAND.amber : 'transparent',
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <FontAwesome
                name="th-list"
                size={14}
                color={scheduleId == null ? BRAND.amber : palette.textMuted}
              />
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text
                  style={[
                    styles.scheduleName,
                    { color: palette.text, fontWeight: scheduleId == null ? '700' : '600' },
                  ]}>
                  {t('All events')}
                </Text>
                <Mono size={9} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 2 }}>
                  {events.length} {t('GENERATED').toUpperCase()}
                </Mono>
              </View>
            </Pressable>
            {schedules.map((s) => {
              const on = scheduleId === s.id;
              const overdue =
                s.next_due_at &&
                new Date(s.next_due_at).getTime() < Date.now();
              return (
                <Pressable
                  key={s.id}
                  onPress={() => setScheduleId(s.id)}
                  style={({ pressed }) => [
                    styles.scheduleRow,
                    {
                      backgroundColor: on ? BRAND.amberSoft : 'transparent',
                      borderColor: on
                        ? BRAND.amber
                        : overdue
                          ? `${palette.danger}40`
                          : 'transparent',
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}>
                  <FontAwesome
                    name={overdue ? 'exclamation-triangle' : 'wrench'}
                    size={14}
                    color={overdue ? palette.danger : on ? BRAND.amber : palette.textMuted}
                  />
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text
                      style={[
                        styles.scheduleName,
                        {
                          color: palette.text,
                          fontWeight: on ? '700' : '600',
                        },
                      ]}
                      numberOfLines={1}>
                      {s.name}
                    </Text>
                    <Mono size={9} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 2 }}>
                      {String(s.frequency).toUpperCase()}
                    </Mono>
                  </View>
                </Pressable>
              );
            })}
            {schedules.length === 0 ? (
              <Mono
                size={11}
                color={palette.textFaint}
                style={{ textAlign: 'center', padding: 16 }}>
                {t('No schedules yet').toUpperCase()}
              </Mono>
            ) : null}
          </ScrollView>
        </View>

        {/* CENTER — events */}
        <View
          style={[
            styles.panel,
            styles.eventsPanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <View style={[styles.eventsHead, { borderBottomColor: palette.border }]}>
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
              {t('GENERATED EVENTS').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint}>
              {filtered.length} · {t('LAST 30D').toUpperCase()}
            </Mono>
          </View>
          <ScrollView style={{ flex: 1 }}>
            {filtered.map((e, i, arr) => {
              const state = eventStateLabel(e);
              const color = STATE_COLOR[state];
              const sel = e.id === selected?.id;
              return (
                <Pressable
                  key={e.id}
                  onPress={() => setEventId(e.id)}
                  style={({ pressed }) => [
                    styles.eventRow,
                    {
                      backgroundColor: sel
                        ? BRAND.amberSoft
                        : state === 'overdue'
                          ? '#fef0f0'
                          : 'transparent',
                      borderLeftColor: sel
                        ? BRAND.amber
                        : state === 'overdue'
                          ? palette.danger
                          : 'transparent',
                      borderBottomColor: palette.border,
                      borderBottomWidth:
                        i === arr.length - 1 ? 0 : StyleSheet.hairlineWidth,
                      opacity: pressed ? 0.9 : 1,
                    },
                  ]}>
                  <View
                    style={[
                      styles.eventIcon,
                      { backgroundColor: `${color}22` },
                    ]}>
                    <FontAwesome name="wrench" size={18} color={color} />
                  </View>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Mono size={11} color={palette.textFaint} letterSpacing={0.3}>
                      ME-{String(e.id).padStart(4, '0')}
                    </Mono>
                    <Text
                      style={[styles.eventTitle, { color: palette.text }]}
                      numberOfLines={1}>
                      {e.title}
                    </Text>
                    {e.tool ? (
                      <Mono
                        size={10}
                        color={palette.textFaint}
                        letterSpacing={0.4}
                        style={{ marginTop: 4 }}>
                        {(e.tool.code ?? e.tool.name).toUpperCase()}
                      </Mono>
                    ) : null}
                  </View>
                  <View style={{ alignItems: 'flex-end' }}>
                    <Mono
                      size={10}
                      color={color}
                      weight="700"
                      letterSpacing={0.5}>
                      {scheduledLabel(e).toUpperCase()}
                    </Mono>
                    <View
                      style={[
                        styles.statePill,
                        { backgroundColor: `${color}22` },
                      ]}>
                      <Mono size={9.5} color={color} weight="700" letterSpacing={0.5}>
                        {state.toUpperCase()}
                      </Mono>
                    </View>
                  </View>
                </Pressable>
              );
            })}
            {filtered.length === 0 ? (
              <View style={{ padding: 24, alignItems: 'center' }}>
                <Mono size={11} color={palette.textFaint}>
                  {t('No maintenance events').toUpperCase()}
                </Mono>
              </View>
            ) : null}
          </ScrollView>
        </View>

        {/* RIGHT — detail */}
        <View
          style={[
            styles.panel,
            styles.detailPanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          {selected ? (
            <ScrollView contentContainerStyle={{ gap: 14 }}>
              <View>
                <Mono size={10.5} color={BRAND.amber} weight="700" letterSpacing={0.8}>
                  {eventStateLabel(selected).toUpperCase()} ·{' '}
                  ME-{String(selected.id).padStart(4, '0')}
                </Mono>
                <Text style={[styles.detailTitle, { color: palette.text }]}>
                  {selected.title}
                </Text>
                {selected.scheduled_at ? (
                  <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 4 }}>
                    {scheduledLabel(selected).toUpperCase()}
                  </Mono>
                ) : null}
              </View>

              <View style={styles.kvGrid}>
                <KV
                  label={t('TOOL')}
                  value={selected.tool?.code ?? selected.tool?.name ?? '—'}
                  palette={palette}
                />
                <KV
                  label={t('LINE')}
                  value={selected.line?.name ?? '—'}
                  palette={palette}
                />
                <KV
                  label={t('TYPE')}
                  value={selected.event_type.toUpperCase()}
                  palette={palette}
                />
                <KV
                  label={t('STATUS')}
                  value={selected.status.toUpperCase()}
                  palette={palette}
                />
              </View>

              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
                {t('NOTES').toUpperCase()}
              </Mono>
              <View
                style={[
                  styles.notesBlock,
                  { backgroundColor: palette.surfaceAlt },
                ]}>
                <Text
                  style={{ color: palette.text, fontSize: 12, lineHeight: 18 }}>
                  {selected.description ?? t('No description provided.')}
                </Text>
              </View>

              <View style={styles.actionRow}>
                <Pressable
                  style={({ pressed }) => [
                    styles.actionBtnGhost,
                    { borderColor: palette.border, opacity: pressed ? 0.85 : 1 },
                  ]}>
                  <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
                    {t('REASSIGN').toUpperCase()}
                  </Mono>
                </Pressable>
                <Pressable
                  onPress={() =>
                    router.push(
                      `/maintenance/events/${selected.id}` as never,
                    )
                  }
                  style={({ pressed }) => [
                    styles.actionBtnPrimary,
                    { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
                  ]}>
                  <Mono size={11} color="#1a1208" weight="700" letterSpacing={0.5}>
                    {t('OPEN EVENT').toUpperCase()}
                  </Mono>
                </Pressable>
              </View>
            </ScrollView>
          ) : (
            <Mono
              size={11}
              color={palette.textFaint}
              style={{ textAlign: 'center', padding: 24 }}>
              {t('Pick an event to view details').toUpperCase()}
            </Mono>
          )}
        </View>
      </View>
    </TabletShell>
  );
}

function KV({
  label,
  value,
  palette,
}: {
  label: string;
  value: string;
  palette: typeof Colors.light;
}) {
  return (
    <View style={[styles.kvTile, { backgroundColor: palette.surfaceAlt }]}>
      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.5}>
        {label}
      </Mono>
      <Mono size={12} color={palette.text} weight="700" style={{ marginTop: 4 }}>
        {value}
      </Mono>
    </View>
  );
}

function scheduledLabel(e: MaintenanceEvent): string {
  if (!e.scheduled_at) return '—';
  try {
    const t = new Date(e.scheduled_at).getTime();
    const now = Date.now();
    const days = Math.round((t - now) / (24 * 60 * 60 * 1000));
    if (days < -1) return `${Math.abs(days)}d ago`;
    if (days === -1) return 'Yesterday';
    if (days === 0) return `Today ${format(parseISO(e.scheduled_at), 'HH:mm')}`;
    if (days === 1) return 'Tomorrow';
    if (days < 14) return `In ${days}d`;
    return format(parseISO(e.scheduled_at), 'MMM d');
  } catch {
    return '—';
  }
}

const styles = StyleSheet.create({
  grid: { flex: 1, flexDirection: 'row', gap: 14, minHeight: 0 },
  panel: { borderRadius: 16, borderWidth: 1, padding: 14 },
  schedulesPanel: { width: 260 },
  eventsPanel: { flex: 1, padding: 0, overflow: 'hidden' },
  detailPanel: { width: 400 },

  newBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    height: 38,
    paddingHorizontal: 14,
    borderRadius: 10,
  },

  scheduleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
  },
  scheduleName: { fontSize: 12 },

  eventsHead: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  eventRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderLeftWidth: 3,
  },
  eventIcon: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  eventTitle: { fontSize: 13, fontWeight: '600', marginTop: 4 },
  statePill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 4, marginTop: 6 },

  detailTitle: { fontSize: 18, fontWeight: '700', marginTop: 4, letterSpacing: -0.3 },
  kvGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  kvTile: { flexBasis: '47%', flexGrow: 1, padding: 10, borderRadius: 8 },
  notesBlock: { padding: 12, borderRadius: 10 },

  actionRow: { flexDirection: 'row', gap: 8 },
  actionBtnGhost: {
    flex: 1,
    height: 48,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionBtnPrimary: {
    flex: 1,
    height: 48,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
