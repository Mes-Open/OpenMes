// Skeleton for MonthScreen — calendar grid + selected-day detail panel.
//
// Composite `<MonthSkeleton />` is the initial-load placeholder (no data
// at all). `<MonthGridSkeleton />` is the per-pane version: calendar
// cells + detail panel only, so the month-nav chevrons and the worker-
// chip strip stay mounted when switching worker/month.

import { StyleSheet, View } from 'react-native';

import { Shimmer } from '@/components/ui/Shimmer';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';

const CALENDAR_WEEKS = 5;

export function MonthGridSkeleton() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { useTabletLayout } = useDeviceClass();

  return (
    <View
      style={[
        styles.layout,
        useTabletLayout ? { flexDirection: 'row' } : { flexDirection: 'column' },
      ]}>
      <View style={{ flex: 1, gap: 6 }}>
        {/* Weekday header (the real one stays mounted; this matches it
            for the initial-load case via the composite below) */}
        <View style={{ flexDirection: 'row', gap: 4 }}>
          {Array.from({ length: 7 }).map((_, i) => (
            <View key={i} style={{ flex: 1, alignItems: 'center' }}>
              <Shimmer width={28} height={9} radius={3} />
            </View>
          ))}
        </View>

        {Array.from({ length: CALENDAR_WEEKS }).map((_, wi) => (
          <View key={wi} style={styles.weekRow}>
            {Array.from({ length: 7 }).map((_, di) => (
              <View
                key={di}
                style={[
                  styles.dayCell,
                  { backgroundColor: palette.surface, borderColor: palette.border },
                ]}>
                <Shimmer width={20} height={11} radius={3} />
                <View style={{ flex: 1 }} />
                <Shimmer width="100%" height={8} radius={2} />
                <View
                  style={{
                    flexDirection: 'row',
                    justifyContent: 'space-between',
                    marginTop: 4,
                  }}>
                  <Shimmer width={10} height={6} radius={2} />
                  <Shimmer width={10} height={6} radius={2} />
                  <Shimmer width={10} height={6} radius={2} />
                </View>
              </View>
            ))}
          </View>
        ))}
      </View>

      <View
        style={[
          styles.detailCard,
          {
            backgroundColor: palette.surface,
            borderColor: palette.border,
            width: useTabletLayout ? 320 : undefined,
          },
        ]}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <View style={{ gap: 8 }}>
            <Shimmer width={160} height={10} radius={3} />
            <Shimmer width={200} height={22} radius={5} />
          </View>
          <Shimmer width={120} height={30} radius={10} />
        </View>
        <View style={{ marginTop: 12, gap: 8 }}>
          <Shimmer width={90} height={9} radius={3} />
          <Shimmer width="100%" height={42} radius={10} />
        </View>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 12 }}>
          {Array.from({ length: 4 }).map((_, i) => (
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
              <Shimmer width={70} height={9} radius={3} />
              <Shimmer width={60} height={16} radius={4} />
            </View>
          ))}
        </View>
      </View>
    </View>
  );
}

export function MonthSkeleton() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { useTabletLayout } = useDeviceClass();

  return (
    <View style={styles.content}>
      {/* Month nav */}
      <View style={styles.monthNav}>
        <Shimmer width={36} height={36} radius={10} />
        <Shimmer width={160} height={20} radius={5} />
        <Shimmer width={36} height={36} radius={10} />
      </View>

      {/* Worker chip strip */}
      <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap' }}>
        {Array.from({ length: 6 }).map((_, i) => (
          <Shimmer key={i} width={120} height={28} radius={100} />
        ))}
      </View>

      {/* Calendar + detail panel */}
      <MonthGridSkeleton />
    </View>
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
  layout: {
    gap: 12,
  },
  weekRow: {
    flexDirection: 'row',
    gap: 4,
  },
  dayCell: {
    flex: 1,
    minHeight: 80,
    padding: 6,
    borderRadius: 8,
    borderWidth: 1,
  },
  detailCard: {
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
});
