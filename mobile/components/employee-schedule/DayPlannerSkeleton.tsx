// Skeleton placeholders for DayPlanScreen.
//
// Three independent sub-skeletons so each pane can show its placeholder
// only when its own query is loading — switching workers shouldn't re-
// skeleton the worker list (those data are already cached), only the
// per-worker bits in the center and detail panes.
//
//   <WorkerListSkeleton />   — worker list pane
//   <CenterPaneSkeleton />   — date strip + summary + tacho + table
//   <DetailPaneSkeleton />   — selected-activity detail
//
// A composite <DayPlannerSkeleton /> renders all three for the initial
// page-load case (everything loading at once).

import { StyleSheet, View } from 'react-native';

import { Shimmer } from '@/components/ui/Shimmer';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';

const WORKER_ROWS = 8;
const ACTIVITY_ROWS = 7;

// ── Worker list ─────────────────────────────────────────────────────────────

export function WorkerListSkeleton() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View
      style={[
        styles.pane,
        { width: 260, backgroundColor: palette.surface, borderColor: palette.border },
      ]}>
      <Shimmer height={36} radius={8} />
      <Shimmer width={120} height={10} radius={3} style={{ marginTop: 14 }} />
      <View style={{ marginTop: 8, gap: 6 }}>
        {Array.from({ length: WORKER_ROWS }).map((_, i) => (
          <View
            key={i}
            style={[
              styles.workerCard,
              { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
            ]}>
            <Shimmer width={30} height={30} radius={8} />
            <View style={{ flex: 1, gap: 6 }}>
              <Shimmer width="70%" height={11} radius={3} />
              <Shimmer width="40%" height={9} radius={3} />
            </View>
          </View>
        ))}
      </View>
      <View style={{ flex: 1 }} />
      <View style={[styles.coverageCard, { backgroundColor: palette.surfaceAlt }]}>
        <Shimmer width={90} height={9} radius={3} />
        <Shimmer width={60} height={22} radius={5} style={{ marginTop: 6 }} />
      </View>
    </View>
  );
}

// ── Center pane — summary + tacho + table ──────────────────────────────────

export function CenterPaneSkeleton() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View
      style={[
        styles.pane,
        { flex: 1, backgroundColor: palette.surface, borderColor: palette.border, padding: 18, gap: 14 },
      ]}>
      {/* Date strip */}
      <View style={{ flexDirection: 'row', gap: 6 }}>
        {Array.from({ length: 7 }).map((_, i) => (
          <Shimmer key={i} width={64} height={32} radius={8} />
        ))}
      </View>

      {/* Summary header */}
      <View
        style={{
          flexDirection: 'row',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          gap: 12,
        }}>
        <View style={{ gap: 8, flex: 1 }}>
          <Shimmer width={180} height={10} radius={3} />
          <Shimmer width={260} height={22} radius={5} />
        </View>
        <View style={{ flexDirection: 'row', gap: 12 }}>
          {Array.from({ length: 3 }).map((_, i) => (
            <View key={i} style={{ alignItems: 'flex-end', gap: 4 }}>
              <Shimmer width={60} height={9} radius={3} />
              <Shimmer width={50} height={16} radius={4} />
            </View>
          ))}
        </View>
      </View>

      {/* Tacho bar */}
      <Shimmer width="100%" height={64} radius={10} />

      {/* Legend pills */}
      <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap' }}>
        {[80, 100, 70, 90].map((w, i) => (
          <Shimmer key={i} width={w} height={24} radius={100} />
        ))}
      </View>

      {/* Activity table */}
      <View style={{ marginTop: 4, gap: 1 }}>
        <View style={[styles.tableHeader, { borderBottomColor: palette.border }]}>
          <Shimmer width={50} height={9} radius={3} />
          <Shimmer width={70} height={9} radius={3} />
          <Shimmer width={30} height={9} radius={3} />
          <Shimmer width={30} height={9} radius={3} />
          <Shimmer width={50} height={9} radius={3} />
        </View>
        {Array.from({ length: ACTIVITY_ROWS }).map((_, i) => (
          <View
            key={i}
            style={[styles.tableRow, { borderBottomColor: palette.border }]}>
            <View style={{ width: 92, flexDirection: 'row', alignItems: 'center', gap: 8 }}>
              <Shimmer width={4} height={16} radius={2} />
              <Shimmer width={12} height={12} radius={3} />
              <Shimmer width={36} height={11} radius={3} />
            </View>
            <View style={{ flex: 1, gap: 4 }}>
              <Shimmer width="60%" height={12} radius={3} />
              <Shimmer width="40%" height={9} radius={3} />
            </View>
            <Shimmer width={36} height={11} radius={3} />
            <Shimmer width={36} height={11} radius={3} />
            <Shimmer width={42} height={11} radius={3} />
          </View>
        ))}
      </View>
    </View>
  );
}

// ── Detail pane ─────────────────────────────────────────────────────────────

export function DetailPaneSkeleton() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View
      style={[
        styles.pane,
        { width: 360, backgroundColor: palette.surface, borderColor: palette.border, padding: 16, gap: 14 },
      ]}>
      <View style={{ gap: 8 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
          <Shimmer width={10} height={10} radius={3} />
          <Shimmer width={140} height={10} radius={3} />
        </View>
        <Shimmer width="80%" height={22} radius={5} />
        <Shimmer width={120} height={11} radius={3} />
      </View>
      <View style={[styles.detailCard, { backgroundColor: palette.surfaceAlt }]}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <View style={{ gap: 6 }}>
            <Shimmer width={70} height={9} radius={3} />
            <Shimmer width={100} height={32} radius={6} />
          </View>
          <View style={{ alignItems: 'flex-end', gap: 6 }}>
            <Shimmer width={50} height={9} radius={3} />
            <Shimmer width={110} height={15} radius={4} />
          </View>
        </View>
      </View>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
        {Array.from({ length: 2 }).map((_, i) => (
          <View
            key={i}
            style={{
              flexBasis: '47%',
              flexGrow: 1,
              padding: 10,
              backgroundColor: palette.surfaceAlt,
              borderRadius: 8,
              gap: 6,
            }}>
            <Shimmer width={60} height={9} radius={3} />
            <Shimmer width={90} height={12} radius={3} />
          </View>
        ))}
      </View>
      <View style={{ gap: 6 }}>
        <Shimmer width={50} height={10} radius={3} />
        <View style={[styles.notesBlock, { backgroundColor: palette.surfaceAlt }]}>
          <Shimmer width="90%" height={11} radius={3} />
          <Shimmer width="70%" height={11} radius={3} style={{ marginTop: 6 }} />
          <Shimmer width="80%" height={11} radius={3} style={{ marginTop: 6 }} />
        </View>
      </View>
    </View>
  );
}

// ── Phone — single-column stack ─────────────────────────────────────────────

export function PhoneSummarySkeleton() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View style={styles.phone}>
      {/* Date strip */}
      <View style={{ flexDirection: 'row', gap: 6 }}>
        {Array.from({ length: 7 }).map((_, i) => (
          <Shimmer key={i} width={60} height={32} radius={8} />
        ))}
      </View>
      {/* Summary card */}
      <View
        style={[
          styles.phoneSummary,
          { backgroundColor: palette.surface, borderColor: palette.border },
        ]}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <View style={{ gap: 8, flex: 1 }}>
            <Shimmer width={140} height={10} radius={3} />
            <Shimmer width={200} height={20} radius={5} />
            <Shimmer width={100} height={11} radius={3} />
          </View>
          <View style={{ alignItems: 'flex-end', gap: 6 }}>
            <Shimmer width={80} height={22} radius={5} />
            <Shimmer width={60} height={10} radius={3} />
          </View>
        </View>
        <View style={{ marginTop: 14 }}>
          <Shimmer width="100%" height={56} radius={10} />
        </View>
      </View>
      {/* Activity rows */}
      <View style={{ gap: 6 }}>
        {Array.from({ length: 6 }).map((_, i) => (
          <View
            key={i}
            style={[
              styles.phoneActivity,
              { backgroundColor: palette.surface, borderColor: palette.border },
            ]}>
            <Shimmer width={5} height={32} radius={3} />
            <Shimmer width={32} height={32} radius={8} />
            <View style={{ flex: 1, gap: 4 }}>
              <Shimmer width="60%" height={12} radius={3} />
              <Shimmer width="40%" height={9} radius={3} />
            </View>
            <View style={{ alignItems: 'flex-end', gap: 4 }}>
              <Shimmer width={70} height={11} radius={3} />
              <Shimmer width={42} height={10} radius={3} />
            </View>
          </View>
        ))}
      </View>
    </View>
  );
}

// ── Composite — initial full-page skeleton ──────────────────────────────────

export function DayPlannerSkeleton() {
  const { useTabletLayout } = useDeviceClass();
  if (!useTabletLayout) {
    return <PhoneSummarySkeleton />;
  }
  return (
    <View style={styles.tabletGrid}>
      <WorkerListSkeleton />
      <CenterPaneSkeleton />
      <DetailPaneSkeleton />
    </View>
  );
}

const styles = StyleSheet.create({
  tabletGrid: {
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
  workerCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 10,
    borderRadius: 8,
    borderWidth: 1,
  },
  coverageCard: {
    padding: 10,
    borderRadius: 8,
    marginTop: 10,
  },
  tableHeader: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderBottomWidth: 1,
  },
  tableRow: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    alignItems: 'center',
    borderBottomWidth: 1,
  },
  detailCard: {
    padding: 14,
    borderRadius: 12,
  },
  notesBlock: {
    padding: 12,
    borderRadius: 10,
  },
  phone: {
    padding: 16,
    gap: 14,
  },
  phoneSummary: {
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  phoneActivity: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 10,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
});
