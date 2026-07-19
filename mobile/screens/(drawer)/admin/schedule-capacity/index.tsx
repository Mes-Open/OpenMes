/**
 * Schedule Capacity — available vs planned hours per resource per time bucket,
 * on the line (machine) or crew (labor) axis. Mirrors the web admin capacity
 * grid: axis + granularity toggles, prev/next navigation, and a frozen
 * resource column beside a horizontally-scrollable bucket grid. Read-only on
 * mobile; rescheduling stays on the web planner.
 */
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { Feather } from '@expo/vector-icons';

import { colors, fonts } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCapacityGrid } from '@/hooks/queries/useScheduleCapacity';
import type { CapacityAxis, CapacityCell, CapacityGranularity } from '@/api/scheduleCapacity';

const RESOURCE_W = 150;
const CELL_W = 92;

/** Load → color: green under 70%, amber 70–89%, red 90%+, faint when no capacity. */
function loadColor(pct: number | null): string {
  if (pct == null) return colors.faintest;
  if (pct >= 90) return colors.blocked;
  if (pct >= 70) return colors.downtime;
  return colors.running;
}

export function ScheduleCapacityScreen() {
  const { t } = useTranslation();
  const [axis, setAxis] = useState<CapacityAxis>('line');
  const [granularity, setGranularity] = useState<CapacityGranularity>('week');
  const [anchor, setAnchor] = useState<string | undefined>(undefined);

  const params = useMemo(
    () => ({ axis, granularity, start_date: anchor }),
    [axis, granularity, anchor],
  );
  const q = useCapacityGrid(params);
  const data = q.data;
  const buckets = data?.grid.buckets ?? [];
  const resources = data?.grid.resources ?? [];

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Capacity')}</Text>
        <View style={{ flex: 1 }} />
        <Toggle
          options={[
            { value: 'line', label: t('Line') },
            { value: 'crew', label: t('Crew') },
          ]}
          value={axis}
          onChange={(v) => setAxis(v as CapacityAxis)}
        />
        <Toggle
          options={[
            { value: 'week', label: t('Weekly') },
            { value: 'day', label: t('Daily') },
          ]}
          value={granularity}
          onChange={(v) => setGranularity(v as CapacityGranularity)}
        />
      </View>

      <View style={styles.navRow}>
        <NavBtn icon="chevron-left" onPress={() => setAnchor(data?.nav_prev)} disabled={!data} />
        <Mono size={11} color={colors.muted}>
          {data ? `${data.range_start} → ${data.range_end}` : '—'}
        </Mono>
        <NavBtn icon="chevron-right" onPress={() => setAnchor(data?.nav_next)} disabled={!data} />
        <View style={{ flex: 1 }} />
        {anchor ? (
          <Pressable onPress={() => setAnchor(undefined)} hitSlop={8}>
            <Mono size={10} color={colors.accent}>{t('Today')}</Mono>
          </Pressable>
        ) : null}
      </View>

      {q.isLoading && !q.data ? (
        <LoadingState />
      ) : q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
          <View style={{ flexDirection: 'row' }}>
            {/* Frozen resource column */}
            <View>
              <View style={[styles.resourceCell, styles.headerCell]}>
                <Mono size={9} color={colors.faint} letterSpacing={0.6}>
                  {axis === 'crew' ? t('Crew').toUpperCase() : t('Line').toUpperCase()}
                </Mono>
              </View>
              {resources.map((r) => (
                <View key={r.id} style={styles.resourceCell}>
                  <Text numberOfLines={1} style={styles.resourceName}>{r.name}</Text>
                  {r.code ? <Mono size={9} color={colors.faint}>{r.code}</Mono> : null}
                </View>
              ))}
            </View>

            {/* Scrollable bucket grid */}
            <ScrollView horizontal showsHorizontalScrollIndicator>
              <View>
                <View style={{ flexDirection: 'row' }}>
                  {buckets.map((b) => (
                    <View key={b.key} style={[styles.cell, styles.headerCell]}>
                      <Mono size={9} color={colors.faint} letterSpacing={0.4}>{b.label.toUpperCase()}</Mono>
                    </View>
                  ))}
                </View>
                {resources.map((r) => (
                  <View key={r.id} style={{ flexDirection: 'row' }}>
                    {buckets.map((b) => (
                      <Cell key={b.key} cell={r.cells[b.key]} />
                    ))}
                  </View>
                ))}
              </View>
            </ScrollView>
          </View>

          {resources.length === 0 ? (
            <Text style={styles.empty}>{t('No capacity data.')}</Text>
          ) : null}
        </ScrollView>
      )}
    </View>
  );
}

function Cell({ cell }: { cell: CapacityCell | undefined }) {
  if (!cell) {
    return <View style={styles.cell}><Text style={styles.dash}>—</Text></View>;
  }
  const pct = cell.load_pct;
  const tint = loadColor(pct);
  const fill = Math.min(100, Math.max(0, pct ?? 0));
  return (
    <View style={styles.cell}>
      <Mono size={12} color={tint}>{pct == null ? '—' : `${pct}%`}</Mono>
      <View style={styles.bar}>
        <View style={[styles.barFill, { width: `${fill}%`, backgroundColor: tint }]} />
      </View>
      <Mono size={9} color={colors.muted}>
        {`${round(cell.planned_h)}h / ${round(cell.available_h)}h`}
      </Mono>
      {cell.unestimated_count > 0 ? (
        <Mono size={8} color={colors.accent}>{`+${cell.unestimated_count} est?`}</Mono>
      ) : null}
    </View>
  );
}

function round(n: number): string {
  return (Math.round(n * 10) / 10).toString();
}

function Toggle({
  options,
  value,
  onChange,
}: {
  options: { value: string; label: string }[];
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <View style={styles.toggle}>
      {options.map((o) => {
        const active = o.value === value;
        return (
          <Pressable
            key={o.value}
            onPress={() => onChange(o.value)}
            style={[styles.toggleBtn, active && styles.toggleBtnActive]}>
            <Text style={[styles.toggleText, active && styles.toggleTextActive]}>{o.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

function NavBtn({ icon, onPress, disabled }: { icon: 'chevron-left' | 'chevron-right'; onPress: () => void; disabled?: boolean }) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      hitSlop={8}
      style={({ pressed }) => [styles.navBtn, { opacity: disabled ? 0.4 : pressed ? 0.6 : 1 }]}>
      <Feather name={icon} size={16} color={colors.ink} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 18, paddingTop: 16, paddingBottom: 8 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  navRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingBottom: 10 },
  navBtn: { width: 30, height: 30, borderRadius: 8, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: colors.line },
  toggle: { flexDirection: 'row', borderWidth: 1, borderColor: colors.line, borderRadius: 9, overflow: 'hidden' },
  toggleBtn: { paddingHorizontal: 12, paddingVertical: 6 },
  toggleBtnActive: { backgroundColor: colors.ink },
  toggleText: { fontSize: 12, fontFamily: fonts.sans.native.medium, color: colors.muted },
  toggleTextActive: { color: colors.bg },
  resourceCell: {
    width: RESOURCE_W,
    paddingHorizontal: 12,
    paddingVertical: 10,
    height: 64,
    justifyContent: 'center',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line2,
    borderRightWidth: 1,
    borderRightColor: colors.line,
    backgroundColor: colors.bg,
  },
  resourceName: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  cell: {
    width: CELL_W,
    height: 64,
    paddingHorizontal: 8,
    paddingVertical: 8,
    gap: 3,
    justifyContent: 'center',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line2,
    borderRightWidth: StyleSheet.hairlineWidth,
    borderRightColor: colors.line2,
  },
  headerCell: { height: 34, paddingVertical: 8, justifyContent: 'center', borderBottomWidth: 1, borderBottomColor: colors.line },
  bar: { height: 4, borderRadius: 2, backgroundColor: colors.line2, overflow: 'hidden' },
  barFill: { height: 4, borderRadius: 2 },
  dash: { fontSize: 13, color: colors.faintest },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 18 },
});
