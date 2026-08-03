import { useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import Animated, {
  runOnJS,
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { Gesture, GestureDetector, Pressable } from 'react-native-gesture-handler';

import { colors, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import type { PlannerBoard as Board, PlannerLine, PlannerOrder } from '@/api/schedule';
import {
  chainChipMeta,
  fmtMin,
  hourlyLanes,
  parseDate,
  todayKey,
  type HourlyItem,
} from '@/lib/planner/helpers';

import { statusOf } from './plannerTheme';

/**
 * One day, minute-level — a port of the web planner's HourlyView.
 *
 * The web measures its track with getBoundingClientRect to convert pixels to
 * minutes. Here the track is a fixed width, so the conversion is a constant and
 * the drag needs no measurement.
 */

const LBL_W = 150;
/** 24h across a fixed, scrollable track — 40px/hour. */
const HOUR_W = 40;
const TRACK_W = 24 * HOUR_W;
const PX_PER_MIN = TRACK_W / 1440;
const HLANE = 40;
const HGAP = 5;
const SNAPS = [5, 10, 15, 30];

interface Props {
  board: Board;
  canEdit: boolean;
  /** Commit a minute-level move/resize (goes to the /resize endpoint). */
  onHourlyChange: (wo: PlannerOrder, startMin: number, endMin: number) => void;
  onSelect: (wo: PlannerOrder, placementKey: HourlyItem['placementKey']) => void;
}

export function HourlyBoard({ board, canEdit, onHourlyChange, onSelect }: Props) {
  const { t } = useTranslation();
  const [snap, setSnap] = useState(board.slotMinutes || 15);
  const dateStr = board.startDate;

  // 2-hour header ticks, as on the web.
  const hours = Array.from({ length: 12 }, (_, i) => String(i * 2).padStart(2, '0'));
  // The now-line reads the device's local wall clock. (Server timestamps are a
  // different matter — those are parsed from their ISO offset, never via Date.)
  const now = new Date();
  const nowMin = dateStr === todayKey() ? now.getHours() * 60 + now.getMinutes() : null;

  return (
    <View style={styles.wrap}>
      <View style={styles.snapRow}>
        <Mono size={9} color={colors.faint} upper letterSpacing={0.8}>
          {t('Snap')}
        </Mono>
        <View style={styles.snapGroup}>
          {SNAPS.map((n) => (
            <Pressable key={n} onPress={() => setSnap(n)} style={[styles.snapBtn, snap === n && styles.snapOn]}>
              <Mono size={10} color={snap === n ? colors.bg : colors.muted}>
                {n}m
              </Mono>
            </Pressable>
          ))}
        </View>
        <View style={styles.spacer} />
        <View style={styles.legendItem}>
          <View style={styles.overlapSwatch} />
          <Mono size={9} color={colors.faint}>
            {t('overlap')}
          </Mono>
        </View>
      </View>

      <View style={styles.card}>
        <ScrollView>
          <View style={styles.row}>
            {/* Frozen line labels */}
            <View style={{ width: LBL_W }}>
              <View style={styles.lblHead}>
                <Mono size={9} color={colors.faint} upper letterSpacing={0.8} numberOfLines={1}>
                  {parseDate(dateStr)?.toLocaleDateString(undefined, {
                    weekday: 'short',
                    day: '2-digit',
                    month: 'short',
                  })}
                </Mono>
              </View>
              {board.lines.map((line) => {
                const { totalLanes } = hourlyLanes(board.workOrders, line.id, dateStr);
                return (
                  <View key={line.id} style={[styles.lblCell, { height: trackHeight(totalLanes) }]}>
                    <View style={styles.lblNameRow}>
                      <View style={[styles.dot, { backgroundColor: colors.accent }]} />
                      <Mono size={11} weight="700" color={colors.ink} numberOfLines={1}>
                        {line.code ?? line.name}
                      </Mono>
                    </View>
                    <Mono size={9} color={colors.muted} numberOfLines={1}>
                      {line.name}
                    </Mono>
                  </View>
                );
              })}
            </View>

            <ScrollView horizontal contentContainerStyle={{ width: TRACK_W }}>
              <View style={{ width: TRACK_W }}>
                {/* Hour header */}
                <View style={styles.hourHead}>
                  {hours.map((h) => (
                    <View key={h} style={[styles.hourCell, { width: TRACK_W / 12 }]}>
                      <Mono size={9} color={colors.faint}>
                        {h}
                      </Mono>
                    </View>
                  ))}
                </View>

                {board.lines.map((line) => {
                  const { items, totalLanes } = hourlyLanes(board.workOrders, line.id, dateStr);
                  return (
                    <View key={line.id} style={[styles.track, { height: trackHeight(totalLanes) }]}>
                      {/* Hour gridlines */}
                      <View style={styles.gridlines} pointerEvents="none">
                        {hours.map((h) => (
                          <View key={h} style={[styles.gridline, { width: TRACK_W / 12 }]} />
                        ))}
                      </View>

                      {nowMin != null ? (
                        <View style={[styles.nowLine, { left: nowMin * PX_PER_MIN }]} pointerEvents="none">
                          <View style={styles.nowDot} />
                        </View>
                      ) : null}

                      {items.map((it) => (
                        <HourlyBar
                          key={`${it.wo.id}:${String(it.placementKey)}`}
                          item={it}
                          allLines={board.allLines}
                          snap={snap}
                          canEdit={canEdit}
                          onHourlyChange={onHourlyChange}
                          onSelect={onSelect}
                        />
                      ))}
                    </View>
                  );
                })}
              </View>
            </ScrollView>
          </View>
        </ScrollView>
      </View>

      <Mono size={9} color={colors.faint} style={styles.hint}>
        {t('Long-press a bar to move it · drag its edges to resize · snaps to {{n}} min', { n: snap })}
      </Mono>
    </View>
  );
}

function trackHeight(lanes: number): number {
  return Math.max(1, lanes) * (HLANE + HGAP) + 11;
}

interface BarProps {
  item: HourlyItem;
  allLines: PlannerLine[];
  snap: number;
  canEdit: boolean;
  onHourlyChange: (wo: PlannerOrder, startMin: number, endMin: number) => void;
  onSelect: (wo: PlannerOrder, placementKey: HourlyItem['placementKey']) => void;
}

function HourlyBar({ item, allLines, snap, canEdit, onHourlyChange, onSelect }: BarProps) {
  const { wo } = item;
  const status = statusOf(wo.status);
  const chip = chainChipMeta(wo, item.placementKey, allLines);
  // The minute plan lives on the primary placement, within its own day: an
  // extra segment or a bar spilling over midnight can only be read here.
  const readOnly = !canEdit || item.spansOutside || item.placementKey !== 'primary';

  const dx = useSharedValue(0);
  const dLeft = useSharedValue(0);
  const dRight = useSharedValue(0);
  const lifted = useSharedValue(0);

  const snapMin = (m: number) => Math.round(m / snap) * snap;

  const commit = (mode: 'move' | 'l' | 'r', px: number) => {
    const d = px / PX_PER_MIN;
    let ns = item.start;
    let ne = item.end;
    if (mode === 'move') {
      ns = snapMin(item.start + d);
      ne = ns + (item.end - item.start);
      if (ns < 0) {
        ne -= ns;
        ns = 0;
      }
      if (ne > 1440) {
        ns -= ne - 1440;
        ne = 1440;
      }
    } else if (mode === 'l') {
      ns = Math.max(0, Math.min(ne - snap, snapMin(item.start + d)));
    } else {
      ne = Math.min(1440, Math.max(ns + snap, snapMin(item.end + d)));
    }

    dx.value = 0;
    dLeft.value = 0;
    dRight.value = 0;
    lifted.value = withTiming(0);
    if (ns === item.start && ne === item.end) return;
    onHourlyChange(wo, ns, ne);
  };

  const reset = () => {
    dx.value = withSpring(0);
    lifted.value = withTiming(0);
  };

  const longPress = Gesture.LongPress()
    .minDuration(220)
    .enabled(!readOnly)
    .onStart(() => {
      lifted.value = withSpring(1);
    });

  const pan = Gesture.Pan()
    .enabled(!readOnly)
    .activeOffsetX([-6, 6])
    .onUpdate((e) => {
      if (lifted.value === 0) return;
      dx.value = e.translationX;
    })
    .onEnd((e) => {
      if (lifted.value === 0) {
        runOnJS(reset)();
        return;
      }
      runOnJS(commit)('move', e.translationX);
    });

  const tap = Gesture.Tap()
    .maxDuration(200)
    .onEnd(() => {
      runOnJS(onSelect)(wo, item.placementKey);
    });

  const edge = (mode: 'l' | 'r') =>
    Gesture.Pan()
      .enabled(!readOnly)
      .activeOffsetX([-6, 6])
      .onUpdate((e) => {
        if (mode === 'l') dLeft.value = e.translationX;
        else dRight.value = e.translationX;
      })
      .onEnd((e) => {
        runOnJS(commit)(mode, e.translationX);
      });

  const composed = Gesture.Race(tap, Gesture.Simultaneous(longPress, pan));

  const animStyle = useAnimatedStyle(() => ({
    transform: [{ translateX: dx.value + dLeft.value }, { translateY: -lifted.value * 2 }],
    zIndex: lifted.value > 0 ? 30 : 2,
    elevation: lifted.value * 4,
  }));
  const sizeStyle = useAnimatedStyle(() => ({
    width: Math.max(12, (item.end - item.start) * PX_PER_MIN + dRight.value - dLeft.value),
  }));

  const durH = Math.round((item.end - item.start) / 6) / 10;

  return (
    <GestureDetector gesture={composed}>
      <Animated.View
        style={[
          styles.bar,
          {
            left: item.start * PX_PER_MIN,
            top: item.lane * (HLANE + HGAP) + 8,
            height: HLANE,
            backgroundColor: status.bg,
            borderColor: item.conflict ? colors.blocked : item.placeholder ? colors.accent : colors.line2,
            borderWidth: item.conflict ? 1.5 : 1,
            borderStyle: item.placeholder ? 'dashed' : 'solid',
          },
          sizeStyle,
          animStyle,
        ]}>
        <View style={styles.barRow}>
          <Mono size={9.5} weight="700" color={colors.ink} numberOfLines={1}>
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
        <Mono size={8.5} color={colors.muted} numberOfLines={1}>
          {fmtMin(item.start)}–{fmtMin(item.end)} · {durH}h
        </Mono>

        {item.conflict ? (
          <View style={[styles.overlapBadge, { backgroundColor: colors.blocked }]}>
            <Mono size={7} weight="700" color="#fff" upper letterSpacing={0.4}>
              overlap
            </Mono>
          </View>
        ) : null}

        {readOnly ? (
          <Mono size={8} color={colors.faint} style={styles.readOnlyMark}>
            ⤢
          </Mono>
        ) : (
          <>
            <GestureDetector gesture={edge('l')}>
              <View style={[styles.handle, styles.handleL]} />
            </GestureDetector>
            <GestureDetector gesture={edge('r')}>
              <View style={[styles.handle, styles.handleR]}>
                <View style={[styles.handleBar, { backgroundColor: status.fg }]} />
              </View>
            </GestureDetector>
          </>
        )}
      </Animated.View>
    </GestureDetector>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1 },
  snapRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingBottom: 8 },
  snapGroup: {
    flexDirection: 'row',
    gap: 2,
    padding: 3,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  snapBtn: { paddingHorizontal: 9, paddingVertical: 4, borderRadius: radius.sm - 2 },
  snapOn: { backgroundColor: colors.ink },
  spacer: { flex: 1 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  overlapSwatch: { width: 8, height: 8, borderRadius: 2, borderWidth: 1.5, borderColor: colors.blocked },
  card: {
    flex: 1,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.card,
    overflow: 'hidden',
  },
  row: { flexDirection: 'row' },
  lblHead: {
    height: 34,
    justifyContent: 'center',
    paddingHorizontal: 12,
    borderRightWidth: 1,
    borderBottomWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.panel,
  },
  lblCell: {
    paddingHorizontal: 12,
    justifyContent: 'center',
    gap: 2,
    borderRightWidth: 1,
    borderBottomWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.panel,
  },
  lblNameRow: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  dot: { width: 6, height: 6, borderRadius: 3 },
  hourHead: {
    flexDirection: 'row',
    height: 34,
    borderBottomWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.panel,
  },
  hourCell: {
    alignItems: 'center',
    justifyContent: 'center',
    borderRightWidth: 1,
    borderColor: colors.line2,
  },
  track: { borderBottomWidth: 1, borderColor: colors.line2 },
  gridlines: { ...StyleSheet.absoluteFillObject, flexDirection: 'row' },
  gridline: { borderRightWidth: 1, borderColor: colors.line2, opacity: 0.6, height: '100%' },
  nowLine: { position: 'absolute', top: 0, bottom: 0, width: 1.5, backgroundColor: colors.accent, zIndex: 1 },
  nowDot: {
    position: 'absolute',
    top: -1,
    left: -3,
    width: 7,
    height: 7,
    borderRadius: 4,
    backgroundColor: colors.accent,
  },
  bar: {
    position: 'absolute',
    borderRadius: radius.sm,
    paddingHorizontal: 8,
    paddingVertical: 3,
    overflow: 'hidden',
    justifyContent: 'center',
  },
  barRow: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  chip: { borderWidth: 1, borderRadius: 3, paddingHorizontal: 3 },
  overlapBadge: { position: 'absolute', top: 3, right: 6, borderRadius: 3, paddingHorizontal: 3 },
  readOnlyMark: { position: 'absolute', bottom: 1, right: 4 },
  handle: { position: 'absolute', top: 0, bottom: 0, width: 18, alignItems: 'center', justifyContent: 'center' },
  handleL: { left: 0 },
  handleR: { right: 0 },
  handleBar: { width: 2, height: '50%', borderRadius: 1, opacity: 0.5 },
  hint: { paddingTop: 8 },
});
