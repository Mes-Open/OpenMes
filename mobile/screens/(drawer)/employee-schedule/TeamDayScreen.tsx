// Team day — stacked tachograph rows for every active worker on a single
// date. Phone view stacks vertically (full-width tacho per worker);
// tablet view adds a shared hour ruler and primary-worker highlight.

import { addDays, format, isSameDay, startOfDay } from 'date-fns';
import { useMemo, useState } from 'react';
import {
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import FontAwesome from '@expo/vector-icons/FontAwesome';

import { PlannerHeader } from '@/components/employee-schedule/PlannerHeader';
import { ActivityTimeline } from '@/components/employee-schedule/ActivityTimeline';
import { iconForActivity } from '@/components/employee-schedule/activityIcons';
import { Mono } from '@/components/ui/Mono';
import {
  ErrorState,
  LoadingState,
} from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useTeamDay } from '@/hooks/queries/useEmployeeActivities';
import { useEmployeeScheduleUrlState } from '@/hooks/useEmployeeScheduleUrlState';
import { formatMinutes, type ActivityType, type TypeMetaMap } from '@/api/employeeActivities';

function initials(name: string): string {
  const parts = name.trim().split(/\s+/);
  return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}

export function TeamDayScreen({
  onSelectWorker,
}: {
  onSelectWorker?: (workerId: number, date: Date) => void;
} = {}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const { useTabletLayout } = useDeviceClass();
  const router = useRouter();

  const today = startOfDay(new Date());
  const { date, setDate, setWorkerId, setView } = useEmployeeScheduleUrlState();
  const dateStr = format(date, 'yyyy-MM-dd');
  const q = useTeamDay(dateStr);

  const days = useMemo(
    () => Array.from({ length: 7 }).map((_, i) => addDays(date, i - 3)),
    [date.getTime()],
  );

  const isToday = isSameDay(date, today);
  const nowMinutes = isToday ? new Date().getHours() * 60 + new Date().getMinutes() : null;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <PlannerHeader
        eyebrow={t('Team day view')}
        title={`A-${t('shift')} · ${format(date, 'EEE dd MMM')}`}
        current="day"
        hideTabs
      />

      {q.isLoading && !q.data ? (
        <LoadingState label={t('Loading team day')} />
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
          {/* Date strip */}
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
          style={{ flexGrow: 0, flexShrink: 0 }}
            contentContainerStyle={styles.dayStripContent}>
            {days.map((d) => {
              const on = isSameDay(d, date);
              return (
                <Pressable
                  key={d.toISOString()}
                  onPress={() => setDate(d)}
                  style={[
                    styles.dayChip,
                    {
                      backgroundColor: on ? BRAND.amber : palette.surface,
                      borderColor: on ? BRAND.amber : palette.border,
                    },
                  ]}>
                  <Mono
                    size={10.5}
                    weight="700"
                    color={on ? '#1a1208' : palette.textMuted}
                    letterSpacing={0.4}
                    upper>
                    {format(d, 'EEE dd')}
                  </Mono>
                </Pressable>
              );
            })}
          </ScrollView>

          {/* Hour ruler (tablet only) */}
          {useTabletLayout ? (
            <View
              style={[
                styles.hourRulerCard,
                { backgroundColor: palette.surface, borderColor: palette.border },
              ]}>
              <View style={styles.hourRulerRow}>
                <Mono
                  size={9.5}
                  color={palette.textMuted}
                  letterSpacing={0.6}
                  upper
                  style={{ width: 180 }}>
                  {t('Worker')}
                </Mono>
                <View style={styles.hourRuler}>
                  {[0, 3, 6, 9, 12, 15, 18, 21].map((h) => (
                    <Mono
                      key={h}
                      size={9}
                      weight="700"
                      color={palette.textMuted}
                      letterSpacing={0.3}
                      style={{
                        position: 'absolute',
                        left: `${(h / 24) * 100}%`,
                      }}>
                      {String(h).padStart(2, '0')}:00
                    </Mono>
                  ))}
                </View>
                <Mono
                  size={9.5}
                  color={palette.textMuted}
                  letterSpacing={0.6}
                  upper
                  style={{ width: 80, textAlign: 'right' }}>
                  {t('On duty')}
                </Mono>
              </View>
            </View>
          ) : null}

          {/* Worker rows */}
          <View style={{ gap: 8 }}>
            {q.data?.rows.map((row) => {
              const typeMeta: TypeMetaMap = q.data.type_meta;
              return (
                <Pressable
                  key={row.worker.id}
                  onPress={() => {
                    if (onSelectWorker) {
                      onSelectWorker(row.worker.id, date);
                      return;
                    }
                    // Navigate back to the per-worker day view with the
                    // worker pre-selected so the user lands on the row
                    // they tapped, not the team-day route again.
                    router.push({
                      pathname: '/admin/employee-schedule',
                      params: {
                        view: 'day',
                        worker_id: String(row.worker.id),
                        date: format(date, 'yyyy-MM-dd'),
                      },
                    });
                  }}
                  style={[
                    styles.workerCard,
                    {
                      backgroundColor: palette.surface,
                      borderColor: palette.border,
                    },
                  ]}>
                  {useTabletLayout ? (
                    <View style={styles.workerRowTablet}>
                      <View style={[styles.workerIdent, { width: 180 }]}>
                        <View
                          style={[
                            styles.workerAvatar,
                            { backgroundColor: palette.surfaceAlt },
                          ]}>
                          <Mono size={11} weight="700" color={palette.text}>
                            {initials(row.worker.name)}
                          </Mono>
                        </View>
                        <View style={{ minWidth: 0 }}>
                          <Mono
                            size={12.5}
                            weight="700"
                            color={palette.text}
                            style={{ fontFamily: undefined }}>
                            {row.worker.name}
                          </Mono>
                          <Mono size={9} color={palette.textMuted} letterSpacing={0.3} style={{ marginTop: 2 }}>
                            {row.worker.code}
                          </Mono>
                        </View>
                      </View>
                      <View style={{ flex: 1 }}>
                        <ActivityTimeline
                          segments={row.segments}
                          typeMeta={typeMeta}
                          height={42}
                          showHours={false}
                          nowMinutes={nowMinutes}
                        />
                      </View>
                      <View style={{ width: 80, alignItems: 'flex-end' }}>
                        <Mono
                          size={16}
                          weight="700"
                          color={palette.success}
                          letterSpacing={-0.3}>
                          {formatMinutes(row.on_duty)}
                        </Mono>
                        <Mono
                          size={8.5}
                          color={palette.textMuted}
                          letterSpacing={0.4}
                          upper
                          style={{ marginTop: 2 }}>
                          {t('On duty')}
                        </Mono>
                      </View>
                    </View>
                  ) : (
                    <View style={styles.workerRowPhone}>
                      <View style={styles.workerHeaderPhone}>
                        <View style={styles.workerIdent}>
                          <View
                            style={[
                              styles.workerAvatar,
                              { backgroundColor: palette.surfaceAlt },
                            ]}>
                            <Mono size={11} weight="700" color={palette.text}>
                              {initials(row.worker.name)}
                            </Mono>
                          </View>
                          <View style={{ minWidth: 0, flex: 1 }}>
                            <Mono
                              size={12.5}
                              weight="700"
                              color={palette.text}
                              style={{ fontFamily: undefined }}>
                              {row.worker.name}
                            </Mono>
                            <Mono size={9} color={palette.textMuted} letterSpacing={0.3} style={{ marginTop: 2 }}>
                              {row.worker.code}
                            </Mono>
                          </View>
                        </View>
                        <View style={{ alignItems: 'flex-end' }}>
                          <Mono
                            size={14}
                            weight="700"
                            color={palette.success}
                            letterSpacing={-0.3}>
                            {formatMinutes(row.on_duty)}
                          </Mono>
                          <Mono
                            size={8}
                            color={palette.textMuted}
                            letterSpacing={0.4}
                            upper>
                            {t('On duty')}
                          </Mono>
                        </View>
                      </View>
                      <ActivityTimeline
                        segments={row.segments}
                        typeMeta={typeMeta}
                        height={36}
                        showHours
                        nowMinutes={nowMinutes}
                      />
                    </View>
                  )}
                </Pressable>
              );
            })}
          </View>

          {/* Legend */}
          {q.data ? (
            <View
              style={[
                styles.legendCard,
                { backgroundColor: palette.surface, borderColor: palette.border },
              ]}>
              {Object.entries(q.data.type_meta)
                .filter(([k]) => k !== 'off' && k !== 'custom')
                .map(([k, def]) => (
                  <View key={k} style={styles.legendItem}>
                    <FontAwesome
                      name={iconForActivity(k as ActivityType)}
                      size={11}
                      color={def.color}
                    />
                    <Mono size={9.5} color={palette.textMuted} letterSpacing={0.4} upper>
                      {t(def.label)}
                    </Mono>
                  </View>
                ))}
            </View>
          ) : null}
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
  dayStripContent: {
    gap: 6,
    paddingRight: 16,
  },
  dayChip: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    minWidth: 60,
    alignItems: 'center',
  },
  hourRulerCard: {
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 12,
    borderWidth: 1,
  },
  hourRulerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  hourRuler: {
    flex: 1,
    height: 16,
    position: 'relative',
  },
  workerCard: {
    borderRadius: 12,
    borderWidth: 1,
    padding: 12,
  },
  workerRowTablet: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  workerRowPhone: {
    gap: 10,
  },
  workerHeaderPhone: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 10,
  },
  workerIdent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  workerAvatar: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
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
