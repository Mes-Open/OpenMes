// Skeleton for WeekScreen — 7 stacked day rows mirroring the real layout.
//
// Exposes both the composite full-page skeleton (initial load) and a
// thinner `WeekRowsSkeleton` that only mocks the 7 day rows. Switching
// worker/date should swap rows in place without re-flashing the hour
// ruler or legend.

import { StyleSheet, View } from 'react-native';

import { Shimmer } from '@/components/ui/Shimmer';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';

export function WeekRowsSkeleton() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { useTabletLayout } = useDeviceClass();
  return (
    <View style={{ gap: 8 }}>
      {Array.from({ length: 7 }).map((_, i) => (
        <View
          key={i}
          style={[
            styles.dayCard,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          {useTabletLayout ? (
            <View style={styles.dayRowTablet}>
              <View style={{ width: 140, gap: 6 }}>
                <Shimmer width={60} height={14} radius={3} />
                <Shimmer width={80} height={11} radius={3} />
              </View>
              <View style={{ flex: 1 }}>
                <Shimmer width="100%" height={42} radius={10} />
              </View>
              <View style={{ width: 80, alignItems: 'flex-end', gap: 6 }}>
                <Shimmer width={60} height={16} radius={4} />
                <Shimmer width={50} height={9} radius={3} />
              </View>
            </View>
          ) : (
            <View style={{ gap: 8 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                <Shimmer width={140} height={14} radius={3} />
                <View style={{ alignItems: 'flex-end', gap: 4 }}>
                  <Shimmer width={56} height={14} radius={4} />
                  <Shimmer width={40} height={8} radius={3} />
                </View>
              </View>
              <Shimmer width="100%" height={36} radius={10} />
            </View>
          )}
        </View>
      ))}
    </View>
  );
}

export function WeekSkeleton() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { useTabletLayout } = useDeviceClass();

  return (
    <View style={styles.content}>
      {/* Hour ruler (tablet only) */}
      {useTabletLayout ? (
        <View
          style={[
            styles.rulerCard,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <View style={styles.rulerRow}>
            <Shimmer width={50} height={9} radius={3} style={{ width: 140 }} />
            <View style={{ flex: 1, flexDirection: 'row', justifyContent: 'space-between' }}>
              {Array.from({ length: 8 }).map((_, i) => (
                <Shimmer key={i} width={36} height={9} radius={3} />
              ))}
            </View>
            <Shimmer width={60} height={9} radius={3} />
          </View>
        </View>
      ) : null}

      {/* 7 day rows */}
      <WeekRowsSkeleton />

      {/* Legend */}
      <View
        style={[
          styles.legendCard,
          { backgroundColor: palette.surface, borderColor: palette.border },
        ]}>
        {[70, 60, 80, 80, 90, 70, 90].map((w, i) => (
          <Shimmer key={i} width={w} height={10} radius={3} />
        ))}
      </View>
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
    gap: 12,
    padding: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
});
