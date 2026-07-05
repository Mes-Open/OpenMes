// Week view — single worker's 7-day plan as stacked tachograph rows
// (matches the design's vocabulary: Day-by-day, color-banded, NOW pin
// on today's row).
//
// Each day reuses the day-plan endpoint via parallel queries so the
// segments are already gap-filled by the backend.

import FontAwesome from '@expo/vector-icons/FontAwesome';
import { addDays, format, isSameDay, startOfWeek } from 'date-fns';
import { useMemo } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { keepPreviousData, useQueries } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { colors } from '@openmes/ui';

import { ActivityTimeline } from '@/components/employee-schedule/ActivityTimeline';
import { iconForActivity } from '@/components/employee-schedule/activityIcons';
import type { ActivityType } from '@/api/employeeActivities';
import { PlannerHeader } from '@/components/employee-schedule/PlannerHeader';
import { WeekRowsSkeleton, WeekSkeleton } from '@/components/employee-schedule/WeekSkeleton';
import { Mono } from '@/components/ui/Mono';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useEmployeeScheduleUrlState } from '@/hooks/useEmployeeScheduleUrlState';
import { useWorkers } from '@/hooks/queries/useHr';
import { useActivityTypes } from '@/hooks/queries/useEmployeeActivities';
import { fetchDayPlan, formatMinutes, onDutyMinutes } from '@/api/employeeActivities';
import { useAuthStore } from '@/stores/authStore';

export function WeekScreen() {
  const { t } = useTranslation();
  const { useTabletLayout } = useDeviceClass();

  const today = new Date();
  const { date, setDate, workerId, setWorkerId, setView } = useEmployeeScheduleUrlState();

  // Week starts on Monday — matches the rest of OpenMES (and the design).
  const weekStart = startOfWeek(date, { weekStartsOn: 1 });
  const days = useMemo(
    () => Array.from({ length: 7 }).map((_, i) => addDays(weekStart, i)),
    [weekStart.getTime()],
  );

  const workersQ = useWorkers({ per_page: 200 });
  const workers = workersQ.data?.data ?? [];
  const me = useAuthStore((s) => s.user);
  const effectiveWorkerId = useMemo(() => {
    if (workerId != null) return workerId;
    const mine = workers.find((w) => w.email && me?.email && w.email === me.email);
    return mine?.id ?? workers[0]?.id ?? null;
  }, [workerId, workers, me?.email]);
  const selectedWorker = workers.find((w) => w.id === effectiveWorkerId);

  const typesQ = useActivityTypes();

  // Parallel fetches — one day-plan query per day in the week.
  const dayQueries = useQueries({
    queries: days.map((d) => ({
      queryKey: ['employee-day-plan', effectiveWorkerId, format(d, 'yyyy-MM-dd')],
      queryFn: () =>
        fetchDayPlan(effectiveWorkerId as number, format(d, 'yyyy-MM-dd')),
      enabled: typeof effectiveWorkerId === 'number',
      // Keep previous worker/week data visible while the new query runs —
      // no skeleton flash on chip switches.
      placeholderData: keepPreviousData,
    })),
  });

  const isLoading = workersQ.isLoading || dayQueries.some((q) => q.isLoading);
  const error =
    workersQ.error ?? typesQ.error ?? dayQueries.find((q) => q.error)?.error;

  const typeMeta = typesQ.data
    ? // Map catalog entries → the TypeMetaMap shape ActivityTimeline expects.
      Object.fromEntries(
        typesQ.data.built_in.map((e) => [
          e.key,
          { color: e.color, label: e.label, short: e.short },
        ]),
      )
    : null;

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <PlannerHeader
        eyebrow={t('Employee week plan')}
        title={
          selectedWorker
            ? `${selectedWorker.name} · ${format(weekStart, 'dd MMM')} – ${format(days[6], 'dd MMM')}`
            : t('Week plan')
        }
        current="week"
        showTeamButton
        onTeamDay={() => setView('day')}
      />

      {isLoading && !typeMeta ? (
        <WeekSkeleton />
      ) : error ? (
        <ErrorState error={error} />
      ) : !typeMeta ? (
        <EmptyState title={t('No activity types loaded')} />
      ) : (
        <ScrollView
          contentContainerStyle={styles.content}
          refreshControl={
            <RefreshControl
              refreshing={dayQueries.some((q) => q.isRefetching)}
              onRefresh={() => dayQueries.forEach((q) => q.refetch())}
              tintColor={colors.accent}
            />
          }>
          {/* Hour ruler (tablet only) */}
          {useTabletLayout ? (
            <View
              style={[
                styles.rulerCard,
                { backgroundColor: colors.card, borderColor: colors.line },
              ]}>
              <View style={styles.rulerRow}>
                <Mono
                  size={9.5}
                  color={colors.muted}
                  letterSpacing={0.6}
                  upper
                  style={{ width: 140 }}>
                  {t('Day')}
                </Mono>
                <View style={styles.ruler}>
                  {[0, 3, 6, 9, 12, 15, 18, 21].map((h) => (
                    <Mono
                      key={h}
                      size={9}
                      weight="700"
                      color={colors.muted}
                      letterSpacing={0.3}
                      style={{ position: 'absolute', left: `${(h / 24) * 100}%` }}>
                      {String(h).padStart(2, '0')}:00
                    </Mono>
                  ))}
                </View>
                <Mono
                  size={9.5}
                  color={colors.muted}
                  letterSpacing={0.6}
                  upper
                  style={{ width: 80, textAlign: 'right' }}>
                  {t('On duty')}
                </Mono>
              </View>
            </View>
          ) : null}

          {/* One row per day — show row skeletons while data is loading so
              the hour ruler and legend stay mounted across worker/date
              switches. */}
          {dayQueries.every((q) => !q.data) && dayQueries.some((q) => q.isLoading) ? (
            <WeekRowsSkeleton />
          ) : (
          <View style={{ gap: 8 }}>
            {days.map((d, idx) => {
              const q = dayQueries[idx];
              const plan = q.data;
              const segments = plan?.segments ?? [];
              const summary = plan?.summary ?? {};
              const dayOnDuty = onDutyMinutes(summary);
              const isToday = isSameDay(d, today);
              const nowMin = isToday
                ? new Date().getHours() * 60 + new Date().getMinutes()
                : null;

              return (
                <Pressable
                  key={d.toISOString()}
                  onPress={() => {
                    setDate(d);
                    setView('day');
                  }}
                  style={[
                    styles.dayCard,
                    {
                      backgroundColor: colors.card,
                      borderColor: isToday ? colors.accent : colors.line,
                    },
                  ]}>
                  {useTabletLayout ? (
                    <View style={styles.dayRowTablet}>
                      <View style={{ width: 140 }}>
                        <Mono
                          size={13}
                          weight="700"
                          color={isToday ? colors.accent : colors.ink}
                          letterSpacing={-0.2}
                          style={{ fontFamily: undefined }}>
                          {format(d, 'EEE')}
                        </Mono>
                        <Mono
                          size={10.5}
                          color={colors.muted}
                          letterSpacing={0.3}
                          style={{ marginTop: 2 }}>
                          {format(d, 'dd MMM')}
                        </Mono>
                      </View>
                      <View style={{ flex: 1 }}>
                        <ActivityTimeline
                          segments={segments}
                          typeMeta={typeMeta as any}
                          height={42}
                          showHours={false}
                          nowMinutes={nowMin}
                        />
                      </View>
                      <View style={{ width: 80, alignItems: 'flex-end' }}>
                        <Mono
                          size={16}
                          weight="700"
                          color={colors.running}
                          letterSpacing={-0.3}>
                          {formatMinutes(dayOnDuty)}
                        </Mono>
                        <Mono
                          size={8.5}
                          color={colors.muted}
                          letterSpacing={0.4}
                          upper
                          style={{ marginTop: 2 }}>
                          {t('On duty')}
                        </Mono>
                      </View>
                    </View>
                  ) : (
                    <View style={{ gap: 8 }}>
                      <View
                        style={{
                          flexDirection: 'row',
                          justifyContent: 'space-between',
                          alignItems: 'center',
                        }}>
                        <View>
                          <Mono
                            size={13}
                            weight="700"
                            color={isToday ? colors.accent : colors.ink}
                            letterSpacing={-0.2}
                            style={{ fontFamily: undefined }}>
                            {format(d, 'EEE dd MMM')}
                          </Mono>
                        </View>
                        <View style={{ alignItems: 'flex-end' }}>
                          <Mono
                            size={14}
                            weight="700"
                            color={colors.running}
                            letterSpacing={-0.3}>
                            {formatMinutes(dayOnDuty)}
                          </Mono>
                          <Mono size={8} color={colors.muted} letterSpacing={0.4} upper>
                            {t('On duty')}
                          </Mono>
                        </View>
                      </View>
                      <ActivityTimeline
                        segments={segments}
                        typeMeta={typeMeta as any}
                        height={36}
                        showHours
                        nowMinutes={nowMin}
                      />
                    </View>
                  )}
                </Pressable>
              );
            })}
          </View>
          )}

          {/* Legend */}
          <View
            style={[
              styles.legendCard,
              { backgroundColor: colors.card, borderColor: colors.line },
            ]}>
            {Object.entries(typeMeta)
              .filter(([k]) => k !== 'off' && k !== 'custom')
              .map(([k, def]) => (
                <View key={k} style={styles.legendItem}>
                  <FontAwesome
                    name={iconForActivity(k as ActivityType)}
                    size={11}
                    color={(def as any).color}
                  />
                  <Mono size={9.5} color={colors.muted} letterSpacing={0.4} upper>
                    {(def as any).label}
                  </Mono>
                </View>
              ))}
          </View>
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  content: {
    padding: 16,
    gap: 12,
  },
  rulerCard: {
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 12,
    borderWidth: 1,
  },
  rulerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  ruler: {
    flex: 1,
    height: 16,
    position: 'relative',
  },
  dayCard: {
    borderRadius: 12,
    borderWidth: 1,
    padding: 12,
  },
  dayRowTablet: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  legendCard: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    padding: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  legendItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
});
