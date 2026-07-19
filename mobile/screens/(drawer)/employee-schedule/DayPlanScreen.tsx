// Tachograph-style day plan for a single worker.
//
// Layout (matches /tmp/design-fetch/openmes-test-remix/project/om-screens-tacho.jsx):
//   • Phone — header → date strip → summary band → legend pills → activity list
//   • Tablet — header → 3-pane grid:
//       LEFT  (260px) Worker list (search + cards + shift coverage)
//       CENTER (1fr)   Summary band + tacho + legend pills + activity table
//       RIGHT (360px) Selected-activity detail panel

import { addDays, format, isSameDay, startOfDay } from 'date-fns';
import { useMemo, useState } from 'react';
import {
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  TextInput,
  View,
} from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { FontAwesome } from '@expo/vector-icons';

import { colors, fonts } from '@openmes/ui';

import { ActivityLegendPills } from '@/components/employee-schedule/ActivityLegendPills';
import { iconForActivity } from '@/components/employee-schedule/activityIcons';
import {
  CenterPaneSkeleton,
  DetailPaneSkeleton,
  PhoneSummarySkeleton,
  WorkerListSkeleton,
} from '@/components/employee-schedule/DayPlannerSkeleton';
import { PlannerHeader } from '@/components/employee-schedule/PlannerHeader';
import { ActivityTimeline } from '@/components/employee-schedule/ActivityTimeline';
import { Mono, Sans } from '@/components/ui/Mono';
import {
  EmptyState,
  ErrorState,
  LoadingState,
} from '@/components/ui/StateViews';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useWorkers } from '@/hooks/queries/useHr';
import { useEmployeeDayPlan } from '@/hooks/queries/useEmployeeActivities';
import { useEmployeeScheduleUrlState } from '@/hooks/useEmployeeScheduleUrlState';
import { useAuthStore } from '@/stores/authStore';
import {
  formatMinutes,
  productiveMinutes,
  toMinutes,
  type DaySegment,
  type TypeMetaMap,
} from '@/api/employeeActivities';

function initials(name: string): string {
  const parts = name.trim().split(/\s+/);
  return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}

interface Props {
  onAdd?: (workerId: number, date: Date) => void;
}

export function DayPlanScreen({ onAdd }: Props = {}) {
  const { t } = useTranslation();
  const router = useRouter();
  const { useTabletLayout } = useDeviceClass();

  const today = startOfDay(new Date());
  const { date, setDate, workerId, setWorkerId } = useEmployeeScheduleUrlState();

  const workersQ = useWorkers({ per_page: 200 });
  const workers = workersQ.data?.data ?? [];

  // If no worker_id in URL, fall back to the signed-in user's worker
  // record (matched by email) and otherwise the first active worker.
  const me = useAuthStore((s) => s.user);
  const effectiveWorkerId = useMemo(() => {
    if (workerId != null) return workerId;
    const mine = workers.find((w) => w.email && me?.email && w.email === me.email);
    return mine?.id ?? workers[0]?.id ?? null;
  }, [workerId, workers, me?.email]);

  const dateStr = format(date, 'yyyy-MM-dd');
  const planQ = useEmployeeDayPlan(effectiveWorkerId ?? undefined, dateStr);

  const isLoading = workersQ.isLoading || planQ.isLoading;
  const error = workersQ.error ?? planQ.error;

  const plan = planQ.data;
  const segments: DaySegment[] = plan?.segments ?? [];
  const typeMeta: TypeMetaMap | undefined = plan?.type_meta;
  const summary = plan?.summary ?? {};

  const isToday = isSameDay(date, today);
  const nowMinutes = isToday ? new Date().getHours() * 60 + new Date().getMinutes() : null;

  // The "NOW" segment — the one containing the current minute.
  const nowSegmentId = useMemo(() => {
    if (!isToday || nowMinutes == null) return null;
    for (const s of segments) {
      const f = toMinutes(s.from);
      const to = s.to === '24:00' ? 24 * 60 : toMinutes(s.to);
      if (nowMinutes >= f && nowMinutes < to) return s.id;
    }
    return null;
  }, [isToday, nowMinutes, segments]);

  // Currently-selected activity in the tablet detail panel. Defaults to the
  // NOW segment, then the first non-off segment.
  const [selectedActivityId, setSelectedActivityId] = useState<number | null>(null);
  const detailSegment = useMemo<DaySegment | undefined>(() => {
    if (selectedActivityId != null) {
      return segments.find((s) => s.id === selectedActivityId);
    }
    if (nowSegmentId != null) return segments.find((s) => s.id === nowSegmentId);
    return segments.find((s) => s.id != null && s.type !== 'off');
  }, [selectedActivityId, nowSegmentId, segments]);

  const selectedWorker = workers.find((w) => w.id === effectiveWorkerId);

  const dateStrip = useMemo(
    () => Array.from({ length: 7 }).map((_, i) => addDays(date, i - 3)),
    [date.getTime()],
  );

  const handleAdd = () => {
    if (effectiveWorkerId == null) return;
    if (onAdd) onAdd(effectiveWorkerId, date);
  };

  const handleTeamDay = () => {
    router.push({
      pathname: '/admin/employee-schedule/team',
      params: { date: format(date, 'yyyy-MM-dd') },
    });
  };

  const headerTitle = selectedWorker
    ? `${selectedWorker.name} · ${format(date, 'EEE dd MMM')}`
    : t('Day plan');

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <PlannerHeader
        eyebrow={t('Employee day plan')}
        title={headerTitle}
        current="day"
        onAdd={effectiveWorkerId != null ? handleAdd : undefined}
        showTeamButton
        onTeamDay={handleTeamDay}
      />

      {error ? (
        <ErrorState error={error} onRetry={() => planQ.refetch()} />
      ) : useTabletLayout ? (
        // Per-pane loading: worker list, center band and detail panel each
        // skeleton independently so switching workers doesn't re-flash the
        // (already-cached) worker list.
        <TabletDayPlanner
          workers={workers}
          workersLoading={workersQ.isLoading && workers.length === 0}
          selectedWorkerId={effectiveWorkerId}
          onSelectWorker={setWorkerId}
          plan={plan}
          planLoading={planQ.isLoading && !plan}
          segments={segments}
          typeMeta={typeMeta}
          summary={summary}
          nowMinutes={nowMinutes}
          nowSegmentId={nowSegmentId}
          detailSegment={detailSegment}
          onSelectActivity={setSelectedActivityId}
          dateStrip={dateStrip}
          date={date}
          onPickDate={setDate}
          t={t}
        />
      ) : (
        <PhoneDayPlanner
          workers={workers}
          selectedWorkerId={effectiveWorkerId}
          onSelectWorker={setWorkerId}
          plan={plan}
          planLoading={planQ.isLoading && !plan}
          segments={segments}
          typeMeta={typeMeta}
          summary={summary}
          nowMinutes={nowMinutes}
          nowSegmentId={nowSegmentId}
          dateStrip={dateStrip}
          date={date}
          onPickDate={setDate}
          isToday={isToday}
          refetching={planQ.isRefetching}
          onRefresh={() => planQ.refetch()}
          onAdd={effectiveWorkerId != null ? handleAdd : undefined}
          t={t}
        />
      )}
    </View>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// Tablet — 3-pane grid
// ═══════════════════════════════════════════════════════════════════════════

function TabletDayPlanner({
  workers,
  workersLoading,
  selectedWorkerId,
  onSelectWorker,
  plan,
  planLoading,
  segments,
  typeMeta,
  summary,
  nowMinutes,
  nowSegmentId,
  detailSegment,
  onSelectActivity,
  dateStrip,
  date,
  onPickDate,
  t,
}: {
  workers: any[];
  workersLoading: boolean;
  selectedWorkerId: number | null;
  onSelectWorker: (id: number) => void;
  plan?: any;
  planLoading: boolean;
  segments: DaySegment[];
  typeMeta?: TypeMetaMap;
  summary: Partial<Record<string, number>>;
  nowMinutes: number | null;
  nowSegmentId: number | null;
  detailSegment?: DaySegment;
  onSelectActivity: (id: number | null) => void;
  dateStrip: Date[];
  date: Date;
  onPickDate: (d: Date) => void;
  t: (k: string) => string;
}) {
  const totalProductive = productiveMinutes(summary);
  const totalPlanned = Object.values(summary).reduce<number>((a, b) => a + (b ?? 0), 0);
  const breaks = (summary.break ?? 0) + (summary.rest ?? 0);

  // Each pane skeletons independently — switching workers re-fetches the
  // plan, but the worker list is already cached and shouldn't flash.
  if (workersLoading && planLoading) {
    return (
      <View style={tablet.grid}>
        <WorkerListSkeleton />
        <CenterPaneSkeleton />
        <DetailPaneSkeleton />
      </View>
    );
  }

  return (
    <View style={tablet.grid}>
      {workersLoading ? (
        <WorkerListSkeleton />
      ) : (
      /* LEFT — Worker list */
      <View style={[tablet.pane, { width: 260, backgroundColor: colors.card, borderColor: colors.line }]}>
        <View
          style={[
            tablet.searchBox,
            { backgroundColor: colors.chip, borderColor: colors.line },
          ]}>
          <FontAwesome name="search" size={12} color={colors.muted} />
          <TextInput
            placeholder={t('Search worker')}
            placeholderTextColor={colors.faint}
            style={{
              flex: 1,
              fontFamily: fonts.mono.native.regular,
              fontSize: 11,
              color: colors.ink,
              padding: 0,
            }}
          />
        </View>
        <Mono size={10} color={colors.muted} letterSpacing={0.7} upper style={{ marginTop: 14 }}>
          A-{t('shift')} · {workers.length} {t('on')}
        </Mono>
        <ScrollView style={{ marginTop: 8 }} contentContainerStyle={{ gap: 6 }}>
          {workers.map((w) => {
            const on = w.id === selectedWorkerId;
            return (
              <Pressable
                key={w.id}
                onPress={() => onSelectWorker(w.id)}
                style={[
                  tablet.workerCard,
                  {
                    backgroundColor: on ? colors.chip : colors.card,
                    borderColor: on ? colors.accent : colors.line,
                  },
                ]}>
                <View
                  style={[
                    tablet.workerAvatar,
                    {
                      backgroundColor: on ? colors.accent : colors.card,
                    },
                  ]}>
                  <Mono size={10} weight="700" color={on ? colors.bg : colors.ink}>
                    {initials(w.name)}
                  </Mono>
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Sans
                    size={12}
                    weight="700"
                    color={colors.ink}
                    numberOfLines={1}
                    style={{ }}>
                    {w.name}
                  </Sans>
                  <Mono size={9} color={colors.muted} letterSpacing={0.3} style={{ marginTop: 2 }}>
                    {w.code}
                  </Mono>
                </View>
              </Pressable>
            );
          })}
        </ScrollView>
        <View
          style={[
            tablet.coverageCard,
            { backgroundColor: colors.chip },
          ]}>
          <Mono size={9} color={colors.muted} letterSpacing={0.5} upper>
            {t('Shift coverage')}
          </Mono>
          <Mono
            size={22}
            weight="700"
            color={colors.running}
            letterSpacing={-0.4}
            style={{ marginTop: 3 }}>
            {workers.length}
            <Mono size={12} color={colors.muted}>
              /{workers.length}
            </Mono>
          </Mono>
        </View>
      </View>
      )}

      {planLoading || !plan || !typeMeta ? (
        <CenterPaneSkeleton />
      ) : (
      /* CENTER — Summary band + tacho + legend + activity table */
      <View style={[tablet.pane, { flex: 1, backgroundColor: colors.card, borderColor: colors.line, padding: 18, gap: 14 }]}>
        {/* Date strip — flexShrink:0 + alignItems prevents the chips
            stretching to fill the column on RN-Web. */}
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          style={{ flexGrow: 0, flexShrink: 0 }}
          contentContainerStyle={{ gap: 6, alignItems: 'center' }}>
          {dateStrip.map((d) => {
            const on = isSameDay(d, date);
            return (
              <Pressable
                key={d.toISOString()}
                onPress={() => onPickDate(d)}
                style={[
                  tablet.dayChip,
                  {
                    backgroundColor: on ? colors.accent : colors.chip,
                    borderColor: on ? colors.accent : colors.line,
                  },
                ]}>
                <Mono
                  size={10.5}
                  weight="700"
                  color={on ? colors.bg : colors.muted}
                  letterSpacing={0.4}
                  upper>
                  {format(d, 'EEE dd')}
                </Mono>
              </Pressable>
            );
          })}
        </ScrollView>

        {/* Summary header */}
        <View style={tablet.summaryHeader}>
          <View>
            <Mono size={10} weight="700" color={colors.accent} letterSpacing={0.7} upper>
              {format(date, 'EEE dd MMM')} · A-{t('shift')}
            </Mono>
            <Sans
              size={18}
              weight="700"
              color={colors.ink}
              style={{ marginTop: 4 }}>
              {formatMinutes(totalPlanned)} {t('on duty')} · {segments.filter((s) => s.id != null).length}{' '}
              {t('activities')}
            </Sans>
          </View>
          <View style={{ flexDirection: 'row', gap: 14 }}>
            <KPI label={t('Productive')} value={formatMinutes(totalProductive)} color={colors.running} />
            <KPI label={t('Breaks')} value={formatMinutes(breaks)} color={colors.downtime} />
            <KPI label={t('Maint')} value={formatMinutes(summary.maint ?? 0)} color={colors.blocked} />
          </View>
        </View>

        {/* Big tacho — clicking a colored block selects that activity in
            the detail panel (same effect as clicking the table row). */}
        <ActivityTimeline
          segments={segments}
          typeMeta={typeMeta}
          height={64}
          highlightSegmentId={detailSegment?.id ?? nowSegmentId ?? null}
          nowMinutes={nowMinutes}
          onSegmentPress={(id) => onSelectActivity(id)}
        />

        {/* Legend pills */}
        <ActivityLegendPills summary={summary} typeMeta={typeMeta} hideOff />

        {/* Activity table */}
        <ScrollView style={{ flex: 1 }} contentContainerStyle={{ paddingBottom: 12 }}>
          <View style={[tablet.tableHeader, { borderBottomColor: colors.line }]}>
            <Mono size={9} color={colors.muted} letterSpacing={0.5} weight="700" style={{ width: 92 }} upper>
              {t('Activity')}
            </Mono>
            <Mono size={9} color={colors.muted} letterSpacing={0.5} weight="700" style={{ flex: 1 }} upper>
              {t('Detail')}
            </Mono>
            <Mono size={9} color={colors.muted} letterSpacing={0.5} weight="700" style={{ width: 70 }} upper>
              {t('From')}
            </Mono>
            <Mono size={9} color={colors.muted} letterSpacing={0.5} weight="700" style={{ width: 70 }} upper>
              {t('To')}
            </Mono>
            <Mono size={9} color={colors.muted} letterSpacing={0.5} weight="700" style={{ width: 60, textAlign: 'right' }} upper>
              {t('Duration')}
            </Mono>
          </View>
          {segments
            .filter((s) => s.id != null || s.type !== 'off')
            .map((s) => {
              const def = typeMeta[s.type];
              const hl = s.id != null && (
                (detailSegment?.id === s.id) ||
                (detailSegment == null && s.id === nowSegmentId)
              );
              return (
                <Pressable
                  key={`${s.id ?? s.from}`}
                  onPress={() => s.id != null && onSelectActivity(s.id)}
                  style={[
                    tablet.tableRow,
                    {
                      // Selected row tints with the activity's own color so
                      // the type-association is obvious at a glance — amber
                      // suggested "warning" which read wrong for MAINT/QC.
                      backgroundColor: hl ? def.color + '20' : 'transparent',
                      borderLeftColor: hl ? def.color : 'transparent',
                    },
                  ]}>
                  <View style={{ width: 92, flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                    <View style={{ width: 4, height: 16, borderRadius: 2, backgroundColor: def.color }} />
                    <FontAwesome name={iconForActivity(s.type)} size={12} color={def.color} />
                    <Mono size={10.5} weight="700" color={colors.ink} letterSpacing={0.3} upper>
                      {def.short}
                    </Mono>
                  </View>
                  <View style={{ flex: 1 }}>
                    <Sans
                      size={12}
                      weight="600"
                      color={colors.ink}
                      style={{ }}
                      numberOfLines={1}>
                      {s.label ?? t(def.label)}
                    </Sans>
                    {s.work_order ? (
                      <Mono size={9.5} color={colors.muted} style={{ marginTop: 2 }}>
                        {s.work_order.order_no}
                        {s.step_name ? ` · ${s.step_name}` : ''}
                      </Mono>
                    ) : null}
                  </View>
                  <Mono size={11} weight="600" color={colors.ink} style={{ width: 70 }}>
                    {s.from}
                  </Mono>
                  <Mono size={11} weight="600" color={colors.ink} style={{ width: 70 }}>
                    {s.to}
                  </Mono>
                  <Mono
                    size={11}
                    weight="700"
                    color={def.color}
                    style={{ width: 60, textAlign: 'right' }}>
                    {formatMinutes(s.duration_min)}
                  </Mono>
                </Pressable>
              );
            })}
        </ScrollView>
      </View>
      )}

      {planLoading || !plan || !typeMeta ? (
        <DetailPaneSkeleton />
      ) : (
      /* RIGHT — Selected activity detail */
      <View style={[tablet.pane, { width: 360, backgroundColor: colors.card, borderColor: colors.line, padding: 16, gap: 14 }]}>
        {detailSegment && typeMeta ? (
          <DetailPanel segment={detailSegment} typeMeta={typeMeta} t={t} />
        ) : (
          <EmptyState title={t('Select an activity')} />
        )}
      </View>
      )}
    </View>
  );
}

function DetailPanel({
  segment,
  typeMeta,
  t,
}: {
  segment: DaySegment;
  typeMeta: TypeMetaMap;
  t: (k: string) => string;
}) {
  const def = typeMeta[segment.type];
  return (
    <ScrollView contentContainerStyle={{ gap: 14, paddingBottom: 8 }}>
      <View>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
          <View style={{ width: 10, height: 10, borderRadius: 3, backgroundColor: def.color }} />
          <FontAwesome name={iconForActivity(segment.type)} size={13} color={def.color} />
          <Mono size={10} weight="700" color={colors.accent} letterSpacing={0.7} upper>
            {t('Selected')} · {def.short}
          </Mono>
        </View>
        <Sans
          size={20}
          weight="700"
          color={colors.ink}
          style={{ marginTop: 6 }}>
          {segment.label ?? t(def.label)}
        </Sans>
        <Mono size={10.5} color={colors.muted} letterSpacing={0.3} style={{ marginTop: 4 }} upper>
          {t(def.label)}
        </Mono>
      </View>

      <View
        style={{
          padding: 14,
          backgroundColor: colors.chip,
          borderRadius: 12,
        }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
          <View>
            <Mono size={9.5} color={colors.muted} letterSpacing={0.6} upper>
              {t('Duration')}
            </Mono>
            <Mono
              size={32}
              weight="700"
              color={def.color}
              letterSpacing={-0.7}
              style={{ marginTop: 3 }}>
              {formatMinutes(segment.duration_min)}
            </Mono>
          </View>
          <View style={{ alignItems: 'flex-end' }}>
            <Mono size={9.5} color={colors.muted} letterSpacing={0.6} upper>
              {t('Window')}
            </Mono>
            <Mono size={15} weight="700" color={colors.ink} style={{ marginTop: 3 }}>
              {segment.from} → {segment.to}
            </Mono>
          </View>
        </View>
      </View>

      {(segment.work_order || segment.line) ? (
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
          {segment.work_order ? (
            <DetailCell label={t('Work Order')} value={segment.work_order.order_no} />
          ) : null}
          {segment.step_name ? (
            <DetailCell label={t('Step')} value={segment.step_name} />
          ) : null}
          {segment.line ? (
            <DetailCell label={t('Line')} value={segment.line.name} />
          ) : null}
        </View>
      ) : null}

      {segment.notes ? (
        <View>
          <Mono size={10.5} color={colors.muted} letterSpacing={0.8} upper>
            {t('Notes')}
          </Mono>
          <View
            style={{
              marginTop: 8,
              padding: 12,
              backgroundColor: colors.chip,
              borderRadius: 10,
            }}>
            <Sans
              size={12}
              color={colors.ink}
              style={{ fontStyle: 'italic' }}>
              “{segment.notes}”
            </Sans>
          </View>
        </View>
      ) : null}
    </ScrollView>
  );
}

function DetailCell({ label, value }: { label: string; value: string }) {
  return (
    <View
      style={{
        flexBasis: '47%',
        flexGrow: 1,
        padding: 10,
        backgroundColor: colors.chip,
        borderRadius: 8,
      }}>
      <Mono size={9} color={colors.muted} letterSpacing={0.5} upper>
        {label}
      </Mono>
      <Mono size={12} weight="700" color={colors.ink} style={{ marginTop: 3 }}>
        {value}
      </Mono>
    </View>
  );
}

function KPI({
  label,
  value,
  color,
}: {
  label: string;
  value: string;
  color: string;
}) {
  return (
    <View style={{ alignItems: 'flex-end' }}>
      <Mono size={8.5} color={colors.muted} letterSpacing={0.4} upper>
        {label}
      </Mono>
      <Mono size={16} weight="700" color={color} style={{ marginTop: 2 }}>
        {value}
      </Mono>
    </View>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// Phone — stacked single-column
// ═══════════════════════════════════════════════════════════════════════════

function PhoneDayPlanner({
  workers,
  selectedWorkerId,
  onSelectWorker,
  plan,
  planLoading,
  segments,
  typeMeta,
  summary,
  nowMinutes,
  nowSegmentId,
  dateStrip,
  date,
  onPickDate,
  isToday,
  refetching,
  onRefresh,
  onAdd,
  t,
}: {
  workers: any[];
  selectedWorkerId: number | null;
  onSelectWorker: (id: number) => void;
  plan?: any;
  planLoading: boolean;
  segments: DaySegment[];
  typeMeta?: TypeMetaMap;
  summary: Partial<Record<string, number>>;
  nowMinutes: number | null;
  nowSegmentId: number | null;
  dateStrip: Date[];
  date: Date;
  onPickDate: (d: Date) => void;
  isToday: boolean;
  refetching: boolean;
  onRefresh: () => void;
  onAdd?: () => void;
  t: (k: string) => string;
}) {
  const totalProductive = productiveMinutes(summary);
  const totalPlanned = Object.values(summary).reduce<number>((a, b) => a + (b ?? 0), 0);

  if (planLoading || !plan || !typeMeta) {
    return <PhoneSummarySkeleton />;
  }

  return (
    <ScrollView
      contentContainerStyle={phone.content}
      refreshControl={
        <RefreshControl refreshing={refetching} onRefresh={onRefresh} tintColor={colors.accent} />
      }>
      {/* Worker chip strip */}
      {workers.length > 1 ? (
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          style={{ flexGrow: 0, flexShrink: 0 }}
          contentContainerStyle={phone.chipScroller}>
          {workers.map((w) => {
            const on = w.id === selectedWorkerId;
            return (
              <Pressable
                key={w.id}
                onPress={() => onSelectWorker(w.id)}
                style={[
                  phone.workerChip,
                  {
                    backgroundColor: on ? colors.accent : colors.card,
                    borderColor: on ? colors.accent : colors.line,
                  },
                ]}>
                <View
                  style={[
                    phone.workerBadge,
                    { backgroundColor: on ? colors.ink : colors.chip },
                  ]}>
                  <Mono size={9} weight="700" color={on ? colors.accent : colors.ink}>
                    {initials(w.name)}
                  </Mono>
                </View>
                <Sans
                  size={11}
                  weight="600"
                  color={on ? colors.bg : colors.ink}
                  style={{ }}>
                  {w.name}
                </Sans>
              </Pressable>
            );
          })}
        </ScrollView>
      ) : null}

      {/* Date strip */}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
          style={{ flexGrow: 0, flexShrink: 0 }}
        contentContainerStyle={phone.dayStripContent}>
        {dateStrip.map((d) => {
          const on = isSameDay(d, date);
          return (
            <Pressable
              key={d.toISOString()}
              onPress={() => onPickDate(d)}
              style={[
                phone.dayChip,
                {
                  backgroundColor: on ? colors.accent : colors.card,
                  borderColor: on ? colors.accent : colors.line,
                },
              ]}>
              <Mono
                size={10.5}
                weight="700"
                color={on ? colors.bg : colors.muted}
                letterSpacing={0.4}
                upper>
                {format(d, 'EEE dd')}
              </Mono>
            </Pressable>
          );
        })}
      </ScrollView>

      {/* Summary band */}
      <View
        style={[
          phone.summaryCard,
          { backgroundColor: colors.card, borderColor: colors.line },
        ]}>
        <View style={phone.summaryHeader}>
          <View style={{ flex: 1 }}>
            <Mono size={10} weight="700" color={colors.accent} letterSpacing={0.7} upper>
              {format(date, 'EEE dd MMM')} · A-{t('shift')}
            </Mono>
            <Sans
              size={18}
              weight="700"
              color={colors.ink}
              style={{ marginTop: 6 }}>
              {formatMinutes(totalPlanned)} {t('planned')}
            </Sans>
            <Mono size={10.5} color={colors.muted} letterSpacing={0.3} style={{ marginTop: 4 }} upper>
              {segments.filter((s) => s.id != null).length} {t('activities')}
            </Mono>
          </View>
          <View style={{ alignItems: 'flex-end' }}>
            <Mono size={22} weight="700" color={colors.running} letterSpacing={-0.4}>
              {formatMinutes(totalProductive)}
            </Mono>
            <Mono size={9} color={colors.muted} letterSpacing={0.4} upper style={{ marginTop: 2 }}>
              {t('Productive')}
            </Mono>
          </View>
        </View>

        <View style={{ marginTop: 14 }}>
          <ActivityTimeline
            segments={segments}
            typeMeta={typeMeta}
            highlightSegmentId={nowSegmentId ?? null}
            nowMinutes={nowMinutes}
          />
        </View>

        <View style={phone.sumRow}>
          <SumChip label={t('work')} value={formatMinutes(summary.work ?? 0)} color={colors.ink} />
          <SumChip label={t('breaks')} value={formatMinutes((summary.break ?? 0) + (summary.rest ?? 0))} color={colors.ink} />
          <SumChip label={t('maint')} value={formatMinutes(summary.maint ?? 0)} color={colors.blocked} />
          <SumChip label={t('off')} value={formatMinutes(summary.off ?? 0)} color={colors.ink} />
        </View>
      </View>

      {/* Legend pills */}
      <ActivityLegendPills summary={summary} typeMeta={typeMeta} hideOff />

      {/* Activity list */}
      <View>
        <Mono size={10.5} color={colors.muted} letterSpacing={0.8} upper style={phone.listLabel}>
          {t('Activities')} · {segments.filter((s) => s.id != null).length}
        </Mono>
        <View style={{ gap: 4 }}>
          {segments.map((s, idx) => {
            const def = typeMeta[s.type];
            if (!def) return null;
            const hl =
              isToday &&
              nowMinutes != null &&
              nowMinutes >= toMinutes(s.from) &&
              nowMinutes < (s.to === '24:00' ? 24 * 60 : toMinutes(s.to));
            return (
              <View
                key={`${idx}-${s.id ?? 'gap'}-${s.from}`}
                style={[
                  phone.activityRow,
                  {
                    backgroundColor: hl ? colors.chip : colors.card,
                    borderColor: hl ? colors.accent : colors.line,
                  },
                ]}>
                <View style={[phone.activityStripe, { backgroundColor: def.color }]} />
                <View style={[phone.activityIcon, { backgroundColor: def.color + '25' }]}>
                  <FontAwesome name={iconForActivity(s.type)} size={14} color={def.color} />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <View style={phone.activityHeaderRow}>
                    <Sans
                      size={12.5}
                      weight="600"
                      color={colors.ink}
                      style={{ flexShrink: 1 }}>
                      {s.label ?? t(def.label)}
                    </Sans>
                    {hl ? (
                      <View style={[phone.nowChip, { backgroundColor: colors.accent }]}>
                        <Mono size={8} weight="700" color={colors.bg} letterSpacing={0.4} upper>
                          {t('now')}
                        </Mono>
                      </View>
                    ) : null}
                  </View>
                  {s.work_order ? (
                    <Mono size={9.5} color={colors.muted} letterSpacing={0.3} style={{ marginTop: 3 }}>
                      {s.work_order.order_no}
                      {s.step_name ? ` · ${s.step_name}` : ''}
                    </Mono>
                  ) : null}
                </View>
                <View style={{ alignItems: 'flex-end', minWidth: 76 }}>
                  <Mono size={10.5} weight="600" color={colors.muted}>
                    {s.from} → {s.to}
                  </Mono>
                  <Mono size={9.5} weight="700" color={def.color} letterSpacing={0.3} style={{ marginTop: 2 }}>
                    {formatMinutes(s.duration_min)}
                  </Mono>
                </View>
              </View>
            );
          })}
        </View>
      </View>

      {/* Quick add CTA */}
      {onAdd ? (
        <Pressable
          onPress={onAdd}
          style={[
            phone.addBtn,
            { borderColor: colors.line, backgroundColor: 'transparent' },
          ]}>
          <FontAwesome name="plus" size={12} color={colors.accent} />
          <Mono size={11.5} weight="700" color={colors.accent} letterSpacing={0.5} upper>
            {t('Add activity')}
          </Mono>
        </Pressable>
      ) : null}
    </ScrollView>
  );
}

function SumChip({
  label,
  value,
  color,
}: {
  label: string;
  value: string;
  color: string;
}) {
  return (
    <View style={phone.sumChip}>
      <Mono size={10.5} weight="700" color={color}>
        {value}
      </Mono>
      <Mono size={10.5} color={colors.muted} letterSpacing={0.3}>
        {label}
      </Mono>
    </View>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// Styles
// ═══════════════════════════════════════════════════════════════════════════

const tablet = StyleSheet.create({
  grid: {
    flex: 1,
    flexDirection: 'row',
    gap: 14,
    padding: 14,
    minHeight: 0,
  },
  pane: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 14,
    minHeight: 0,
  },
  searchBox: {
    height: 36,
    borderRadius: 8,
    borderWidth: 1,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    gap: 8,
  },
  workerCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 10,
    borderRadius: 8,
    borderWidth: 1,
  },
  workerAvatar: {
    width: 30,
    height: 30,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  coverageCard: {
    padding: 10,
    borderRadius: 8,
    marginTop: 10,
  },
  summaryHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    flexWrap: 'wrap',
    gap: 12,
  },
  dayChip: {
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 8,
    borderWidth: 1,
    minWidth: 60,
    alignItems: 'center',
    userSelect: 'none',
  },
  tableHeader: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderBottomWidth: 1,
  },
  tableRow: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    alignItems: 'center',
    borderLeftWidth: 3,
  },
});

// Phone styles — single-column scroll.
const phone = StyleSheet.create({
  content: {
    padding: 16,
    gap: 14,
  },
  chipScroller: {
    gap: 6,
    paddingRight: 16,
  },
  workerChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 100,
    borderWidth: 1,
    userSelect: 'none',
  },
  workerBadge: {
    width: 22,
    height: 22,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
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
    userSelect: 'none',
  },
  summaryCard: {
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  summaryHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  sumRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 14,
  },
  sumChip: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 4,
  },
  listLabel: {
    marginBottom: 8,
  },
  activityRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 10,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  activityStripe: {
    width: 5,
    alignSelf: 'stretch',
    borderRadius: 3,
  },
  activityIcon: {
    width: 32,
    height: 32,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  activityHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  nowChip: {
    paddingHorizontal: 4,
    paddingVertical: 1,
    borderRadius: 3,
  },
  addBtn: {
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
});
