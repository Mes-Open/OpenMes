import { useRef } from 'react';
import { ScrollView, StyleSheet, View, type View as RNView } from 'react-native';
import { useTranslation } from 'react-i18next';
import Animated, {
  runOnJS,
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { Gesture, GestureDetector } from 'react-native-gesture-handler';

import { colors, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import type { PlannerBoard as Board, PlannerLine, PlannerOrder } from '@/api/schedule';
import {
  chainChipMeta,
  dayList,
  lineLoad,
  parseDate,
  placementsOf,
  projectSegment,
  todayKey,
  weeklySlot,
  type PlacementKey,
} from '@/lib/planner/helpers';

import type { DropTarget } from './metrics';
import { loadColor, statusOf, tierColor } from './plannerTheme';

/**
 * Fourteen day sections, each a row per line — a port of the web planner's
 * DailyView.
 *
 * The web drops cards with react-dnd (HTML5 drag events). Cards here are laid
 * out by flex-wrap rather than on a fixed grid, so unlike the weekly board the
 * geometry isn't derivable: instead every line row registers itself, and the
 * rows are measured once when a drag primes (they can't move while a finger is
 * down) and hit-tested on release.
 */

const LBL_W = 150;
const DAYS_SHOWN = 14;

interface RowRect {
  lineId: number;
  date: string;
  x: number;
  y: number;
  w: number;
  h: number;
}

interface Props {
  board: Board;
  selectedId: number | null;
  canEdit: boolean;
  onDrop: (wo: PlannerOrder, placementKey: PlacementKey, target: DropTarget) => void;
  onSelect: (wo: PlannerOrder, placementKey: PlacementKey) => void;
}

export function DailyBoard({ board, selectedId, canEdit, onDrop, onSelect }: Props) {
  const { t } = useTranslation();
  const today = todayKey();
  const days = dayList(board.startDate, DAYS_SHOWN, board.showWeekends);

  // key -> row view, so a drag can measure every drop target when it primes.
  const rowRefs = useRef(new Map<string, { node: RNView | null; lineId: number; date: string }>());
  const rects = useRef<RowRect[]>([]);

  const register = (key: string, lineId: number, date: string, node: RNView | null) => {
    if (node) rowRefs.current.set(key, { node, lineId, date });
    else rowRefs.current.delete(key);
  };

  /** Measure every row once, at drag start. */
  const measureRows = () => {
    const out: RowRect[] = [];
    rowRefs.current.forEach(({ node, lineId, date }) => {
      node?.measureInWindow((x, y, w, h) => {
        out.push({ lineId, date, x, y, w, h });
      });
    });
    rects.current = out;
  };

  const hitTest = (x: number, y: number): { lineId: number; date: string } | null => {
    const hit = rects.current.find((r) => x >= r.x && x <= r.x + r.w && y >= r.y && y <= r.y + r.h);
    return hit ? { lineId: hit.lineId, date: hit.date } : null;
  };

  if (!days.length) return null;

  return (
    <ScrollView contentContainerStyle={styles.scroll}>
      {days.map((d) => {
        const count = board.lines.reduce(
          (n, line) =>
            n +
            board.workOrders.reduce(
              (m, o) =>
                m +
                placementsOf(o).filter(
                  (p) => p.line_id === line.id && weeklySlot(projectSegment(o, p), 1).date === d.date,
                ).length,
              0,
            ),
          0,
        );
        const date = parseDate(d.date);
        const isToday = d.date === today;

        return (
          <View key={d.date} style={styles.dayCard}>
            <View style={[styles.dayHead, isToday && styles.dayHeadToday]}>
              <Mono size={12} weight="700" color={isToday ? colors.accent : colors.ink}>
                {date
                  ? date.toLocaleDateString(undefined, { weekday: 'long', day: '2-digit', month: 'long' })
                  : d.date}
              </Mono>
              <Mono size={9} color={colors.faint}>
                {count} {t('orders')}
              </Mono>
            </View>

            {board.lines.map((line) => (
              <DailyLine
                key={line.id}
                line={line}
                date={d.date}
                board={board}
                selectedId={selectedId}
                canEdit={canEdit}
                register={register}
                measureRows={measureRows}
                hitTest={hitTest}
                onDrop={onDrop}
                onSelect={onSelect}
              />
            ))}
          </View>
        );
      })}
    </ScrollView>
  );
}

interface LineProps {
  line: PlannerLine;
  date: string;
  board: Board;
  selectedId: number | null;
  canEdit: boolean;
  register: (key: string, lineId: number, date: string, node: RNView | null) => void;
  measureRows: () => void;
  hitTest: (x: number, y: number) => { lineId: number; date: string } | null;
  onDrop: (wo: PlannerOrder, placementKey: PlacementKey, target: DropTarget) => void;
  onSelect: (wo: PlannerOrder, placementKey: PlacementKey) => void;
}

function DailyLine({
  line,
  date,
  board,
  selectedId,
  canEdit,
  register,
  measureRows,
  hitTest,
  onDrop,
  onSelect,
}: LineProps) {
  const { t } = useTranslation();

  // One card per schedule segment on this line/day.
  const segs = board.workOrders.flatMap((o) =>
    placementsOf(o)
      .filter((p) => p.line_id === line.id && weeklySlot(projectSegment(o, p), 1).date === date)
      .map((p) => ({ wo: o, key: p.key })),
  );
  const lc = loadColor(lineLoad(board.workOrders, line.id, [{ date, isWeekend: false }], 1) || 0);

  return (
    <View style={styles.lineRow}>
      <View style={[styles.lineLbl, { width: LBL_W }]}>
        <View style={styles.lineNameRow}>
          <View style={[styles.dot, { backgroundColor: lc }]} />
          <Mono size={11} weight="700" color={colors.ink} numberOfLines={1}>
            {line.code ?? line.name}
          </Mono>
        </View>
        <Mono size={9} color={colors.muted} numberOfLines={1}>
          {line.name}
        </Mono>
      </View>

      <View
        ref={(node) => register(`${date}:${line.id}`, line.id, date, node)}
        collapsable={false}
        style={styles.cards}>
        {segs.length === 0 ? (
          <Mono size={9} color={colors.faintest} style={styles.idle}>
            — {t('idle')} —
          </Mono>
        ) : (
          segs.map(({ wo, key }) => (
            <DailyCard
              key={`${wo.id}:${String(key)}`}
              wo={wo}
              placementKey={key}
              allLines={board.allLines}
              selected={selectedId === wo.id}
              canEdit={canEdit}
              measureRows={measureRows}
              hitTest={hitTest}
              onDrop={onDrop}
              onSelect={onSelect}
            />
          ))
        )}
      </View>
    </View>
  );
}

interface CardProps {
  wo: PlannerOrder;
  placementKey: PlacementKey;
  allLines: PlannerLine[];
  selected: boolean;
  canEdit: boolean;
  measureRows: () => void;
  hitTest: (x: number, y: number) => { lineId: number; date: string } | null;
  onDrop: (wo: PlannerOrder, placementKey: PlacementKey, target: DropTarget) => void;
  onSelect: (wo: PlannerOrder, placementKey: PlacementKey) => void;
}

function DailyCard({
  wo,
  placementKey,
  allLines,
  selected,
  canEdit,
  measureRows,
  hitTest,
  onDrop,
  onSelect,
}: CardProps) {
  const status = statusOf(wo.status);
  const chip = chainChipMeta(wo, placementKey, allLines);
  const tier = tierColor(wo.customer_tier);

  const dx = useSharedValue(0);
  const dy = useSharedValue(0);
  const lifted = useSharedValue(0);

  const reset = () => {
    dx.value = withSpring(0);
    dy.value = withSpring(0);
    lifted.value = withTiming(0);
  };

  const finish = (absX: number, absY: number) => {
    const hit = hitTest(absX, absY);
    dx.value = withSpring(0);
    dy.value = withSpring(0);
    lifted.value = withTiming(0);
    if (!hit) return;
    // The daily view is day-granular: shift 1, as on the web.
    onDrop(wo, placementKey, { lineId: hit.lineId, date: hit.date, shift: 1 });
  };

  const longPress = Gesture.LongPress()
    .minDuration(220)
    .enabled(canEdit)
    .onStart(() => {
      lifted.value = withSpring(1);
      // Rows can't move while a finger is down, so one measure per drag is enough.
      runOnJS(measureRows)();
    });

  const pan = Gesture.Pan()
    .enabled(canEdit)
    .activeOffsetX([-6, 6])
    .activeOffsetY([-6, 6])
    .onUpdate((e) => {
      if (lifted.value === 0) return;
      dx.value = e.translationX;
      dy.value = e.translationY;
    })
    .onEnd((e) => {
      if (lifted.value === 0) {
        runOnJS(reset)();
        return;
      }
      runOnJS(finish)(e.absoluteX, e.absoluteY);
    });

  const tap = Gesture.Tap()
    .maxDuration(200)
    .onEnd(() => {
      runOnJS(onSelect)(wo, placementKey);
    });

  const composed = Gesture.Race(tap, Gesture.Simultaneous(longPress, pan));

  const animStyle = useAnimatedStyle(() => ({
    transform: [{ translateX: dx.value }, { translateY: dy.value - lifted.value * 2 }, { scale: 1 + lifted.value * 0.04 }],
    zIndex: lifted.value > 0 ? 50 : 1,
    elevation: lifted.value * 5,
    opacity: lifted.value > 0 ? 0.92 : 1,
  }));

  return (
    <GestureDetector gesture={composed}>
      <Animated.View
        style={[
          styles.card,
          {
            backgroundColor: status.bg,
            borderColor: wo.is_overdue ? colors.blocked : selected ? colors.accent : colors.line2,
            borderWidth: wo.is_overdue || selected ? 1.5 : 1,
          },
          animStyle,
        ]}>
        <View style={styles.cardTop}>
          {tier ? <View style={[styles.tierDot, { backgroundColor: tier }]} /> : null}
          <Mono size={10} weight="700" color={colors.ink} numberOfLines={1}>
            {wo.order_no}
          </Mono>
          {chip ? (
            <View style={[styles.chip, { borderColor: status.fg }]}>
              <Mono size={7} color={status.fg}>
                {chip.dir === 'to' ? `→ ${chip.code}` : chip.dir === 'from' ? `${chip.code} →` : `⇄ ${chip.code}`}
              </Mono>
            </View>
          ) : null}
        </View>
        <Mono size={9} color={colors.muted} numberOfLines={1}>
          {wo.product_name || '—'}
        </Mono>
        <Mono size={8.5} color={colors.faint} numberOfLines={1}>
          {wo.planned_qty ?? '—'}
        </Mono>
      </Animated.View>
    </GestureDetector>
  );
}

const styles = StyleSheet.create({
  scroll: { gap: 12, paddingBottom: 12 },
  dayCard: {
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.card,
    overflow: 'hidden',
  },
  dayHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.panel,
  },
  dayHeadToday: { backgroundColor: 'rgba(234, 90, 43, 0.07)' },
  lineRow: { flexDirection: 'row', borderBottomWidth: 1, borderColor: colors.line2 },
  lineLbl: {
    padding: 12,
    gap: 2,
    borderRightWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.panel,
  },
  lineNameRow: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  dot: { width: 7, height: 7, borderRadius: 4 },
  cards: {
    flex: 1,
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'flex-start',
    gap: 8,
    padding: 10,
    minHeight: 56,
  },
  idle: { padding: 6 },
  card: {
    width: 160,
    borderRadius: radius.sm,
    padding: 8,
    gap: 2,
  },
  cardTop: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  tierDot: { width: 5, height: 5, borderRadius: 3 },
  chip: { borderWidth: 1, borderRadius: 3, paddingHorizontal: 3 },
});
