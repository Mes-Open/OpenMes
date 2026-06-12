import { useRouter } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { InactiveToggle } from '@/components/ui/InactiveToggle';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useShifts } from '@/hooks/queries/useOps';

const DAY_LABELS = ['', 'M', 'T', 'W', 'T', 'F', 'S', 'S'];

export function ShiftsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const [includeInactive, setIncludeInactive] = useState(false);
  const query = useShifts({ include_inactive: includeInactive });
  const items = query.data ?? [];

  return (
    <ListScreen
      title="Shifts"
      eyebrow={`PRODUCTION · ${items.length} SHIFTS`}
      newRoute="/production/shifts/new"
      extraHeader={<InactiveToggle value={includeInactive} onValueChange={setIncludeInactive} />}
      items={items}
      keyExtractor={(s) => String(s.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No shifts"
      renderItem={(item) => {
        const days: number[] = item.days_of_week ?? [];
        return (
          <Card onPress={() => router.push(`/production/shifts/${item.id}` as never)}>
            <View style={styles.headerRow}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono size={11} color={palette.textFaint}>
                  {item.line ? item.line.name.toUpperCase() : 'ALL LINES'}
                </Mono>
                <Text style={[styles.name, { color: palette.text }]} numberOfLines={1}>
                  {item.name}
                </Text>
              </View>
              <StatusPill
                status={item.is_active ? 'IN_PROGRESS' : 'CANCELLED'}
                label={item.is_active ? 'Active' : 'Inactive'}
              />
            </View>

            <View style={styles.timeRow}>
              <Text style={[styles.time, { color: BRAND.amber, fontFamily: MONO }]}>
                {item.start_time?.slice(0, 5) ?? '—'}
              </Text>
              <View style={[styles.timeBar, { backgroundColor: palette.surfaceAlt }]}>
                <View
                  style={[
                    styles.timeBarFill,
                    { backgroundColor: BRAND.amber, opacity: item.is_active ? 1 : 0.3 },
                  ]}
                />
              </View>
              <Text style={[styles.time, { color: BRAND.amber, fontFamily: MONO }]}>
                {item.end_time?.slice(0, 5) ?? '—'}
              </Text>
            </View>

            <View style={styles.dayRow}>
              {[1, 2, 3, 4, 5, 6, 7].map((d) => {
                const active = days.includes(d);
                return (
                  <View
                    key={d}
                    style={[
                      styles.dayChip,
                      {
                        backgroundColor: active ? palette.surfaceInverse : palette.surfaceAlt,
                      },
                    ]}>
                    <Text
                      style={{
                        fontSize: 11,
                        fontWeight: '600',
                        color: active ? (scheme === 'dark' ? '#171715' : '#fff') : palette.textFaint,
                        fontFamily: MONO,
                      }}>
                      {DAY_LABELS[d] ?? String(d)}
                    </Text>
                  </View>
                );
              })}
            </View>
          </Card>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  name: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2, marginTop: 3 },
  timeRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 14 },
  time: { fontSize: 16, fontWeight: '600', letterSpacing: 0.4 },
  timeBar: { flex: 1, height: 4, borderRadius: 2, overflow: 'hidden' },
  timeBarFill: { height: '100%', width: '100%' },
  dayRow: { flexDirection: 'row', gap: 4, marginTop: 12 },
  dayChip: {
    flex: 1,
    height: 28,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
