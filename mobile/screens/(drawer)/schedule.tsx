import { addDays, endOfDay, format, isSameDay, isToday as dfnsIsToday, startOfDay } from 'date-fns';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import * as WebBrowser from 'expo-web-browser';

import { Card } from '@/components/ui/Card';
import { DraggableBlockBar } from '@/components/schedule/DraggableBlockBar';
import { EditScheduleModal } from '@/components/schedule/EditScheduleModal';
import { LiveDot } from '@/components/ui/LiveDot';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useScheduleRealtime } from '@/hooks/useScheduleRealtime';
import { useOperatorsOnShift, useScheduleEvents } from '@/hooks/queries/useSystem';
import { useWorkers } from '@/hooks/queries/useHr';
import { useLines } from '@/hooks/queries/useUsers';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import type { MockGanttBlock, OperatorOnShift, ScheduleEvent } from '@/api/system';
import { isWorkOrderOverdue } from '@/lib/statusLabels';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import { useSettingsStore } from '@/stores/settingsStore';

// Full-day timeline — 0..24h. The strip is wider than the screen and scrolls
// horizontally; we auto-scroll so 06:00 is visible at mount (matches the
// design's A-shift entry point).
const DAY_HOURS = 24;
const HOUR_COL_WIDTH = 52;
const TIMELINE_WIDTH = DAY_HOURS * HOUR_COL_WIDTH;
const INITIAL_HOUR = 6; // scroll-to-on-mount and label tone "before this is past"
const HOUR_LABELS = Array.from({ length: DAY_HOURS + 1 }).map((_, i) =>
  String(i).padStart(2, '0'),
);
const ROW_HEIGHT = 36;
const LINE_LABEL_WIDTH = 56;
const ROW_GAP = 8;

export function ScheduleScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const user = useAuthStore((s) => s.user);
  const canSeeSchedule = isSupervisorOrAdmin(user);
  const serverUrl = useSettingsStore((s) => s.serverUrl);
  const { useTabletLayout } = useDeviceClass();

  // Subscribe to live schedule events (Reverb). Falls back to polling
  // silently if the WebSocket can't connect.
  useScheduleRealtime(canSeeSchedule);

  const today = startOfDay(new Date());
  const [selectedDay, setSelectedDay] = useState<Date>(today);
  // Default to weekly on tablet (matches web planner's 3-column layout); on
  // phone, daily Gantt + day-strip is the better fit.
  const [mode, setMode] = useState<'day' | 'week'>(useTabletLayout ? 'week' : 'day');

  // Edit-schedule modal — tap a Gantt bar to open. Null = closed.
  const [editBlock, setEditBlock] = useState<MockGanttBlock | null>(null);

  const days = useMemo(
    () => Array.from({ length: 7 }).map((_, i) => addDays(today, i)),
    [today.getTime()],
  );

  // Date window — day mode pulls one day, week mode pulls 7 days from
  // selectedDay forward.
  const fromIso = format(selectedDay, 'yyyy-MM-dd');
  const toEnd = mode === 'week' ? endOfDay(addDays(selectedDay, 6)) : endOfDay(selectedDay);
  const toIso = format(toEnd, "yyyy-MM-dd'T'HH:mm:ssXXX");

  const linesQuery = useLines();
  const workersQuery = useWorkers({});

  // Real /api/v1/system/schedule events (Admin/Supervisor-gated on the
  // backend). 30s polling lives in useScheduleEvents.
  const eventsQuery = useScheduleEvents(
    canSeeSchedule ? { from: fromIso, to: toIso, type: 'all' } : { from: '', to: '' },
  );
  const events: ScheduleEvent[] = eventsQuery.data ?? [];

  // Active work orders feed the live tracking strip — polled every 30s. Kept
  // lightweight (status filter only); the schedule cards below give the full
  // picture.
  const activeOrdersQuery = useWorkOrders(
    {
      status: ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'BLOCKED', 'PAUSED'],
      per_page: 100,
    },
    { refetchInterval: 30_000 },
  );
  const activeOrders = activeOrdersQuery.data ?? [];
  const inProgressCount = activeOrders.filter((o) => o.status === 'IN_PROGRESS').length;
  const overdueCount = activeOrders.filter((o) => isWorkOrderOverdue(o)).length;
  const dueTodayCount = activeOrders.filter(
    (o) => o.due_date && dfnsIsToday(new Date(o.due_date)),
  ).length;

  const lines = useMemo(() => linesQuery.data ?? [], [linesQuery.data]);
  const workers = useMemo(() => workersQuery.data?.data ?? [], [workersQuery.data?.data]);

  // Real schedule events bucketed by line_id. Lines with no events render
  // empty rows on the Gantt — no synthetic fillers.
  const blocksByLine = useMemo(() => {
    const byLine: Record<number, MockGanttBlock[]> = {};
    for (const e of events) {
      if (e.line_id == null || !e.starts_at) continue;
      const start = new Date(e.starts_at);
      if (Number.isNaN(start.getTime())) continue;
      const end = e.ends_at ? new Date(e.ends_at) : null;
      const startHour = start.getHours() + start.getMinutes() / 60;
      const durationHours =
        end && !Number.isNaN(end.getTime())
          ? Math.max(0.25, (end.getTime() - start.getTime()) / 3_600_000)
          : 1;
      const block: MockGanttBlock = {
        id: `${e.type}-${e.id}`,
        title: e.title,
        startHour,
        durationHours,
        status:
          e.type === 'maintenance'
            ? 'maintenance'
            : e.status === 'IN_PROGRESS'
            ? 'running'
            : e.status === 'PAUSED'
            ? 'paused'
            : e.status === 'BLOCKED'
            ? 'blocked'
            : 'queued',
        kind: e.type,
        workOrderId: e.type === 'work_order' ? e.id : null,
        lineId: e.line_id ?? null,
        plannedStartAt: e.starts_at ?? null,
        plannedEndAt: e.ends_at ?? null,
      };
      (byLine[e.line_id] ??= []).push(block);
    }
    return byLine;
  }, [events]);

  // TODO(api/shift-attendance): mocked client-side until the demo backend ships
  // /api/v1/system/operators-on-shift. See api/system.ts for the swap path.
  const operatorsQuery = useOperatorsOnShift(fromIso, workers, lines);
  const operators: OperatorOnShift[] = operatorsQuery.data ?? [];

  const onSiteCount = operators.filter((o) => o.status !== 'no-show').length;

  // "now" indicator — only shown when selected day is today.
  const isToday = isSameDay(selectedDay, today);
  const now = new Date();
  const nowHour = now.getHours() + now.getMinutes() / 60;
  const nowOffsetPx = isToday ? nowHour * HOUR_COL_WIDTH : null;

  // Keep header + body horizontal scroll in sync, and auto-scroll to 06:00 on
  // first mount only — switching days preserves the user's drag position.
  const headerScrollRef = useRef<ScrollView>(null);
  const bodyScrollRef = useRef<ScrollView>(null);
  const didInitialScroll = useRef(false);

  useEffect(() => {
    if (didInitialScroll.current) return;
    didInitialScroll.current = true;
    const x = INITIAL_HOUR * HOUR_COL_WIDTH;
    requestAnimationFrame(() => {
      headerScrollRef.current?.scrollTo({ x, animated: false });
      bodyScrollRef.current?.scrollTo({ x, animated: false });
    });
  }, []);

  const isLoading = linesQuery.isLoading;
  const isError = linesQuery.isError;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        title={t('Schedule')}
        subtitle={`${format(selectedDay, 'EEE dd MMM').toUpperCase()} · A-SHIFT 06–14`}
        rightSlot={
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
            <LiveDot />
            <Pressable
              onPress={() => setSelectedDay(today)}
              hitSlop={8}
              style={{
                width: 36,
                height: 36,
                alignItems: 'center',
                justifyContent: 'center',
                borderRadius: 10,
                borderWidth: 1,
                borderColor: palette.border,
              }}>
              <FontAwesome name="calendar" size={16} color={palette.text} />
            </Pressable>
          </View>
        }
      />

      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          <RefreshControl
            refreshing={
              linesQuery.isFetching ||
              workersQuery.isFetching ||
              activeOrdersQuery.isFetching ||
              eventsQuery.isFetching
            }
            onRefresh={() => {
              linesQuery.refetch();
              workersQuery.refetch();
              activeOrdersQuery.refetch();
              eventsQuery.refetch();
            }}
          />
        }>
        {/* Live tracking strip */}
        <View style={styles.liveStrip}>
          <LiveTile
            label={t('In Progress').toUpperCase()}
            value={inProgressCount}
            tone="primary"
          />
          <LiveTile
            label={t('Overdue').toUpperCase()}
            value={overdueCount}
            tone={overdueCount > 0 ? 'danger' : 'muted'}
          />
          <LiveTile
            label={t('Due today').toUpperCase()}
            value={dueTodayCount}
            tone="amber"
          />
        </View>

        {/* Day / Week mode toggle */}
        <View style={styles.modeRow}>
          {(['day', 'week'] as const).map((m) => {
            const active = mode === m;
            return (
              <Pressable
                key={m}
                onPress={() => setMode(m)}
                style={[
                  styles.modeChip,
                  {
                    backgroundColor: active ? palette.surfaceInverse : 'transparent',
                    borderColor: active ? palette.surfaceInverse : palette.border,
                  },
                ]}>
                <Mono
                  size={11}
                  color={active ? (scheme === 'dark' ? '#1A1917' : '#fff') : palette.textMuted}
                  weight="700"
                  letterSpacing={0.6}>
                  {m === 'day' ? t('Day').toUpperCase() : t('Week').toUpperCase()}
                </Mono>
              </Pressable>
            );
          })}
        </View>

        {/* Weekly planner — 7-day column layout. Replaces the day-strip and
        Gantt on tablet (or when the operator picks Week mode on phone). */}
        {mode === 'week' ? (
          <WeeklyPlanner
            anchorDay={selectedDay}
            events={events}
            onPickDay={(d) => {
              setSelectedDay(d);
              setMode('day');
            }}
          />
        ) : null}

        {mode === 'day' ? (
          <>
        {/* Day strip */}
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.dayStrip}>
          {days.map((d) => {
            const active = isSameDay(d, selectedDay);
            return (
              <Pressable
                key={d.toISOString()}
                onPress={() => setSelectedDay(d)}
                style={[
                  styles.dayCell,
                  {
                    backgroundColor: active ? palette.surfaceInverse : 'transparent',
                    borderColor: active ? palette.surfaceInverse : palette.border,
                  },
                ]}>
                <Mono
                  size={10}
                  color={active ? (scheme === 'dark' ? '#1A1917' : '#fff') : palette.textFaint}
                  letterSpacing={0.6}>
                  {format(d, 'EEE').toUpperCase()}
                </Mono>
                <Text
                  style={[
                    styles.dayNum,
                    {
                      color: active ? (scheme === 'dark' ? '#1A1917' : '#fff') : palette.text,
                      fontFamily: MONO,
                    },
                  ]}>
                  {format(d, 'd')}
                </Text>
              </Pressable>
            );
          })}
        </ScrollView>

        {/* Gantt timeline */}
        {isLoading ? (
          <LoadingState />
        ) : isError ? (
          <ErrorState error={linesQuery.error} onRetry={linesQuery.refetch} />
        ) : (
          <View>
            <SectionLabel
              right={
                <Mono size={11} color={palette.textFaint}>00:00 → 24:00 · DRAG</Mono>
              }>
              Hour · Line
            </SectionLabel>

            <View style={styles.timelineFrame}>
              {/* Sticky line-label column (hour header spacer + each row label) */}
              <View style={[styles.stickyLabelCol, { width: LINE_LABEL_WIDTH }]}>
                <View style={{ height: 18 }} />
                {lines.map((line) => {
                  const label = (
                    line.code ?? line.name ?? `L-${String(line.id).padStart(2, '0')}`
                  ).toUpperCase();
                  return (
                    <View
                      key={line.id}
                      style={[styles.stickyLabelCell, { height: ROW_HEIGHT, marginTop: ROW_GAP }]}>
                      <Mono size={10} color={palette.textMuted} weight="700" letterSpacing={0.4}>
                        {label}
                      </Mono>
                    </View>
                  );
                })}
              </View>

              {/* Scrollable timeline — header + rows share scroll position */}
              <View style={{ flex: 1 }}>
                <ScrollView
                  ref={headerScrollRef}
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  scrollEnabled={false}
                  contentContainerStyle={{ width: TIMELINE_WIDTH }}>
                  <View style={styles.hourHeader}>
                    {HOUR_LABELS.slice(0, -1).map((h) => (
                      <View key={h} style={[styles.hourHeaderCell, { width: HOUR_COL_WIDTH }]}>
                        <Mono size={10} color={palette.textFaint} letterSpacing={0.4}>
                          {h}
                        </Mono>
                      </View>
                    ))}
                  </View>
                </ScrollView>

                <ScrollView
                  ref={bodyScrollRef}
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  onScroll={(e) => {
                    const x = e.nativeEvent.contentOffset.x;
                    headerScrollRef.current?.scrollTo({ x, animated: false });
                  }}
                  scrollEventThrottle={16}
                  contentContainerStyle={{ width: TIMELINE_WIDTH }}>
                  {lines.length === 0 ? (
                    <View style={{ paddingVertical: 24 }}>
                      <Mono size={11} color={palette.textFaint}>NO LINES CONFIGURED</Mono>
                    </View>
                  ) : (
                    <View style={{ gap: ROW_GAP, width: TIMELINE_WIDTH }}>
                      {lines.map((line) => (
                        <GanttRow
                          key={line.id}
                          blocks={blocksByLine[line.id] ?? []}
                          nowOffsetPx={nowOffsetPx}
                          onBlockPress={canSeeSchedule ? setEditBlock : undefined}
                          canEdit={canSeeSchedule}
                        />
                      ))}
                    </View>
                  )}
                </ScrollView>
              </View>
            </View>
          </View>
        )}
          </>
        ) : null}

        {/* Scheduled events (real API data — work orders + maintenance) */}
        {canSeeSchedule ? (
          <View>
            <SectionLabel
              right={
                <Mono size={11} color={palette.textFaint}>
                  {`${events.length}`}
                </Mono>
              }>
              {t('Scheduled events')}
            </SectionLabel>

            {eventsQuery.isLoading ? (
              <LoadingState />
            ) : eventsQuery.isError ? (
              <ErrorState error={eventsQuery.error} onRetry={eventsQuery.refetch} />
            ) : events.length === 0 ? (
              <EmptyState
                title={t('No events')}
                subtitle={t('Nothing scheduled for this day.')}
              />
            ) : (
              <View style={{ gap: 8 }}>
                {events.map((e) => (
                  <EventRow key={`${e.type}-${e.id}`} event={e} />
                ))}
              </View>
            )}

            <Pressable
              onPress={() =>
                WebBrowser.openBrowserAsync(`${serverUrl}/admin/schedule`)
              }
              style={({ pressed }) => [
                styles.openWeb,
                { borderColor: palette.text, opacity: pressed ? 0.85 : 1 },
              ]}>
              <Mono size={12} color={palette.text} weight="700" letterSpacing={0.6}>
                {t('Open planner on web').toUpperCase()}
              </Mono>
              <FontAwesome name="external-link" size={12} color={palette.text} />
            </Pressable>
          </View>
        ) : null}

        {/* Operators on shift */}
        <SectionLabel
          right={
            <Mono size={11} color={palette.textFaint}>
              {`${onSiteCount} / ${operators.length}`}
            </Mono>
          }>
          {t('Operators on shift')}
        </SectionLabel>

        {operators.length === 0 ? (
          <EmptyState title={t('No operators')} subtitle={t('Add workers to populate this list.')} />
        ) : (
          <View style={{ gap: 8 }}>
            {operators.map((op) => (
              <OperatorRow key={op.id} op={op} />
            ))}
          </View>
        )}
      </ScrollView>

      <EditScheduleModal
        visible={editBlock != null}
        workOrderId={editBlock?.workOrderId ?? null}
        initialLineId={editBlock?.lineId ?? null}
        initialStartIso={editBlock?.plannedStartAt ?? null}
        initialEndIso={editBlock?.plannedEndAt ?? null}
        title={editBlock?.title}
        lines={lines}
        onClose={() => setEditBlock(null)}
      />
    </View>
  );
}

function WeeklyPlanner({
  anchorDay,
  events,
  onPickDay,
}: {
  anchorDay: Date;
  events: ScheduleEvent[];
  onPickDay: (d: Date) => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const days = useMemo(
    () => Array.from({ length: 7 }).map((_, i) => addDays(anchorDay, i)),
    [anchorDay],
  );

  const byDay = useMemo(() => {
    const m = new Map<string, ScheduleEvent[]>();
    for (const e of events) {
      if (!e.starts_at) continue;
      const key = format(new Date(e.starts_at), 'yyyy-MM-dd');
      const list = m.get(key) ?? [];
      list.push(e);
      m.set(key, list);
    }
    return m;
  }, [events]);

  return (
    <View style={styles.weekGrid}>
      {days.map((d) => {
        const key = format(d, 'yyyy-MM-dd');
        const list = (byDay.get(key) ?? []).sort((a, b) =>
          String(a.starts_at).localeCompare(String(b.starts_at)),
        );
        const isToday = dfnsIsToday(d);
        return (
          <Pressable
            key={key}
            onPress={() => onPickDay(d)}
            style={[
              styles.weekCol,
              {
                backgroundColor: palette.surface,
                borderColor: isToday ? BRAND.amber : palette.border,
              },
            ]}>
            <View style={styles.weekColHead}>
              <Mono
                size={10}
                color={isToday ? BRAND.amber : palette.textFaint}
                letterSpacing={0.7}
                weight="700">
                {format(d, 'EEE').toUpperCase()}
              </Mono>
              <Text
                style={[
                  styles.weekColDate,
                  { color: isToday ? BRAND.amber : palette.text, fontFamily: MONO },
                ]}>
                {format(d, 'd')}
              </Text>
              <Mono size={10} color={palette.textFaint} letterSpacing={0.4}>
                {list.length}
              </Mono>
            </View>
            <View style={styles.weekColBody}>
              {list.length === 0 ? (
                <Mono size={10} color={palette.textFaint} letterSpacing={0.4}>
                  —
                </Mono>
              ) : (
                list.slice(0, 6).map((e) => (
                  <View
                    key={`${e.type}-${e.id}`}
                    style={[
                      styles.weekChip,
                      {
                        backgroundColor: `${e.color}22`,
                        borderLeftColor: e.color,
                      },
                    ]}>
                    <Mono size={9} color={e.color} weight="700" letterSpacing={0.3}>
                      {e.starts_at ? format(new Date(e.starts_at), 'HH:mm') : '—'}
                    </Mono>
                    <Text
                      style={[styles.weekChipTitle, { color: palette.text }]}
                      numberOfLines={1}>
                      {e.title}
                    </Text>
                  </View>
                ))
              )}
              {list.length > 6 ? (
                <Mono size={9} color={palette.textFaint} letterSpacing={0.4}>
                  +{list.length - 6} {t('more')}
                </Mono>
              ) : null}
            </View>
          </Pressable>
        );
      })}
    </View>
  );
}

function LiveTile({
  label,
  value,
  tone,
}: {
  label: string;
  value: number;
  tone: 'primary' | 'danger' | 'amber' | 'muted';
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const color =
    tone === 'danger'
      ? palette.danger
      : tone === 'primary'
      ? palette.success
      : tone === 'amber'
      ? BRAND.amber
      : palette.textMuted;
  return (
    <View style={[styles.liveTile, { backgroundColor: palette.surface, borderColor: palette.border }]}>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.6}>
        {label}
      </Mono>
      <Text style={[styles.liveValue, { color, fontFamily: MONO }]}>{value}</Text>
    </View>
  );
}

function EventRow({ event }: { event: ScheduleEvent }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const start = event.starts_at ? new Date(event.starts_at) : null;
  const timeLabel = start ? format(start, 'HH:mm') : '—';
  const icon: React.ComponentProps<typeof FontAwesome>['name'] =
    event.type === 'maintenance' ? 'wrench' : 'cube';

  return (
    <Card>
      <View style={styles.eventRow}>
        <View style={[styles.eventIcon, { backgroundColor: palette.surfaceAlt }]}>
          <FontAwesome name={icon} size={14} color={palette.textMuted} />
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={[styles.eventTitle, { color: palette.text }]} numberOfLines={1}>
            {event.title}
          </Text>
          <Mono size={11} color={palette.textFaint} style={{ marginTop: 2 }}>
            {timeLabel}
          </Mono>
        </View>
        <StatusPill status={event.status} />
      </View>
    </Card>
  );
}

function GanttRow({
  blocks,
  nowOffsetPx,
  onBlockPress,
  canEdit,
}: {
  blocks: MockGanttBlock[];
  nowOffsetPx: number | null;
  onBlockPress?: (b: MockGanttBlock) => void;
  canEdit: boolean;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  return (
    <View
      style={[
        styles.rowTrack,
        {
          backgroundColor: palette.surfaceAlt,
          height: ROW_HEIGHT,
          width: TIMELINE_WIDTH,
        },
      ]}>
      {/* Hour grid lines */}
      {Array.from({ length: DAY_HOURS - 1 }).map((_, i) => (
        <View
          key={i}
          style={[
            styles.gridLine,
            {
              left: (i + 1) * HOUR_COL_WIDTH,
              backgroundColor: scheme === 'dark' ? '#E6E4DE' : '#dcd8ce',
              pointerEvents: 'none',
            },
          ]}
        />
      ))}
      {/* Now indicator */}
      {nowOffsetPx != null ? (
        <View
          style={[styles.nowLine, { left: nowOffsetPx, backgroundColor: BRAND.amber, pointerEvents: 'none' }]}
        />
      ) : null}
      {/* Blocks — draggable on long-press for users who can write to the
          planner; tap opens the modal for full-detail edits. */}
      {blocks.map((b) => (
        <DraggableBlockBar
          key={b.id}
          block={b}
          hourColWidth={HOUR_COL_WIDTH}
          rowHeight={ROW_HEIGHT}
          color={blockColor(b.status)}
          canEdit={canEdit}
          onTap={onBlockPress}
        />
      ))}
    </View>
  );
}

function OperatorRow({ op }: { op: OperatorOnShift }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const statusStyle =
    op.status === 'in'
      ? { bg: palette.successSoft, fg: palette.success, label: 'In', dot: palette.success }
      : op.status === 'break'
      ? { bg: '#FAF0DD', fg: '#8a5a0e', label: 'Break', dot: '#EA5A2B' }
      : op.status === 'no-show'
      ? { bg: palette.dangerSoft, fg: palette.danger, label: 'No-show', dot: palette.danger }
      : { bg: palette.surfaceAlt, fg: palette.textFaint, label: 'Out', dot: palette.textFaint };

  const initials =
    op.name
      .split(/\s+/)
      .map((p) => p[0])
      .filter(Boolean)
      .slice(0, 2)
      .join('')
      .toUpperCase() || 'OP';
  const lineLabel = op.line_code ?? op.line_name ?? null;

  return (
    <Card style={styles.opRow}>
      <View style={[styles.avatar, { backgroundColor: palette.surfaceInverse }]}>
        <Text
          style={{
            color: scheme === 'dark' ? '#1A1917' : '#fff',
            fontFamily: MONO,
            fontSize: 13,
            fontWeight: '700',
            letterSpacing: 0.4,
          }}>
          {initials}
        </Text>
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={[styles.opName, { color: palette.text }]} numberOfLines={1}>
          {op.name}
        </Text>
        <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
          {[lineLabel, op.crew].filter(Boolean).join(' · ').toUpperCase() || 'UNASSIGNED'}
        </Mono>
      </View>
      <View style={[styles.statusPill, { backgroundColor: statusStyle.bg }]}>
        <View style={[styles.statusDot, { backgroundColor: statusStyle.dot }]} />
        <Mono size={11} color={statusStyle.fg} weight="700">
          {statusStyle.label}
        </Mono>
      </View>
    </Card>
  );
}

// ── helpers ─────────────────────────────────────────────────────────────────

// Block palette tuned to read on top of the warm-grey row track. Active states
// (running/paused/blocked/maintenance) use the brand colors; queued blocks use
// a slightly darker neutral so the bar shape stays visible on the track.
function blockColor(status: MockGanttBlock['status']): { bg: string; fg: string } {
  switch (status) {
    case 'running':
      return { bg: '#1C9A55', fg: '#fff' };
    case 'paused':
      return { bg: '#EA5A2B', fg: '#1a1208' };
    case 'blocked':
      return { bg: '#D6442F', fg: '#fff' };
    case 'maintenance':
      return { bg: '#a78bfa', fg: '#fff' };
    case 'queued':
    default:
      return { bg: '#cdc9bf', fg: '#6F6C66' };
  }
}

const styles = StyleSheet.create({
  scroll: { padding: 18, gap: 18, paddingBottom: 32 },

  liveStrip: { flexDirection: 'row', gap: 8 },
  modeRow: { flexDirection: 'row', gap: 8 },
  modeChip: {
    paddingVertical: 6,
    paddingHorizontal: 14,
    borderRadius: 999,
    borderWidth: 1,
  },

  // Weekly planner
  weekGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  weekCol: {
    flexBasis: '13%',
    flexGrow: 1,
    minWidth: 96,
    borderRadius: 10,
    borderWidth: 1,
    padding: 8,
    gap: 8,
    minHeight: 160,
  },
  weekColHead: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
    gap: 4,
  },
  weekColDate: { fontSize: 18, fontWeight: '700', letterSpacing: -0.4 },
  weekColBody: { gap: 6 },
  weekChip: {
    borderLeftWidth: 3,
    paddingVertical: 4,
    paddingHorizontal: 6,
    borderRadius: 4,
    gap: 2,
  },
  weekChipTitle: { fontSize: 11, fontWeight: '600', letterSpacing: -0.1 },

  liveTile: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
    gap: 4,
  },
  liveValue: { fontSize: 24, fontWeight: '700', letterSpacing: -0.4 },

  eventRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  eventIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  eventTitle: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },

  openWeb: {
    marginTop: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 10,
    borderWidth: 1,
  },

  dayStrip: { flexDirection: 'row', gap: 8 },
  dayCell: {
    width: 48,
    paddingVertical: 8,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    gap: 4,
  },
  dayNum: { fontSize: 18, fontWeight: '700', letterSpacing: -0.4 },

  timelineFrame: { flexDirection: 'row', gap: 8 },
  stickyLabelCol: { alignItems: 'flex-start' },
  stickyLabelCell: { justifyContent: 'center', alignItems: 'flex-start' },
  hourHeader: { flexDirection: 'row', paddingBottom: 6 },
  hourHeaderCell: { alignItems: 'flex-start' },
  rowTrack: {
    borderRadius: 6,
    position: 'relative',
    overflow: 'hidden',
  },
  gridLine: { position: 'absolute', top: 0, bottom: 0, width: 1 },
  block: {
    position: 'absolute',
    top: 4,
    bottom: 4,
    borderRadius: 4,
    paddingHorizontal: 6,
    justifyContent: 'center',
    overflow: 'hidden',
  },
  blockText: { fontSize: 10, fontWeight: '700', letterSpacing: 0.3 },
  nowLine: {
    position: 'absolute',
    top: -2,
    bottom: -2,
    width: 2,
    zIndex: 5,
  },

  opRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  opName: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
  },
  statusDot: { width: 6, height: 6, borderRadius: 3 },
});
