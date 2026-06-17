import { FontAwesome } from '@expo/vector-icons';
import { format, formatDistanceToNowStrict, isPast, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useTools } from '@/hooks/queries/useMaintenance';
import { useAuthStore } from '@/stores/authStore';
import type { Tool, ToolStatus } from '@/api/maintenance';

const FILTERS: { id: ToolStatus | 'all'; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'available', label: 'Available' },
  { id: 'in_use', label: 'In use' },
  { id: 'maintenance', label: 'Maintenance' },
  { id: 'retired', label: 'Retired' },
];

interface ToneStyle {
  color: string;
  bg: string;
  label: string;
}
const TONES: Record<ToolStatus, ToneStyle> = {
  available:   { color: '#1C9A55', bg: '#E6F4EA', label: 'AVAILABLE' },
  in_use:      { color: '#1d4ed8', bg: '#F1EFEA', label: 'IN USE' },
  maintenance: { color: '#8a5a0e', bg: '#FAF0DD', label: 'MAINTENANCE' },
  retired:     { color: '#6F6C66', bg: '#F1EFEA', label: 'RETIRED' },
};

export function ToolsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const [statusFilter, setStatusFilter] = useState<ToolStatus | 'all'>('all');
  const query = useTools({ status: statusFilter === 'all' ? undefined : statusFilter });
  const canCreate = useAuthStore((s) => s.user)?.roles?.some((r) => r.name === 'Admin') ?? false;

  const items = query.data ?? [];
  const inMaintenance = items.filter((t) => t.status === 'maintenance').length;

  return (
    <ListScreen
      title="Tools"
      eyebrow={`${items.length} ITEMS · ${inMaintenance} IN MAINTENANCE`}
      newRoute={canCreate ? '/maintenance/tools/new' : undefined}
      // The status filter pills live in extraHeader so they get the
      // horizontal-scroll affordance for narrower phones.
      extraHeader={
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipsRow}>
          {FILTERS.map((f) => {
            const active = f.id === statusFilter;
            const count =
              f.id === 'all'
                ? items.length
                : items.filter((t) => t.status === f.id).length;
            return (
              <Pressable
                key={f.id}
                onPress={() => setStatusFilter(f.id)}
                style={[
                  styles.chip,
                  {
                    backgroundColor: active ? palette.surfaceInverse : palette.surface,
                    borderColor: active ? palette.surfaceInverse : palette.border,
                  },
                ]}>
                <Mono
                  size={11}
                  color={active ? (scheme === 'dark' ? '#1A1917' : '#fff') : palette.text}
                  weight="600">
                  {f.label}
                </Mono>
                <Mono
                  size={10}
                  color={active ? '#6F6C66' : palette.textFaint}>
                  {count}
                </Mono>
              </Pressable>
            );
          })}
        </ScrollView>
      }
      items={items}
      keyExtractor={(t) => String(t.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No tools"
      renderItem={(item) => (
        <ToolRow item={item} onPress={() => router.push(`/maintenance/tools/${item.id}` as never)} />
      )}
    />
  );
}

function ToolRow({ item, onPress }: { item: Tool; onPress: () => void }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const tone = TONES[item.status];

  // "NEXT SVC" — show OVERDUE / TODAY / "in 28d" relative date.
  const nextSvc = (() => {
    if (!item.next_service_at) return null;
    try {
      const d = parseISO(item.next_service_at);
      if (isPast(d)) {
        const sameDay =
          format(d, 'yyyy-MM-dd') === format(new Date(), 'yyyy-MM-dd');
        return sameDay ? 'TODAY' : 'OVERDUE';
      }
      return `IN ${formatDistanceToNowStrict(d).toUpperCase()}`;
    } catch {
      return null;
    }
  })();

  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
      <Card style={styles.row}>
        <View style={[styles.iconWrap, { backgroundColor: tone.bg }]}>
          <FontAwesome name="cog" size={16} color={tone.color} />
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Mono size={11} color={palette.text} weight="600">
            {item.code}
          </Mono>
          <Text style={[styles.name, { color: palette.textMuted }]} numberOfLines={1}>
            {item.name}
          </Text>
          <Mono size={10} color={palette.textFaint} style={{ marginTop: 4 }}>
            {[
              item.workstation_type?.name?.toUpperCase(),
              nextSvc ? `NEXT SVC ${nextSvc}` : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          </Mono>
        </View>
        <View style={[styles.statusPill, { backgroundColor: tone.bg }]}>
          <Mono size={9.5} color={tone.color} weight="700" letterSpacing={0.5}>
            {tone.label}
          </Mono>
        </View>
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  chipsRow: { flexDirection: 'row', gap: 6 },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  name: { fontSize: 13, marginTop: 2 },
  statusPill: { paddingVertical: 3, paddingHorizontal: 6, borderRadius: 4 },
});
