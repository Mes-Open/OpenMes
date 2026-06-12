import { FontAwesome } from '@expo/vector-icons';
import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useDeleteEan,
  useEans,
  usePackagingItems,
  usePackagingStats,
} from '@/hooks/queries/usePackaging';
import type { WorkOrderEan } from '@/api/packaging';

type FilterId = 'all' | 'active' | 'closed';

export function EansList() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const [filter, setFilter] = useState<FilterId>('all');

  const query = useEans({});
  const stats = usePackagingStats();
  const items = usePackagingItems();
  const deleteMutation = useDeleteEan();

  // Build a map of WO id → packed/target (planned) for the per-EAN bar.
  // Backend doesn't expose this on the EAN endpoint, so we join client-side.
  const woProgress = useMemo(() => {
    const map = new Map<number, { packed: number; target: number; done: boolean }>();
    (items.data ?? []).forEach((it) => {
      map.set(it.id, { packed: it.packed_qty, target: it.planned_qty, done: it.done });
    });
    return map;
  }, [items.data]);

  const all = query.data?.data ?? [];
  const filtered = useMemo(() => {
    if (filter === 'all') return all;
    return all.filter((e) => {
      const wo = e.work_order_id ? woProgress.get(e.work_order_id) : null;
      const isClosed = wo?.done === true;
      return filter === 'closed' ? isClosed : !isClosed;
    });
  }, [filter, all, woProgress]);

  const onDelete = (id: number, ean: string) =>
    Alert.alert('Delete EAN', `Remove "${ean}"?`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(id, {
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          }),
      },
    ]);

  // Stats for the dark hero card. Backend gives `today_packed` + `total_packed`.
  // Active EAN count comes from the unfiltered EAN list.
  const today = stats.data?.today_packed ?? 0;
  const totalPacked = stats.data?.total_packed ?? 0;
  const activeEans = all.length;

  return (
    <ListScreen
      title="Packaging EANs"
      eyebrow={`PAKOWANIE · ${all.length} EAN${all.length === 1 ? '' : 'S'}`}
      newRoute="/pakowanie/eans/new"
      extraHeader={
        <View style={{ gap: 14 }}>
          <View style={styles.statsCard}>
            <StatCol label="PACKED TODAY" value={fmtNum(today)} sub="↑ TODAY" />
            <View style={styles.divider} />
            <StatCol
              label="TOTAL PACKED"
              value={fmtNum(totalPacked)}
              sub={stats.data?.shift_start ? 'SINCE SHIFT START' : ''}
            />
            <View style={styles.divider} />
            <StatCol
              label="ACTIVE EAN"
              value={String(activeEans)}
              sub={items.data ? `ON ${countLines(items.data)} LINES` : ''}
            />
          </View>

          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ flexDirection: 'row', gap: 6 }}>
            {(
              [
                { id: 'all', label: 'All' },
                { id: 'active', label: 'Active' },
                { id: 'closed', label: 'Closed' },
              ] as { id: FilterId; label: string }[]
            ).map((f) => {
              const active = f.id === filter;
              return (
                <Pressable
                  key={f.id}
                  onPress={() => setFilter(f.id)}
                  style={[
                    styles.filter,
                    {
                      backgroundColor: active ? palette.surfaceInverse : palette.surface,
                      borderColor: active ? palette.surfaceInverse : palette.border,
                    },
                  ]}>
                  <Mono
                    size={11}
                    color={active ? (scheme === 'dark' ? '#1A1917' : '#fff') : palette.text}
                    weight="600"
                    letterSpacing={0.4}>
                    {f.label.toUpperCase()}
                  </Mono>
                </Pressable>
              );
            })}
          </ScrollView>
        </View>
      }
      items={filtered}
      keyExtractor={(e) => String(e.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No EANs"
      emptySubtitle="Tap + to bind one to a work order."
      renderItem={(item) => (
        <EanCard
          item={item}
          progress={item.work_order_id ? woProgress.get(item.work_order_id) : null}
          onDelete={() => onDelete(item.id, item.ean)}
        />
      )}
    />
  );
}

function StatCol({ label, value, sub }: { label: string; value: string; sub?: string }) {
  return (
    <View style={{ flex: 1, paddingHorizontal: 4 }}>
      <Mono size={9.5} color="#6F6C66" letterSpacing={0.7}>{label}</Mono>
      <Text style={styles.statValue}>{value}</Text>
      {sub ? (
        <Mono size={9.5} color="#7a7a82" letterSpacing={0.4} style={{ marginTop: 3 }}>
          {sub}
        </Mono>
      ) : null}
    </View>
  );
}

function EanCard({
  item,
  progress,
  onDelete,
}: {
  item: WorkOrderEan;
  progress: { packed: number; target: number; done: boolean } | null | undefined;
  onDelete: () => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const target = progress?.target ?? 0;
  const packed = progress?.packed ?? 0;
  const pct = target > 0 ? Math.min(100, Math.round((packed / target) * 100)) : 0;
  const fillColor = pct === 0 ? '#cfccc4' : pct < 100 ? BRAND.amber : palette.success;

  return (
    <Card style={{ gap: 10 }}>
      <View style={styles.row}>
        <View style={[styles.iconWrap, { backgroundColor: palette.surfaceAlt }]}>
          <FontAwesome name="qrcode" size={18} color={palette.text} />
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={[styles.ean, { color: palette.text, fontFamily: MONO }]} numberOfLines={1}>
            {item.ean}
          </Text>
          {item.work_order?.product_type ? (
            <Text style={[styles.product, { color: palette.textMuted }]} numberOfLines={1}>
              {item.work_order.product_type.name}
            </Text>
          ) : null}
        </View>
        <View style={{ alignItems: 'flex-end', gap: 4 }}>
          {item.work_order?.order_no ? (
            <Mono size={10.5} color={palette.textFaint}>{item.work_order.order_no}</Mono>
          ) : null}
          <Pressable
            onPress={onDelete}
            hitSlop={6}
            style={({ pressed }) => [
              styles.deleteBtn,
              {
                borderColor: palette.border,
                backgroundColor: palette.dangerSoft,
                opacity: pressed ? 0.7 : 1,
              },
            ]}>
            <FontAwesome name="trash" size={12} color={palette.danger} />
          </Pressable>
        </View>
      </View>
      <View style={styles.barRow}>
        <View style={[styles.barTrack, { backgroundColor: palette.surfaceAlt }]}>
          <View style={[styles.barFill, { width: `${pct}%`, backgroundColor: fillColor }]} />
        </View>
        <Mono size={12} color={palette.text} weight="600" style={{ minWidth: 70, textAlign: 'right' }}>
          {target > 0 ? `${packed}/${target}` : '— / —'}
        </Mono>
      </View>
    </Card>
  );
}

function fmtNum(n: number) {
  return n.toLocaleString('en-US');
}

function countLines(items: { line?: string | null }[]) {
  const set = new Set<string>();
  items.forEach((i) => {
    if (i.line) set.add(i.line);
  });
  return set.size;
}

const styles = StyleSheet.create({
  statsCard: {
    backgroundColor: '#F6F5F1',
    borderRadius: 14,
    padding: 16,
    flexDirection: 'row',
  },
  divider: { width: StyleSheet.hairlineWidth, backgroundColor: '#2c2c30', marginHorizontal: 4 },
  statValue: {
    color: BRAND.amber,
    fontFamily: MONO,
    fontSize: 22,
    fontWeight: '600',
    letterSpacing: -0.5,
    marginTop: 4,
  },
  filter: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconWrap: { width: 38, height: 38, borderRadius: 8, alignItems: 'center', justifyContent: 'center' },
  ean: { fontSize: 13, fontWeight: '700', letterSpacing: 0.4 },
  product: { fontSize: 12, marginTop: 3 },
  deleteBtn: {
    width: 28,
    height: 28,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  barRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  barTrack: { flex: 1, height: 6, borderRadius: 3, overflow: 'hidden' },
  barFill: { height: '100%' },
});
