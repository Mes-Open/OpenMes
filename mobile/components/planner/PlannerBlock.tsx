import { useRef } from 'react';
import Animated, {
  runOnJS,
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { Gesture, GestureDetector } from 'react-native-gesture-handler';
import { StyleSheet, View } from 'react-native';

import { colors, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import type { PlannerLine, PlannerOrder } from '@/api/schedule';
import {
  chainChipMeta,
  slotOfCol,
  type PlacementKey,
  type PlannerDay,
  type WeeklyItem,
} from '@/lib/planner/helpers';

import { CELL_W, LANE_H, type DropTarget, type PlannerPreview } from './metrics';
import { statusOf, tierColor } from './plannerTheme';

/** Wide enough to hit with a finger — the web's 6px edge handle is unusable on touch. */
const RESIZE_HANDLE_W = 20;

interface RowMeta {
  id: number;
  height: number;
}

interface Props {
  item: WeeklyItem;
  rowIndex: number;
  rows: RowMeta[];
  days: PlannerDay[];
  shiftsPerDay: number;
  allLines: PlannerLine[];
  selected: boolean;
  canEdit: boolean;
  onMove: (wo: PlannerOrder, placementKey: PlacementKey, target: DropTarget) => void;
  /** Same-line edge stretch — the segment keeps its line, its span changes. */
  onSpanChange: (wo: PlannerOrder, placementKey: PlacementKey, startCol: number, endCol: number) => void;
  /** Edge dragged onto another line — appends a NEW segment there. */
  onDiagonalExtend: (wo: PlannerOrder, lineId: number, startCol: number, endCol: number) => void;
  onPreview: (p: PlannerPreview | null) => void;
  onSelect: (wo: PlannerOrder, placementKey: PlacementKey) => void;
}

/**
 * One work-order segment on the weekly grid.
 *
 *   • Long-press → lift, then pan in both axes to move across shifts, days and
 *     lines. Release snaps to the nearest cell.
 *   • Edge handles → stretch the span. Drag an edge onto ANOTHER line and the
 *     order continues there: released, it appends a new segment (the staircase).
 *   • Tap → the order's action sheet (the web reveals a ✕ on hover, which
 *     doesn't exist on touch).
 */
export function PlannerBlock({
  item,
  rowIndex,
  rows,
  days,
  shiftsPerDay,
  allLines,
  selected,
  canEdit,
  onMove,
  onSpanChange,
  onDiagonalExtend,
  onPreview,
  onSelect,
}: Props) {
  const { wo, placementKey, startCol, endCol, lane } = item;

  const dragX = useSharedValue(0);
  const dragY = useSharedValue(0);
  const stretchL = useSharedValue(0);
  const stretchR = useSharedValue(0);
  const lifted = useSharedValue(0);

  // Last previewed target, so the board only re-renders when it actually
  // changes rather than on every pointer frame.
  const previewRef = useRef<string>('');
  // What the in-flight edge drag resolved to, read on release.
  const edgeRef = useRef<
    { kind: 'span'; startCol: number; endCol: number } | { kind: 'diagonal'; lineId: number; startCol: number; endCol: number } | null
  >(null);

  const status = statusOf(wo.status);
  const chip = chainChipMeta(wo, placementKey, allLines);
  const tier = tierColor(wo.customer_tier);
  const totalCols = days.length * shiftsPerDay;
  const spanCols = endCol - startCol + 1;

  const rowTopOf = (index: number) => {
    let top = 0;
    for (let i = 0; i < index; i++) top += rows[i].height;
    return top;
  };

  /** Which row a vertical delta lands on. Rows vary in height, so walk them. */
  const rowAt = (absY: number) => {
    if (absY < 0) return 0;
    let cursor = 0;
    for (let i = 0; i < rows.length; i++) {
      if (absY < cursor + rows[i].height) return i;
      cursor += rows[i].height;
    }
    return rows.length - 1;
  };

  const reset = () => {
    dragX.value = withSpring(0);
    dragY.value = withSpring(0);
    lifted.value = withTiming(0);
  };

  const pushPreview = (p: PlannerPreview | null) => {
    const key = p ? `${p.lineId}:${p.startCol}:${p.endCol}:${p.kind}` : '';
    if (key === previewRef.current) return;
    previewRef.current = key;
    onPreview(p);
  };

  const finishMove = (dx: number, dy: number) => {
    const deltaCols = Math.round(dx / CELL_W);
    const targetCol = Math.max(0, Math.min(totalCols - 1, startCol + deltaCols));
    const centreY = rowTopOf(rowIndex) + lane * LANE_H + LANE_H / 2 + dy;
    const targetRow = rowAt(centreY);

    const slot = slotOfCol(targetCol, days, shiftsPerDay);
    const targetLineId = rows[targetRow]?.id;
    const unchanged = targetCol === startCol && targetLineId === item.lineId;

    pushPreview(null);
    if (!slot || targetLineId == null || unchanged) {
      reset();
      return;
    }

    // Snap visuals home immediately — the refetch redraws at the real position,
    // and springing back while the request flies reads as lag.
    dragX.value = 0;
    dragY.value = 0;
    lifted.value = withTiming(0);
    onMove(wo, placementKey, { lineId: targetLineId, date: slot.date, shift: slot.shift });
  };

  /**
   * Resolve an edge drag. Mirrors the web's `begin('l'|'r')`: over another row
   * the segment keeps its own span and the drag previews a continuation there;
   * over its own row it's an ordinary stretch.
   */
  const updateEdge = (edge: 'l' | 'r', dx: number, dy: number) => {
    const dCol = Math.round(dx / CELL_W);
    const centreY = rowTopOf(rowIndex) + lane * LANE_H + LANE_H / 2 + dy;
    const targetLineId = rows[rowAt(centreY)]?.id;

    const canExtend = edge === 'r' ? endCol < totalCols - 1 : startCol > 0;
    if (targetLineId != null && targetLineId !== item.lineId && canExtend) {
      const ext =
        edge === 'r'
          ? { startCol: endCol + 1, endCol: Math.min(totalCols - 1, Math.max(endCol + 1, endCol + dCol)) }
          : { startCol: Math.max(0, Math.min(startCol - 1, startCol + dCol)), endCol: startCol - 1 };
      edgeRef.current = { kind: 'diagonal', lineId: targetLineId, ...ext };
      pushPreview({ kind: 'extend', lineId: targetLineId, ...ext });
      return;
    }

    const ns = edge === 'l' ? Math.max(0, Math.min(endCol, startCol + dCol)) : startCol;
    const ne = edge === 'r' ? Math.min(totalCols - 1, Math.max(startCol, endCol + dCol)) : endCol;
    edgeRef.current = { kind: 'span', startCol: ns, endCol: ne };
    pushPreview(
      ns === startCol && ne === endCol
        ? null
        : { kind: 'span', lineId: item.lineId ?? 0, startCol: ns, endCol: ne },
    );
  };

  const finishEdge = () => {
    const r = edgeRef.current;
    edgeRef.current = null;
    previewRef.current = '';
    stretchL.value = 0;
    stretchR.value = 0;
    onPreview(null);
    if (!r) return;
    if (r.kind === 'diagonal') {
      onDiagonalExtend(wo, r.lineId, r.startCol, r.endCol);
      return;
    }
    if (r.startCol === startCol && r.endCol === endCol) return;
    onSpanChange(wo, placementKey, r.startCol, r.endCol);
  };

  const longPress = Gesture.LongPress()
    .minDuration(220)
    .enabled(canEdit)
    .onStart(() => {
      lifted.value = withSpring(1);
    });

  // activeOffset keeps small finger jitter from stealing the parent
  // ScrollView's horizontal pan before the long-press has primed the drag.
  const pan = Gesture.Pan()
    .enabled(canEdit)
    .activeOffsetX([-6, 6])
    .activeOffsetY([-6, 6])
    .onUpdate((e) => {
      if (lifted.value === 0) return;
      dragX.value = e.translationX;
      dragY.value = e.translationY;
    })
    .onEnd((e) => {
      if (lifted.value === 0) {
        runOnJS(reset)();
        return;
      }
      runOnJS(finishMove)(e.translationX, e.translationY);
    });

  const tap = Gesture.Tap()
    .maxDuration(200)
    .onEnd(() => {
      runOnJS(onSelect)(wo, placementKey);
    });

  const edgeGesture = (edge: 'l' | 'r') =>
    Gesture.Pan()
      .enabled(canEdit)
      .activeOffsetX([-6, 6])
      .activeOffsetY([-6, 6])
      .onUpdate((e) => {
        if (edge === 'l') stretchL.value = e.translationX;
        else stretchR.value = e.translationX;
        runOnJS(updateEdge)(edge, e.translationX, e.translationY);
      })
      .onEnd(() => {
        runOnJS(finishEdge)();
      });

  const composed = Gesture.Race(tap, Gesture.Simultaneous(longPress, pan));

  const animStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: dragX.value + stretchL.value },
      { translateY: dragY.value - lifted.value * 2 },
      { scale: 1 + lifted.value * 0.03 },
    ],
    opacity: lifted.value > 0 ? 0.92 : 1,
    zIndex: lifted.value > 0 ? 50 : 5,
    elevation: lifted.value * 5,
  }));

  const widthStyle = useAnimatedStyle(() => ({
    width: Math.max(CELL_W * 0.5, spanCols * CELL_W - 4 + stretchR.value - stretchL.value),
  }));

  return (
    <GestureDetector gesture={composed}>
      <Animated.View
        style={[
          styles.block,
          {
            left: startCol * CELL_W + 2,
            top: lane * LANE_H + 4,
            height: LANE_H - 6,
            backgroundColor: status.bg,
            // Overdue rings the block in red, exactly like the web's inset ring.
            borderColor: wo.is_overdue ? colors.blocked : selected ? colors.accent : colors.line2,
            borderWidth: wo.is_overdue || selected ? 1.5 : 1,
          },
          widthStyle,
          animStyle,
        ]}>
        <View style={styles.blockRow}>
          {tier ? <View style={[styles.tierDot, { backgroundColor: tier }]} /> : null}
          <Mono size={9.5} weight="700" color={colors.ink} numberOfLines={1} style={styles.orderNo}>
            {wo.order_no}
          </Mono>
          {chip ? (
            <View style={[styles.chip, { borderColor: status.fg }]}>
              <Mono size={7.5} color={status.fg} numberOfLines={1}>
                {chip.dir === 'to' ? `→ ${chip.code}` : chip.dir === 'from' ? `${chip.code} →` : `⇄ ${chip.code}`}
              </Mono>
            </View>
          ) : null}
          {wo.is_overdue ? (
            <View style={[styles.bang, { backgroundColor: colors.blocked }]}>
              <Mono size={7.5} weight="700" color="#fff">
                !
              </Mono>
            </View>
          ) : null}
        </View>
        <Mono size={8} color={colors.muted} numberOfLines={1}>
          {[wo.product_name || '—', wo.planned_qty].filter(Boolean).join(' · ')}
        </Mono>

        {canEdit ? (
          <>
            <GestureDetector gesture={edgeGesture('l')}>
              <View style={[styles.handle, styles.handleL, { width: RESIZE_HANDLE_W }]}>
                <View style={[styles.handleBar, { backgroundColor: status.fg }]} />
              </View>
            </GestureDetector>
            <GestureDetector gesture={edgeGesture('r')}>
              <View style={[styles.handle, styles.handleR, { width: RESIZE_HANDLE_W }]}>
                <View style={[styles.handleBar, { backgroundColor: status.fg }]} />
              </View>
            </GestureDetector>
          </>
        ) : null}
      </Animated.View>
    </GestureDetector>
  );
}

const styles = StyleSheet.create({
  block: {
    position: 'absolute',
    borderRadius: radius.sm,
    paddingHorizontal: 6,
    paddingVertical: 2,
    overflow: 'hidden',
    justifyContent: 'center',
  },
  blockRow: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  orderNo: { flexShrink: 1 },
  tierDot: { width: 5, height: 5, borderRadius: 3 },
  chip: {
    borderWidth: 1,
    borderRadius: 3,
    paddingHorizontal: 3,
    paddingVertical: 0.5,
  },
  bang: {
    marginLeft: 'auto',
    borderRadius: 3,
    paddingHorizontal: 3,
  },
  handle: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    alignItems: 'center',
    justifyContent: 'center',
  },
  handleL: { left: 0 },
  handleR: { right: 0 },
  handleBar: { width: 2, height: '55%', borderRadius: 1, opacity: 0.5 },
});
