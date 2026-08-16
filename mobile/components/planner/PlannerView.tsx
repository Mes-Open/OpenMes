import { useMemo, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { format, parseISO, startOfWeek } from 'date-fns';
import { FontAwesome } from '@expo/vector-icons';
import { router } from 'expo-router';

import { colors, radius, spacing } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  ScheduleConflictError,
  type ExtraPlacementInput,
  type PlannerOrder,
  type PlannerViewMode,
} from '@/api/schedule';
import {
  usePlannerBoard,
  useResizeScheduleOrder,
  useUpdateScheduleOrder,
} from '@/hooks/queries/useSchedule';
import { dayList, fmtMin, slotOfCol, type PlacementKey } from '@/lib/planner/helpers';

import { BacklogRail } from './BacklogRail';
import { DailyBoard } from './DailyBoard';
import { HourlyBoard } from './HourlyBoard';
import { MonthlyBoard } from './MonthlyBoard';
import { WeeklyBoard } from './WeeklyBoard';
import type { DropTarget } from './metrics';
import { maintColors, statusOf } from './plannerTheme';

/**
 * The tablet production planner — the weekly board plus the backlog/changes rail.
 *
 * Chrome mirrors the web planner (Pages/admin/schedule/Planner.jsx): the same
 * breadcrumb, toolbar, status legend and card-framed grid, reading the shared
 * @openmes/ui tokens so both surfaces stay in step.
 *
 * Write semantics mirror it exactly too (both go through SchedulePlannerService):
 * the primary placement carries the minute plan, extra segments are coarse
 * day+shift rows synced as a full list.
 */

interface Props {
  canEdit: boolean;
}

const VIEW_TABS: { key: PlannerViewMode; label: string }[] = [
  { key: 'weekly', label: 'Weekly' },
  { key: 'daily', label: 'Daily' },
  { key: 'hourly', label: 'Hourly' },
  { key: 'monthly', label: 'Monthly' },
];

export function PlannerView({ canEdit }: Props) {
  const { t } = useTranslation();
  const [startDate, setStartDate] = useState(() =>
    format(startOfWeek(new Date(), { weekStartsOn: 1 }), 'yyyy-MM-dd'),
  );
  const [lineId, setLineId] = useState<number | null>(null);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [linePickerOpen, setLinePickerOpen] = useState(false);
  const [view, setView] = useState<PlannerViewMode>('weekly');

  // The server anchors start_date per view (week start / day / month start) and
  // hands back the range plus its own prev/next, so nav works the same for all.
  const q = usePlannerBoard({ view_mode: view, start_date: startDate, line_id: lineId });
  const update = useUpdateScheduleOrder();
  const resize = useResizeScheduleOrder();
  const board = q.data;

  const boardStart = board?.startDate;
  const showWeekends = board?.showWeekends ?? true;
  const days = useMemo(
    () => (boardStart ? dayList(boardStart, showWeekends ? 7 : 5, showWeekends) : []),
    [boardStart, showWeekends],
  );

  /**
   * Rebuild the order's full extra-segment list with one segment rewritten,
   * removed or appended — the endpoint treats `extra_placements` as
   * authoritative, so an omitted row is a deleted row.
   */
  const placementsPayload = (
    wo: PlannerOrder,
    opts: {
      update?: { key: PlacementKey; patch: Partial<ExtraPlacementInput> };
      remove?: PlacementKey;
      add?: ExtraPlacementInput;
    },
  ): ExtraPlacementInput[] => {
    let list: ExtraPlacementInput[] = (wo.placements ?? []).map((p) => ({
      id: p.id,
      line_id: p.line_id,
      due_date: p.due_date,
      shift_number: p.shift_number,
      end_date: p.end_date,
      end_shift_number: p.end_shift_number,
    }));
    if (opts.remove != null) list = list.filter((p) => p.id !== opts.remove);
    if (opts.update) list = list.map((p) => (p.id === opts.update!.key ? { ...p, ...opts.update!.patch } : p));
    if (opts.add) list = [...list, opts.add];
    return list;
  };

  const submit = (id: number, input: Parameters<typeof update.mutate>[0]['input'], force = false) => {
    update.mutate(
      { id, input: { ...input, force_conflict: force } },
      {
        onError: (e: Error) => {
          if (e instanceof ScheduleConflictError) {
            Alert.alert(t('Conflict'), e.message, [
              { text: t('Cancel'), style: 'cancel' },
              { text: t('Schedule anyway'), style: 'destructive', onPress: () => submit(id, input, true) },
            ]);
            return;
          }
          Alert.alert(t('Could not reschedule'), e.message);
        },
      },
    );
  };

  const applyMove = (wo: PlannerOrder, key: PlacementKey, target: DropTarget) => {
    if (key === 'primary') {
      // A coarse drop discards an exact minute plan — the web confirms first,
      // so do the same rather than silently destroying it.
      const doMove = () =>
        submit(wo.id, {
          line_id: target.lineId,
          due_date: target.date,
          shift_number: target.shift,
          // Clearing the span keeps end_* from pointing before the new start.
          end_date: null,
          end_shift_number: null,
          ...(wo.planned_start_at ? { planned_start_at: null, planned_end_at: null } : {}),
        });

      if (wo.planned_start_at) {
        Alert.alert(
          t('Replace exact time plan?'),
          t('This order has an exact start and end time. Moving it to a shift cell will clear them.'),
          [
            { text: t('Cancel'), style: 'cancel' },
            { text: t('Move'), style: 'destructive', onPress: doMove },
          ],
        );
        return;
      }
      doMove();
      return;
    }

    submit(wo.id, {
      extra_placements: placementsPayload(wo, {
        update: {
          key,
          patch: {
            line_id: target.lineId,
            due_date: target.date,
            shift_number: target.shift,
            end_date: null,
            end_shift_number: null,
          },
        },
      }),
    });
  };

  /** Same-line edge stretch: start and end both follow the dragged columns. */
  const applySpanChange = (wo: PlannerOrder, key: PlacementKey, startCol: number, endCol: number) => {
    const a = slotOfCol(startCol, days, board?.shiftsPerDay ?? 1);
    const b = slotOfCol(endCol, days, board?.shiftsPerDay ?? 1);
    if (!a || !b) return;
    const spanned = endCol > startCol;

    if (key === 'primary') {
      submit(wo.id, {
        due_date: a.date,
        shift_number: a.shift,
        end_date: spanned ? b.date : null,
        end_shift_number: spanned ? b.shift : null,
      });
      return;
    }
    submit(wo.id, {
      extra_placements: placementsPayload(wo, {
        update: {
          key,
          patch: {
            due_date: a.date,
            shift_number: a.shift,
            end_date: spanned ? b.date : null,
            end_shift_number: spanned ? b.shift : null,
          },
        },
      }),
    });
  };

  /**
   * Diagonal edge-stretch: the order continues on another line. The extension is
   * APPENDED as a new segment, so the chain reads as a staircase across the
   * board (any number of steps).
   */
  const applyDiagonalExtend = (wo: PlannerOrder, targetLineId: number, startCol: number, endCol: number) => {
    const a = slotOfCol(startCol, days, board?.shiftsPerDay ?? 1);
    const b = slotOfCol(endCol, days, board?.shiftsPerDay ?? 1);
    if (!a || !b) return;
    const spanned = endCol > startCol;

    submit(wo.id, {
      extra_placements: placementsPayload(wo, {
        add: {
          line_id: targetLineId,
          due_date: a.date,
          shift_number: a.shift,
          end_date: spanned ? b.date : null,
          end_shift_number: spanned ? b.shift : null,
        },
      }),
    });
  };

  /**
   * Hourly move/resize → the /resize endpoint, which owns minute-level conflict
   * detection. Times are built from the visible day, not the device clock.
   */
  const applyHourlyChange = (wo: PlannerOrder, startMin: number, endMin: number, force = false) => {
    const day = board?.startDate;
    if (!day) return;
    const iso = (m: number) => `${day}T${fmtMin(m)}:00`;
    resize.mutate(
      { id: wo.id, input: { planned_start_at: iso(startMin), planned_end_at: iso(endMin), force_conflict: force } },
      {
        onError: (e: Error) => {
          if (e instanceof ScheduleConflictError) {
            Alert.alert(t('Conflict'), e.message, [
              { text: t('Cancel'), style: 'cancel' },
              {
                text: t('Schedule anyway'),
                style: 'destructive',
                onPress: () => applyHourlyChange(wo, startMin, endMin, true),
              },
            ]);
            return;
          }
          Alert.alert(t('Could not reschedule'), e.message);
        },
      },
    );
  };

  const unassign = (wo: PlannerOrder, key: PlacementKey) => {
    if (key === 'primary') {
      // Losing the primary line clears every segment (server-side rule).
      submit(wo.id, { line_id: null, due_date: null, shift_number: null, end_date: null, end_shift_number: null });
      return;
    }
    submit(wo.id, { extra_placements: placementsPayload(wo, { remove: key }) });
  };

  const onSelect = (wo: PlannerOrder, key: PlacementKey) => {
    setSelectedId(wo.id);
    if (!canEdit) return;
    Alert.alert(
      wo.order_no,
      [wo.product_name, wo.customer_name].filter(Boolean).join(' · ') || undefined,
      [
        { text: t('Close'), style: 'cancel' },
        {
          text: key === 'primary' ? t('Return to backlog') : t('Detach this segment'),
          style: 'destructive',
          onPress: () => unassign(wo, key),
        },
      ],
    );
  };

  const shiftRange = (dir: -1 | 1) => {
    if (!board) return;
    setStartDate(dir === -1 ? board.navPrev : board.navNext);
  };

  /** Jump to now — the server re-anchors it to the active view's boundary. */
  const goToday = () => setStartDate(format(new Date(), 'yyyy-MM-dd'));

  if (q.isLoading && !board) return <LoadingState />;
  if (q.isError) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!board) return null;

  const activeLine = board.allLines.find((l) => l.id === lineId);
  const maint = maintColors();
  const legend: { label: string; color: string }[] = [
    { label: t('Running'), color: statusOf('IN_PROGRESS').fg },
    { label: t('Accepted'), color: statusOf('ACCEPTED').fg },
    { label: t('Blocked'), color: statusOf('BLOCKED').fg },
    { label: t('Paused'), color: statusOf('PAUSED').fg },
    { label: t('Maintenance'), color: maint.fg },
  ];

  return (
    <View style={styles.wrap}>
      {/* Breadcrumb + title, matching the web planner's page head. */}
      <View style={styles.head}>
        <Mono size={9} color={colors.faint} upper letterSpacing={0.8}>
          {/* `n`, not `count` — i18next treats `count` as a plural trigger and
              would look for `_one`/`_other` variants of the key. */}
          {t('Production')} · {t('{{n}} unscheduled in backlog', { n: board.backlogOrders.length })}
        </Mono>
        <Mono size={19} weight="700" color={colors.ink}>
          {t('Production Planner')}
        </Mono>
      </View>

      <View style={styles.toolbar}>
        <Pressable onPress={() => shiftRange(-1)} style={styles.navBtn} hitSlop={8}>
          <FontAwesome name="chevron-left" size={10} color={colors.ink} />
        </Pressable>
        <Mono size={11} weight="700" color={colors.ink}>
          {format(parseISO(board.rangeStart), 'dd/MM')} – {format(parseISO(board.rangeEnd), 'dd/MM/yyyy')}
        </Mono>
        <Pressable onPress={() => shiftRange(1)} style={styles.navBtn} hitSlop={8}>
          <FontAwesome name="chevron-right" size={10} color={colors.ink} />
        </Pressable>
        <Pressable onPress={goToday} style={styles.todayBtn}>
          <Mono size={10} weight="700" color={colors.accent}>
            {t('Today')}
          </Mono>
        </Pressable>

        <View style={styles.tabs}>
          {VIEW_TABS.map((v) => (
            <Pressable
              key={v.key}
              onPress={() => setView(v.key)}
              style={[styles.tab, v.key === view && styles.tabOn]}>
              <Mono size={10} weight="700" color={v.key === view ? colors.bg : colors.ink}>
                {t(v.label)}
              </Mono>
            </Pressable>
          ))}
        </View>

        {/* Line filter — a dropdown, as on the web (chips don't scale past a
            handful of lines). */}
        <Pressable onPress={() => setLinePickerOpen(true)} style={styles.select}>
          <Mono size={10.5} color={colors.ink} numberOfLines={1}>
            {activeLine ? (activeLine.code ?? activeLine.name) : t('All lines')}
          </Mono>
          <FontAwesome name="caret-down" size={11} color={colors.faint} />
        </Pressable>

        <Pressable onPress={() => router.push('/(drawer)/admin/schedule-capacity')} style={styles.link}>
          <Mono size={10} weight="700" color={colors.accent}>
            {t('Capacity')} →
          </Mono>
        </Pressable>
        <Pressable onPress={() => router.push('/(drawer)/employee-schedule')} style={styles.link}>
          <Mono size={10} color={colors.muted}>
            {t('Employees')}
          </Mono>
        </Pressable>
      </View>

      {/* Status legend */}
      <View style={styles.legend}>
        {legend.map((l) => (
          <View key={l.label} style={styles.legendItem}>
            <View style={[styles.legendDot, { backgroundColor: l.color }]} />
            <Mono size={8.5} color={colors.muted} upper letterSpacing={0.6}>
              {l.label}
            </Mono>
          </View>
        ))}
      </View>

      <View style={styles.body}>
        <View style={styles.viewWrap}>
          {view === 'weekly' ? (
            // The grid sits in a card, like the web board.
            <View style={styles.card}>
              <WeeklyBoard
                board={board}
                days={days}
                selectedId={selectedId}
                canEdit={canEdit}
                onMove={applyMove}
                onSpanChange={applySpanChange}
                onDiagonalExtend={applyDiagonalExtend}
                onSelect={onSelect}
              />
              <Mono size={9} color={colors.faint} style={styles.hint}>
                {t(
                  'Long-press a block to move it across shifts, days or lines · drag its edges to stretch · drag an edge onto another line to continue the order there · tap it for actions',
                )}
              </Mono>
            </View>
          ) : null}

          {view === 'daily' ? (
            <DailyBoard
              board={board}
              selectedId={selectedId}
              canEdit={canEdit}
              onDrop={applyMove}
              onSelect={onSelect}
            />
          ) : null}

          {view === 'hourly' ? (
            <HourlyBoard
              board={board}
              canEdit={canEdit}
              onHourlyChange={(wo, startMin, endMin) => applyHourlyChange(wo, startMin, endMin)}
              onSelect={onSelect}
            />
          ) : null}

          {view === 'monthly' ? <MonthlyBoard board={board} /> : null}
        </View>

        <BacklogRail board={board} canEdit={canEdit} />
      </View>

      {/* Line picker */}
      <Modal
        visible={linePickerOpen}
        transparent
        animationType="fade"
        onRequestClose={() => setLinePickerOpen(false)}>
        <Pressable style={styles.backdrop} onPress={() => setLinePickerOpen(false)}>
          <View style={styles.sheet}>
            <Mono size={9} color={colors.faint} upper letterSpacing={0.8} style={styles.sheetHead}>
              {t('Line')}
            </Mono>
            <ScrollView>
              {[{ id: null as number | null, code: t('All lines'), name: '' }, ...board.allLines].map((l) => (
                <Pressable
                  key={String(l.id)}
                  onPress={() => {
                    setLineId(l.id);
                    setLinePickerOpen(false);
                  }}
                  style={[styles.sheetRow, lineId === l.id && styles.sheetRowOn]}>
                  <Mono size={11} color={colors.ink}>
                    {l.code ?? l.name}
                  </Mono>
                  {l.name ? (
                    <Mono size={9} color={colors.faint}>
                      {l.name}
                    </Mono>
                  ) : null}
                </Pressable>
              ))}
            </ScrollView>
          </View>
        </Pressable>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1 },
  head: { paddingHorizontal: spacing.md, paddingTop: spacing.xs, gap: 2 },
  toolbar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    flexWrap: 'wrap',
  },
  navBtn: {
    width: 24,
    height: 24,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    alignItems: 'center',
    justifyContent: 'center',
  },
  todayBtn: { paddingHorizontal: 6, paddingVertical: 4 },
  tabs: {
    flexDirection: 'row',
    gap: 2,
    padding: 2,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
  },
  tab: { paddingHorizontal: 9, paddingVertical: 4, borderRadius: radius.sm - 2 },
  tabOn: { backgroundColor: colors.ink },
  tabOff: { opacity: 0.55 },
  select: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    minWidth: 104,
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.bg,
  },
  link: { paddingHorizontal: 4, paddingVertical: 4 },
  legend: {
    flexDirection: 'row',
    gap: spacing.md,
    paddingHorizontal: spacing.md,
    paddingBottom: spacing.sm,
    flexWrap: 'wrap',
  },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  legendDot: { width: 6, height: 6, borderRadius: 3 },
  viewWrap: { flex: 1 },
  body: {
    flex: 1,
    flexDirection: 'row',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingBottom: spacing.md,
  },
  card: {
    flex: 1,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.card,
    overflow: 'hidden',
    paddingTop: 2,
  },
  hint: { paddingHorizontal: 10, paddingVertical: 8 },
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.35)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sheet: {
    width: 260,
    maxHeight: 380,
    borderRadius: radius.md,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    padding: spacing.sm,
  },
  sheetHead: { paddingHorizontal: 6, paddingBottom: 6 },
  sheetRow: { paddingHorizontal: 8, paddingVertical: 8, borderRadius: radius.sm, gap: 1 },
  sheetRowOn: { backgroundColor: colors.chip },
});
