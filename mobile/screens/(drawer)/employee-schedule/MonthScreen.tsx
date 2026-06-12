// Month calendar — each day cell shows a mini 24h tachograph strip + WO count.
// Tapping a cell selects it and shows the day detail in the right panel
// (tablet) or in a bottom sheet (phone).

import { addMonths, format, isSameDay, parseISO, startOfMonth, subMonths } from 'date-fns';
import { useMemo, useState } from 'react';
import {
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  View,
} from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import { MonthGridSkeleton, MonthSkeleton } from '@/components/employee-schedule/MonthSkeleton';
import { PlannerHeader } from '@/components/employee-schedule/PlannerHeader';
import { ActivityTimeline } from '@/components/employee-schedule/ActivityTimeline';
import { iconForActivity } from '@/components/employee-schedule/activityIcons';
import {
  ErrorState,
  LoadingState,
} from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useEmployeeMonthPlan } from '@/hooks/queries/useEmployeeActivities';
import { useWorkers } from '@/hooks/queries/useHr';
import { useEmployeeScheduleUrlState } from '@/hooks/useEmployeeScheduleUrlState';
import { useAuthStore } from '@/stores/authStore';
import {
  formatMinutes,
  toMinutes,
  type DaySegment,
  type MonthPlanDay,
  type TypeMetaMap,
} from '@/api/employeeActivities';

const TOTAL_MIN = 24 * 60;

export function MonthScreen({
  onSelectDay,
}: {
  onSelectDay?: (workerId: number, date: Date) => void;
} = {}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const { useTabletLayout } = useDeviceClass();

  const today = new Date();
  const {
    monthAnchor: anchor,
    setMonthAnchor: setAnchor,
    selectedDate,
    setSelectedDate,
    workerId,
    setWorkerId,
    setView,
    setDate,
  } = useEmployeeScheduleUrlState();

  const workersQ = useWorkers({ per_page: 200 });
  const workers = workersQ.data?.data ?? [];

  const me = useAuthStore((s) => s.user);
  const effectiveWorkerId = useMemo(() => {
    if (workerId != null) return workerId;
    const mine = workers.find((w) => w.email && me?.email && w.email === me.email);
    return mine?.id ?? workers[0]?.id ?? null;
  }, [workerId, workers, me?.email]);

  const monthStr = format(anchor, 'yyyy-MM');
  const q = useEmployeeMonthPlan(effectiveWorkerId ?? undefined, monthStr);

  const selectedDay = useMemo<MonthPlanDay | undefined>(() => {
    if (!q.data) return undefined;
    const key = format(selectedDate, 'yyyy-MM-dd');
    return q.data.days.find((d) => d.date === key);
  }, [q.data, selectedDate]);

  // Group days into weeks (rows of 7).
  const weeks = useMemo(() => {
    const days = q.data?.days ?? [];
    const rows: MonthPlanDay[][] = [];
    for (let i = 0; i < days.length; i += 7) rows.push(days.slice(i, i + 7));
    return rows;
  }, [q.data]);

  const selectedWorker = workers.find((w) => w.id === effectiveWorkerId);

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <PlannerHeader
        eyebrow={selectedWorker ? `${selectedWorker.code} · ${selectedWorker.name}` : t('Month overview')}
        title={format(anchor, 'LLLL yyyy')}
        current="month"
      />

      {q.isLoading && !q.data ? (
        <MonthSkeleton />
      ) : q.error ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : (
        <ScrollView
          contentContainerStyle={styles.content}
          refreshControl={
            <RefreshControl
              refreshing={q.isRefetching}
              onRefresh={() => q.refetch()}
              tintColor={palette.tint}
            />
          }>
          {/* Month nav */}
          <View style={styles.monthNav}>
            <Pressable
              onPress={() => setAnchor(subMonths(anchor, 1))}
              style={[styles.navBtn, { backgroundColor: palette.surface, borderColor: palette.border }]}>
              <FontAwesome name="chevron-left" size={12} color={palette.text} />
            </Pressable>
            <Mono
              size={18}
              weight="700"
              color={palette.text}
              upper
              letterSpacing={-0.2}
              style={{ fontFamily: undefined }}>
              {format(anchor, 'LLLL yyyy')}
            </Mono>
            <Pressable
              onPress={() => setAnchor(addMonths(anchor, 1))}
              style={[styles.navBtn, { backgroundColor: palette.surface, borderColor: palette.border }]}>
              <FontAwesome name="chevron-right" size={12} color={palette.text} />
            </Pressable>
          </View>

          {/* Worker selector (only if multiple) */}
          {workers.length > 1 ? (
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
          style={{ flexGrow: 0, flexShrink: 0 }}
              contentContainerStyle={{ gap: 6, paddingRight: 16 }}>
              {workers.map((w) => {
                const on = w.id === effectiveWorkerId;
                return (
                  <Pressable
                    key={w.id}
                    onPress={() => setWorkerId(w.id)}
                    style={[
                      styles.workerChip,
                      {
                        backgroundColor: on ? BRAND.amber : palette.surface,
                        borderColor: on ? BRAND.amber : palette.border,
                      },
                    ]}>
                    <Mono
                      size={11}
                      weight="700"
                      color={on ? '#1a1208' : palette.text}
                      style={{ fontFamily: undefined }}>
                      {w.name}
                    </Mono>
                  </Pressable>
                );
              })}
            </ScrollView>
          ) : null}

          {/* Weekday header */}
          <View style={styles.weekdays}>
            {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((wd) => (
              <Mono
                key={wd}
                size={10}
                color={palette.textMuted}
                letterSpacing={0.6}
                upper
                style={styles.weekdayCell}>
                {t(wd)}
              </Mono>
            ))}
          </View>

          {/* Calendar grid */}
          {q.data ? (
            <View style={{ gap: 4 }}>
              {weeks.map((week, wi) => (
                <View key={`w${wi}`} style={styles.weekRow}>
                  {week.map((day) => (
                    <DayCell
                      key={day.date}
                      day={day}
                      typeMeta={q.data!.type_meta}
                      isSelected={isSameDay(parseISO(day.date), selectedDate)}
                      onPress={() => setSelectedDate(parseISO(day.date))}
                      palette={palette}
                    />
                  ))}
                </View>
              ))}
            </View>
          ) : null}

          {/* Legend */}
          {q.data ? (
            <View style={styles.legendRow}>
              {(['work','break','rest','maint','meeting','off'] as const).map((k) => {
                const def = q.data!.type_meta[k];
                if (!def) return null;
                return (
                  <View key={k} style={styles.legendItem}>
                    <FontAwesome name={iconForActivity(k)} size={11} color={def.color} />
                    <Mono size={9.5} color={palette.textMuted} letterSpacing={0.4} upper>
                      {t(def.label)}
                    </Mono>
                  </View>
                );
              })}
            </View>
          ) : null}

          {/* Selected-day detail panel */}
          {selectedDay && q.data ? (
            <View
              style={[
                styles.detailCard,
                { backgroundColor: palette.surface, borderColor: palette.border },
              ]}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <View>
                  <Mono size={10.5} weight="700" color={BRAND.amber} letterSpacing={0.7} upper>
                    {t('Selected')} · {format(parseISO(selectedDay.date), 'EEE dd MMM')}
                  </Mono>
                  <Mono
                    size={20}
                    weight="700"
                    color={palette.text}
                    letterSpacing={-0.3}
                    style={{ fontFamily: undefined, marginTop: 4 }}>
                    {formatMinutes(selectedDay.on_duty)} {t('on duty')}
                  </Mono>
                </View>
                <Pressable
                  onPress={() => effectiveWorkerId && onSelectDay?.(effectiveWorkerId, parseISO(selectedDay.date))}
                  style={[styles.openDayBtn, { backgroundColor: BRAND.amber }]}>
                  <Mono size={10.5} weight="700" color="#1a1208" letterSpacing={0.4} upper>
                    {t('Open day plan')}
                  </Mono>
                </Pressable>
              </View>

              <View style={{ marginTop: 12 }}>
                <Mono size={9.5} color={palette.textMuted} letterSpacing={0.5} upper style={{ marginBottom: 8 }}>
                  {t('24h activity')}
                </Mono>
                <ActivityTimeline
                  segments={selectedDay.segments as DaySegment[]}
                  typeMeta={q.data.type_meta}
                  height={42}
                  nowMinutes={
                    selectedDay.is_today
                      ? new Date().getHours() * 60 + new Date().getMinutes()
                      : null
                  }
                />
              </View>

              <View style={styles.detailStats}>
                <Stat label={t('Productive')} value={formatMinutes(selectedDay.productive)} color={palette.text} />
                <Stat label={t('On duty')} value={formatMinutes(selectedDay.on_duty)} color={palette.success} />
              </View>
            </View>
          ) : null}
        </ScrollView>
      )}
    </View>
  );
}

function Stat({ label, value, color }: { label: string; value: string; color: string }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View
      style={{
        flex: 1,
        padding: 10,
        backgroundColor: palette.surfaceAlt,
        borderRadius: 8,
      }}>
      <Mono size={9} color={palette.textMuted} letterSpacing={0.5} upper>
        {label}
      </Mono>
      <Mono size={16} weight="700" color={color} style={{ marginTop: 4 }}>
        {value}
      </Mono>
    </View>
  );
}

function DayCell({
  day,
  typeMeta,
  isSelected,
  onPress,
  palette,
}: {
  day: MonthPlanDay;
  typeMeta: TypeMetaMap;
  isSelected: boolean;
  onPress: () => void;
  palette: typeof Colors.light;
}) {
  const inMonth = day.in_month;
  return (
    <Pressable
      onPress={onPress}
      style={[
        styles.dayCell,
        {
          backgroundColor: day.is_today && !isSelected ? palette.warningSoft : palette.surface,
          borderColor: isSelected ? BRAND.amber : palette.border,
          borderWidth: isSelected ? 1.5 : 1,
          opacity: inMonth ? 1 : 0.4,
        },
      ]}>
      <View style={styles.dayCellHeader}>
        <Mono
          size={11}
          weight="700"
          color={isSelected ? BRAND.amber : palette.text}>
          {format(parseISO(day.date), 'd')}
        </Mono>
        {day.on_duty > 0 ? (
          <View style={[styles.dayDot, { backgroundColor: '#1C9A55' }]} />
        ) : null}
      </View>

      {/* Mini tacho strip */}
      <View style={{ flex: 1 }} />
      {day.segments.length > 0 ? (
        <View style={[styles.miniStrip, { backgroundColor: palette.surfaceAlt }]}>
          {day.segments.map((s, i) => {
            const color = typeMeta[s.type]?.color ?? '#94a3b8';
            const from = toMinutes(s.from);
            const toStr = s.to === '24:00' ? '23:59' : s.to;
            const to = toMinutes(toStr);
            const leftPct = (from / TOTAL_MIN) * 100;
            const widthPct = s.to === '24:00'
              ? 100 - leftPct
              : ((to - from) / TOTAL_MIN) * 100;
            return (
              <View
                key={i}
                style={{
                  position: 'absolute',
                  top: 0,
                  bottom: 0,
                  left: `${leftPct}%`,
                  width: `${widthPct}%`,
                  backgroundColor: color,
                }}
              />
            );
          })}
        </View>
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  content: {
    padding: 16,
    gap: 12,
  },
  monthNav: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  navBtn: {
    width: 36,
    height: 36,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  workerChip: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 100,
    borderWidth: 1,
    userSelect: 'none',
  },
  weekdays: {
    flexDirection: 'row',
    gap: 4,
  },
  weekdayCell: {
    flex: 1,
    textAlign: 'center',
  },
  weekRow: {
    flexDirection: 'row',
    gap: 4,
  },
  dayCell: {
    flex: 1,
    minHeight: 80,
    padding: 4,
    borderRadius: 8,
  },
  dayCellHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  dayDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  miniStrip: {
    position: 'relative',
    height: 8,
    borderRadius: 2,
    overflow: 'hidden',
    marginTop: 4,
  },
  legendRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    marginTop: 4,
  },
  legendItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  detailCard: {
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    gap: 10,
  },
  openDayBtn: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 10,
  },
  detailStats: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 4,
  },
});
