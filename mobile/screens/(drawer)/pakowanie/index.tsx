import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';

import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { usePackagingItems, usePackagingStats } from '@/hooks/queries/usePackaging';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';

export function PakowanieDashboard() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const router = useRouter();

  const itemsQuery = usePackagingItems();
  const statsQuery = usePackagingStats();

  const user = useAuthStore((s) => s.user);
  const canManage = isSupervisorOrAdmin(user);

  if (itemsQuery.isLoading || statsQuery.isLoading) return <LoadingState />;
  if (itemsQuery.isError) return <ErrorState error={itemsQuery.error} onRetry={itemsQuery.refetch} />;

  const stats = statsQuery.data;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title="Packaging" subtitle="MODULE · DASHBOARD" />
      <LegendList
        style={{ backgroundColor: palette.background }}
        data={itemsQuery.data ?? []}
        keyExtractor={(i) => String(i.id)}
        contentContainerStyle={styles.list}
        ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
        ListHeaderComponent={
          <View style={{ gap: 14, marginBottom: 14 }}>
            {stats ? (
              <View style={styles.statsGrid}>
                <Stat label="TODAY" value={stats.today_packed} accent={palette.tint} />
                <Stat label="PLAN" value={stats.plan} />
                <Stat label="TOTAL" value={stats.total_packed} accent={palette.success} />
                <Stat label="BACKLOG" value={stats.backlog} accent={palette.warning} />
              </View>
            ) : null}

            {canManage ? (
              <Pressable
                onPress={() => router.push('/(drawer)/pakowanie/eans' as never)}
                style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
                <Card style={styles.linkCard}>
                  <View style={[styles.iconWrap, { backgroundColor: '#fbe9c8' }]}>
                    <FontAwesome name="qrcode" size={16} color={BRAND.amber} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.itemTitle, { color: palette.text }]}>Manage EANs</Text>
                    <Mono size={11} color={palette.textFaint}>ASSIGN BARCODES TO WORK ORDERS</Mono>
                  </View>
                  <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
                </Card>
              </Pressable>
            ) : null}

            <SectionLabel
              right={<Mono size={11} color={palette.textFaint}>{itemsQuery.data?.length ?? 0} ORDERS</Mono>}>
              Active orders
            </SectionLabel>
          </View>
        }
      ListEmptyComponent={
        <EmptyState
          title="No work orders to pack"
          subtitle="Items appear here when there are IN_PROGRESS or DONE orders with EANs assigned."
        />
      }
      renderItem={({ item }) => (
        <Card>
          <View style={styles.row}>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Mono size={11} color={palette.textFaint}>{item.order_no}</Mono>
              {item.product ? (
                <Text style={[styles.itemTitle, { color: palette.text }]} numberOfLines={1}>
                  {item.product}
                </Text>
              ) : null}
            </View>
            <StatusPill status={item.status} />
          </View>
          <View style={[styles.bar, { backgroundColor: palette.surfaceAlt }]}>
            <View
              style={[
                styles.barFill,
                { width: `${item.progress}%`, backgroundColor: item.done ? palette.success : BRAND.amber },
              ]}
            />
          </View>
          <Mono size={11} color={palette.textFaint} style={{ marginTop: 8 }}>
            {item.packed_qty}/{item.planned_qty} PACKED · {item.eans.length} EAN
            {item.line ? ` · ${item.line.toUpperCase()}` : ''}
          </Mono>
        </Card>
      )}
      refreshControl={
        <RefreshControl
          refreshing={itemsQuery.isFetching || statsQuery.isFetching}
          onRefresh={() => {
            itemsQuery.refetch();
            statsQuery.refetch();
          }}
        />
      }
      />
    </View>
  );
}

function Stat({ label, value, accent }: { label: string; value: number; accent?: string }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Card style={styles.kpi}>
      <Text style={[styles.kpiValue, { color: accent ?? palette.text, fontFamily: MONO }]}>{value}</Text>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.6}>{label}</Mono>
    </Card>
  );
}

const styles = StyleSheet.create({
  list: { padding: 18 },
  heading: { fontSize: 24, fontWeight: '600', letterSpacing: -0.4, marginTop: 4 },
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  kpi: { flexBasis: '48%', flexGrow: 1, padding: 12, gap: 6 },
  kpiValue: { fontSize: 24, fontWeight: '600' },
  iconWrap: { width: 40, height: 40, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  itemTitle: { fontSize: 15, fontWeight: '600', letterSpacing: -0.2, marginTop: 3 },
  linkCard: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  bar: { height: 4, borderRadius: 2, marginTop: 10, overflow: 'hidden' },
  barFill: { height: '100%' },
});
