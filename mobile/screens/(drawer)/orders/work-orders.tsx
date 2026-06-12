import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';
import { useTranslation } from 'react-i18next';

import { Mono, SectionLabel } from '@/components/ui/Mono';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { WorkOrderCard } from '@/components/workorders/WorkOrderCard';
import { TabletStatusStripLive } from '@/components/tablet/TabletStatusStripLive';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { useLines } from '@/hooks/queries/useUsers';
import type { WorkOrderStatus } from '@/types/api';

// Labels are i18n keys (English phrase = key, per Laravel __() convention).
const STATUS_GROUPS: { key: string; label: string; statuses?: WorkOrderStatus[] }[] = [
  { key: 'all', label: 'All' },
  { key: 'active', label: 'Active', statuses: ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'BLOCKED', 'PAUSED'] },
  { key: 'pending', label: 'Not Started', statuses: ['PENDING'] },
  { key: 'in_progress', label: 'Running', statuses: ['IN_PROGRESS'] },
  { key: 'blocked', label: 'Blocked', statuses: ['BLOCKED'] },
  { key: 'done', label: 'Done', statuses: ['DONE'] },
];

export function WorkOrdersListScreen() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const { useTabletLayout: isTablet } = useDeviceClass();

  const [statusKey, setStatusKey] = useState<string>('active');
  const [lineId, setLineId] = useState<number | null>(null);

  const linesQuery = useLines();
  const lines = linesQuery.data ?? [];

  const filters = useMemo(() => {
    const group = STATUS_GROUPS.find((g) => g.key === statusKey);
    const f: { status?: WorkOrderStatus[]; line_id?: number; per_page?: number } = { per_page: 100 };
    if (group?.statuses) f.status = group.statuses;
    if (lineId != null) f.line_id = lineId;
    return f;
  }, [statusKey, lineId]);

  const query = useWorkOrders(filters);
  const orders = query.data ?? [];
  const counts = useMemo(() => groupCounts(orders), [orders]);

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      {isTablet ? <TabletStatusStripLive /> : null}
      <View style={[styles.filters, { borderBottomColor: palette.border }]}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipsRow}>
          {STATUS_GROUPS.map((g) => {
            const active = g.key === statusKey;
            const c = g.key === 'all' ? orders.length : counts[g.key] ?? 0;
            return (
              <Pressable
                key={g.key}
                onPress={() => setStatusKey(g.key)}
                style={[
                  styles.chip,
                  {
                    backgroundColor: active ? '#241a08' : 'transparent',
                    borderColor: active ? BRAND.amber : palette.border,
                  },
                ]}>
                <Text
                  style={[styles.chipText, { color: active ? BRAND.amber : palette.textMuted }]}>
                  {t(g.label)}
                </Text>
                <Mono size={10} color={active ? BRAND.amber : palette.textFaint}>
                  {c}
                </Mono>
              </Pressable>
            );
          })}
        </ScrollView>
        {lines.length > 1 ? (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={[styles.chipsRow, { marginTop: 8 }]}>
            <Pressable
              onPress={() => setLineId(null)}
              style={[
                styles.chip,
                {
                  backgroundColor: lineId == null ? palette.surfaceInverse : 'transparent',
                  borderColor: lineId == null ? palette.surfaceInverse : palette.border,
                },
              ]}>
              <Text
                style={[
                  styles.chipText,
                  {
                    color: lineId == null ? (scheme === 'dark' ? '#171715' : '#fff') : palette.textMuted,
                  },
                ]}>
                All lines
              </Text>
            </Pressable>
            {lines.map((l) => {
              const active = l.id === lineId;
              return (
                <Pressable
                  key={l.id}
                  onPress={() => setLineId(l.id)}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: active ? palette.surfaceInverse : 'transparent',
                      borderColor: active ? palette.surfaceInverse : palette.border,
                    },
                  ]}>
                  <Text
                    style={[
                      styles.chipText,
                      {
                        color: active ? (scheme === 'dark' ? '#171715' : '#fff') : palette.textMuted,
                      },
                    ]}>
                    {l.name}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>
        ) : null}
      </View>

      {query.isLoading ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <LegendList
          style={{ backgroundColor: palette.background }}
          data={orders}
          keyExtractor={(wo) => String(wo.id)}
          contentContainerStyle={styles.list}
          ListHeaderComponent={
            <SectionLabel
              right={
                <Mono size={11} color={palette.textFaint}>
                  {orders.length} {orders.length === 1 ? 'ORDER' : 'ORDERS'}
                </Mono>
              }>
              Work orders
            </SectionLabel>
          }
          ListEmptyComponent={
            <EmptyState title="No work orders" subtitle="Try a different filter or pull to refresh." />
          }
          renderItem={({ item }) => (
            <WorkOrderCard workOrder={item} onPress={() => router.push(`/work-orders/${item.id}`)} />
          )}
          ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} />}
        />
      )}
    </View>
  );
}

function groupCounts(orders: { status: string }[]) {
  const counts: Record<string, number> = {};
  for (const g of STATUS_GROUPS) {
    if (!g.statuses) continue;
    counts[g.key] = orders.filter((o) => g.statuses!.includes(o.status as WorkOrderStatus)).length;
  }
  return counts;
}

const styles = StyleSheet.create({
  filters: { paddingHorizontal: 18, paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth },
  chipsRow: { flexDirection: 'row', gap: 8 },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
  chipText: { fontSize: 12, fontWeight: '600' },
  list: { padding: 18, gap: 10 },
});
