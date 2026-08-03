import { useMemo } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import type { PlannerBoard as Board } from '@/api/schedule';
import { onMonthlyDay, parseDate, todayKey } from '@/lib/planner/helpers';

import { statusOf } from './plannerTheme';

/**
 * Month calendar for the month containing the range start — a port of the web
 * planner's MonthlyView. Read-only on both surfaces: a month cell is too coarse
 * to place an order into, so scheduling stays in the weekly/hourly views.
 */

const DOW_KEYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

interface Props {
  board: Board;
}

interface Cell {
  d: number;
  iso: string;
  orders: Board['workOrders'];
}

export function MonthlyBoard({ board }: Props) {
  const { t } = useTranslation();
  const today = todayKey();

  const { cells, monthLabel } = useMemo(() => {
    const anchor = parseDate(board.startDate) ?? new Date();
    const y = anchor.getFullYear();
    const mo = anchor.getMonth();
    const firstDow = (new Date(y, mo, 1).getDay() + 6) % 7; // Monday-first
    const dim = new Date(y, mo + 1, 0).getDate();

    const out: (Cell | null)[] = [];
    for (let i = 0; i < firstDow; i++) out.push(null);
    for (let d = 1; d <= dim; d++) {
      const iso = `${y}-${String(mo + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      out.push({ d, iso, orders: board.workOrders.filter((o) => onMonthlyDay(o, iso, d, mo + 1)) });
    }
    return {
      cells: out,
      monthLabel: new Date(y, mo, 1, 12).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }),
    };
  }, [board.startDate, board.workOrders]);

  return (
    <View style={styles.card}>
      <View style={styles.head}>
        <Mono size={13} weight="700" color={colors.ink}>
          {monthLabel}
        </Mono>
        <Mono size={9} color={colors.faint}>
          {t('read-only')}
        </Mono>
      </View>

      <View style={styles.dowRow}>
        {DOW_KEYS.map((d) => (
          <View key={d} style={styles.dowCell}>
            <Mono size={8.5} color={colors.faint} upper letterSpacing={0.8}>
              {t(d)}
            </Mono>
          </View>
        ))}
      </View>

      <ScrollView contentContainerStyle={styles.grid}>
        {cells.map((c, i) =>
          !c ? (
            <View key={`e${i}`} style={[styles.cell, styles.cellEmpty]} />
          ) : (
            <View key={c.iso} style={[styles.cell, c.iso === today && styles.cellToday]}>
              <View style={styles.cellHead}>
                <Mono
                  size={11}
                  weight={c.iso === today ? '700' : '500'}
                  color={c.iso === today ? colors.accent : colors.ink}>
                  {c.d}
                </Mono>
                {c.orders.length > 0 ? (
                  <View style={styles.count}>
                    <Mono size={8} color={colors.muted}>
                      {c.orders.length}
                    </Mono>
                  </View>
                ) : null}
              </View>

              {/* One bar per order, width hinting at planned quantity. */}
              <View style={styles.bars}>
                {c.orders.slice(0, 4).map((o) => (
                  <View
                    key={o.id}
                    style={{
                      height: 4,
                      borderRadius: 3,
                      backgroundColor: statusOf(o.status).fg,
                      width: `${Math.min(100, 40 + Number(o.planned_qty ?? 0) / 6)}%`,
                    }}
                  />
                ))}
              </View>
            </View>
          ),
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    flex: 1,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.card,
    overflow: 'hidden',
  },
  head: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderColor: colors.line2,
  },
  dowRow: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.panel,
  },
  dowCell: { width: `${100 / 7}%`, alignItems: 'center', paddingVertical: 8 },
  grid: { flexDirection: 'row', flexWrap: 'wrap' },
  cell: {
    width: `${100 / 7}%`,
    minHeight: 84,
    padding: 8,
    borderRightWidth: 1,
    borderBottomWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.card,
    gap: 6,
  },
  cellEmpty: { backgroundColor: colors.panel },
  cellToday: { backgroundColor: 'rgba(234, 90, 43, 0.07)' },
  cellHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  count: { backgroundColor: colors.chip, borderRadius: 20, paddingHorizontal: 5, paddingVertical: 1 },
  bars: { gap: 3 },
});
