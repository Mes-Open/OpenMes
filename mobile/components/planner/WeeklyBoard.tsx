import { useMemo, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import Svg, { Circle, Polyline } from 'react-native-svg';

import { colors } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import type { PlannerBoard as Board, PlannerOrder } from '@/api/schedule';
import {
  lineLoad,
  onLine,
  parseDate,
  todayKey,
  weeklyConnectors,
  weeklyPlacements,
  type PlacementKey,
  type PlannerDay,
} from '@/lib/planner/helpers';

import { PlannerBlock } from './PlannerBlock';
import {
  CELL_W,
  HEADER_H,
  LANE_H,
  LINE_COL_W,
  rowHeight,
  type DropTarget,
  type PlannerPreview,
} from './metrics';
import { loadColor, maintColors, shiftColor } from './plannerTheme';

export { CELL_W, HEADER_H, LANE_H, LINE_COL_W };
export type { DropTarget };

/**
 * The weekly day×shift Gantt — a React Native port of the web planner's
 * WeeklyView.
 *
 * The web hit-tests drags and measures its connectors against the DOM
 * (getBoundingClientRect over `[data-weekrow]` nodes). Here the grid geometry is
 * ours and deterministic, so both fall out of arithmetic: a drop target is
 * round(dx / CELL_W) plus a row walk, and connector geometry comes straight from
 * the placement columns. No measurement, no resize listener, no layout registry.
 */

interface Props {
  board: Board;
  days: PlannerDay[];
  selectedId: number | null;
  canEdit: boolean;
  onMove: (wo: PlannerOrder, placementKey: PlacementKey, target: DropTarget) => void;
  onSpanChange: (wo: PlannerOrder, placementKey: PlacementKey, startCol: number, endCol: number) => void;
  onDiagonalExtend: (wo: PlannerOrder, lineId: number, startCol: number, endCol: number) => void;
  onSelect: (wo: PlannerOrder, placementKey: PlacementKey) => void;
}

export function WeeklyBoard({
  board,
  days,
  selectedId,
  canEdit,
  onMove,
  onSpanChange,
  onDiagonalExtend,
  onSelect,
}: Props) {
  const { t } = useTranslation();
  const { shiftsPerDay, lines, workOrders, shifts, maintenanceEvents } = board;
  const today = todayKey();
  const [preview, setPreview] = useState<PlannerPreview | null>(null);

  const dayIndex = useMemo(() => {
    const m: Record<string, number> = {};
    days.forEach((d, i) => {
      m[d.date] = i;
    });
    return m;
  }, [days]);

  // One layout pass per line — memoised on the inputs the math actually reads.
  const rows = useMemo(
    () =>
      lines.map((line) => {
        const orders = workOrders.filter((wo) => onLine(wo, line.id));
        const placed = weeklyPlacements(orders, days, shiftsPerDay, line.id);
        const maint = maintenanceEvents.filter(
          (m) => m.line_id === line.id && m.scheduled_at_date != null && m.scheduled_at_date in dayIndex,
        );
        return {
          line,
          ...placed,
          maint,
          load: lineLoad(workOrders, line.id, days, shiftsPerDay),
          // Maintenance pills sit under the blocks, so they need a lane of their own.
          height: rowHeight(placed.lanes + (maint.length ? 1 : 0)),
        };
      }),
    [lines, workOrders, days, shiftsPerDay, maintenanceEvents, dayIndex],
  );

  const headerOffset = HEADER_H * 2;
  const gridW = days.length * shiftsPerDay * CELL_W;
  const gridH = headerOffset + rows.reduce((sum, r) => sum + r.height, 0);
  const shiftName = (n: number) => shifts[n - 1]?.name ?? `${t('Shift')} ${n}`;

  // Accent links between the segments of every multi-line order.
  const connectors = useMemo(
    () =>
      weeklyConnectors(workOrders, rows, {
        cellW: CELL_W,
        laneH: LANE_H,
        topOffset: headerOffset,
        blockInset: 2,
      }),
    [workOrders, rows, headerOffset],
  );

  const maint = maintColors();

  return (
    <ScrollView style={styles.vScroll} contentContainerStyle={styles.vContent}>
      <View style={styles.row}>
        {/* Frozen line column — scrolls vertically with the rows, never horizontally. */}
        <View style={{ width: LINE_COL_W }}>
          <View style={[styles.lineHeadCell, { height: headerOffset }]}>
            <Mono size={9} color={colors.faint} upper letterSpacing={0.8}>
              {t('Line · Load')}
            </Mono>
          </View>
          {rows.map((r) => (
            <View key={r.line.id} style={[styles.lineCell, { height: r.height }]}>
              <View style={styles.lineNameRow}>
                <View style={[styles.dot, { backgroundColor: colors.running }]} />
                <Mono size={10.5} weight="700" color={colors.ink} numberOfLines={1}>
                  {r.line.code ?? r.line.name}
                </Mono>
                <Mono size={8.5} color={colors.faint} numberOfLines={1} style={styles.lineName}>
                  {r.line.name}
                </Mono>
              </View>
              <View style={styles.loadRow}>
                <View style={styles.loadTrack}>
                  <View
                    style={{
                      width: `${Math.min(100, r.load)}%`,
                      height: 3,
                      borderRadius: 2,
                      backgroundColor: loadColor(r.load),
                    }}
                  />
                </View>
                <Mono size={9} color={colors.faint}>
                  {r.load}%
                </Mono>
              </View>
            </View>
          ))}
        </View>

        {/* Day/shift grid. Header and rows share one horizontal ScrollView, so
            they stay aligned without any scroll-sync bookkeeping. */}
        <ScrollView horizontal showsHorizontalScrollIndicator contentContainerStyle={{ width: gridW }}>
          <View style={{ width: gridW }}>
            {/* Day header */}
            <View style={styles.headRow}>
              {days.map((d) => {
                const date = parseDate(d.date);
                const isToday = d.date === today;
                return (
                  <View
                    key={d.date}
                    style={[
                      styles.dayHead,
                      { width: CELL_W * shiftsPerDay },
                      d.isWeekend && { backgroundColor: colors.chip },
                      isToday && styles.todayCol,
                    ]}>
                    <Mono size={9} color={isToday ? colors.accent : colors.faint} upper letterSpacing={0.8}>
                      {date ? date.toLocaleDateString(undefined, { weekday: 'short' }).toUpperCase() : ''}
                    </Mono>
                    <View style={styles.dayHeadLine}>
                      <Mono size={11} weight="700" color={isToday ? colors.accent : colors.ink}>
                        {date
                          ? `${date.getDate()} ${date.toLocaleDateString(undefined, { month: 'short' })}`
                          : d.date}
                      </Mono>
                      {isToday ? <View style={styles.todayRule} /> : null}
                    </View>
                  </View>
                );
              })}
            </View>

            {/* Shift sub-header */}
            <View style={styles.headRow}>
              {days.map((d) =>
                Array.from({ length: shiftsPerDay }, (_, i) => (
                  <View
                    key={`${d.date}-${i}`}
                    style={[styles.shiftHead, { width: CELL_W }, d.date === today && styles.todayCol]}>
                    <View style={[styles.dot, { backgroundColor: shiftColor(i + 1) }]} />
                    <Mono size={8.5} color={colors.muted} numberOfLines={1}>
                      {shiftName(i + 1)}
                    </Mono>
                  </View>
                )),
              )}
            </View>

            {/* One row per line */}
            {rows.map((r, rowIndex) => (
              <View key={r.line.id} style={[styles.gridRow, { height: r.height }]}>
                {/* Cell backgrounds */}
                <View style={StyleSheet.absoluteFill}>
                  <View style={styles.cellStrip}>
                    {days.map((d) =>
                      Array.from({ length: shiftsPerDay }, (_, i) => (
                        <View
                          key={`${d.date}-${i}`}
                          style={[
                            styles.cell,
                            { width: CELL_W },
                            d.isWeekend && { backgroundColor: colors.chip },
                            d.date === today && styles.todayCol,
                          ]}
                        />
                      )),
                    )}
                  </View>
                </View>

                {/* Maintenance windows — read-only pills under the blocks. */}
                {r.maint.map((m, i) => (
                  <View
                    key={`m${m.id ?? 'v'}-${m.scheduled_at_date}-${i}`}
                    style={[
                      styles.maintPill,
                      {
                        left: dayIndex[m.scheduled_at_date!] * shiftsPerDay * CELL_W + 3,
                        width: CELL_W - 6,
                        top: r.lanes * LANE_H + 5,
                        borderColor: maint.fg,
                        backgroundColor: maint.bg,
                      },
                    ]}>
                    <View style={[styles.dot, { backgroundColor: maint.fg }]} />
                    <Mono size={7.5} color={maint.fg} numberOfLines={1} style={styles.maintText}>
                      {m.title}
                    </Mono>
                  </View>
                ))}

                {/* The dashed ghost for the in-flight drag. */}
                {preview && preview.lineId === r.line.id ? (
                  <View
                    style={[
                      styles.ghost,
                      {
                        left: preview.startCol * CELL_W + 2,
                        width: (preview.endCol - preview.startCol + 1) * CELL_W - 4,
                        top: 4,
                        height: LANE_H - 6,
                        borderColor: colors.accent,
                      },
                    ]}
                  />
                ) : null}

                {/* Blocks */}
                {r.items.map((it) => (
                  <PlannerBlock
                    key={`${it.wo.id}-${String(it.placementKey)}`}
                    item={it}
                    rowIndex={rowIndex}
                    rows={rows.map((x) => ({ id: x.line.id, height: x.height }))}
                    days={days}
                    shiftsPerDay={shiftsPerDay}
                    allLines={board.allLines}
                    selected={selectedId === it.wo.id}
                    canEdit={canEdit}
                    onMove={onMove}
                    onSpanChange={onSpanChange}
                    onDiagonalExtend={onDiagonalExtend}
                    onPreview={setPreview}
                    onSelect={onSelect}
                  />
                ))}
              </View>
            ))}

            {/* Chain connectors, drawn over the rows. Non-interactive so drags
                on the blocks underneath still register. */}
            {connectors.length ? (
              <Svg pointerEvents="none" style={[StyleSheet.absoluteFill, styles.connectors]} width={gridW} height={gridH}>
                {connectors.map((c) => (
                  <Polyline
                    key={c.id}
                    points={c.points.map((p) => `${p.x},${p.y}`).join(' ')}
                    fill="none"
                    stroke={colors.accent}
                    strokeOpacity={0.55}
                    strokeWidth={2}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                ))}
                {connectors.flatMap((c) =>
                  c.dots.map((d, i) => (
                    <Circle key={`${c.id}-d${i}`} cx={d.x} cy={d.y} r={2.5} fill={colors.accent} />
                  )),
                )}
              </Svg>
            ) : null}
          </View>
        </ScrollView>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  vScroll: { flex: 1 },
  vContent: { paddingBottom: 8 },
  row: { flexDirection: 'row' },
  headRow: { flexDirection: 'row' },
  lineHeadCell: {
    justifyContent: 'flex-end',
    paddingBottom: 6,
    paddingHorizontal: 8,
    borderBottomWidth: 1,
    borderColor: colors.line,
  },
  lineCell: {
    paddingHorizontal: 8,
    justifyContent: 'center',
    gap: 1,
    borderBottomWidth: 1,
    borderRightWidth: 1,
    borderColor: colors.line,
  },
  lineNameRow: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  lineName: { flexShrink: 1 },
  dot: { width: 5, height: 5, borderRadius: 3 },
  loadRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 3 },
  loadTrack: {
    flex: 1,
    height: 3,
    borderRadius: 2,
    backgroundColor: colors.line2,
    overflow: 'hidden',
  },
  dayHead: {
    height: HEADER_H,
    alignItems: 'center',
    justifyContent: 'center',
    borderRightWidth: 1,
    borderBottomWidth: 1,
    borderColor: colors.line,
    gap: 1,
  },
  dayHeadLine: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  todayRule: { width: 14, height: 1.5, borderRadius: 1, backgroundColor: colors.accent },
  todayCol: { backgroundColor: 'rgba(234, 90, 43, 0.05)' },
  shiftHead: {
    height: HEADER_H,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    borderRightWidth: 1,
    borderBottomWidth: 1,
    borderColor: colors.line,
  },
  gridRow: { borderBottomWidth: 1, borderColor: colors.line },
  cellStrip: { flexDirection: 'row', flex: 1 },
  cell: { borderRightWidth: 1, borderColor: colors.line2, height: '100%' },
  maintPill: {
    position: 'absolute',
    height: LANE_H - 12,
    borderRadius: 4,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 4,
  },
  maintText: { flexShrink: 1 },
  connectors: { zIndex: 4 },
  ghost: {
    position: 'absolute',
    borderRadius: 6,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    zIndex: 40,
  },
});
