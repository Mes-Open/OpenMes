import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useMaterials } from '@/hooks/queries/useBom';
import type { Material } from '@/api/bom';

type FilterId = 'all' | 'below_min';

function num(v: number | string | null | undefined): number | null {
  if (v == null || v === '') return null;
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return Number.isFinite(n) ? n : null;
}

export function MaterialsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [q, setQ] = useState('');
  const [filter, setFilter] = useState<FilterId>('all');

  const query = useMaterials({ search: q.trim() || undefined });
  const all = query.data ?? [];

  const counts = useMemo(() => {
    const c = { all: all.length, below_min: 0 };
    for (const m of all) {
      const stock = num(m.stock_quantity);
      const min = num(m.min_stock_level);
      if (stock != null && min != null && stock < min) c.below_min++;
    }
    return c;
  }, [all]);

  const filtered = useMemo(() => {
    if (filter === 'all') return all;
    return all.filter((m) => {
      const stock = num(m.stock_quantity);
      const min = num(m.min_stock_level);
      return stock != null && min != null && stock < min;
    });
  }, [all, filter]);

  return (
    <ListScreen
      title={t('Materials')}
      eyebrow={`${t('ADMIN').toUpperCase()} · ${all.length} ${t('ITEMS').toUpperCase()} · ${counts.below_min} ${t('LOW').toUpperCase()}`}
      filters={[
        { id: 'all', label: t('All'), count: counts.all },
        { id: 'below_min', label: t('Below min'), count: counts.below_min },
      ]}
      activeFilter={filter}
      onFilterChange={(id) => setFilter(id as FilterId)}
      extraHeader={
        <SearchBar
          placeholder="Search by code or name"
          value={q}
          onChangeText={setQ}
        />
      }
      items={filtered}
      keyExtractor={(m) => String(m.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No materials')}
      renderItem={(item) => (
        <MaterialRow
          item={item}
          onPress={() => router.push(`/admin/materials/${item.id}` as never)}
        />
      )}
    />
  );
}

function MaterialRow({ item, onPress }: { item: Material; onPress: () => void }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const stock = num(item.stock_quantity);
  const min = num(item.min_stock_level);
  const belowMin = stock != null && min != null && stock < min;
  const iconColor = belowMin ? palette.warning : palette.success;

  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
      <Card leftAccent={belowMin ? palette.warning : undefined} style={belowMin ? { backgroundColor: '#FAF0DD' } : undefined}>
        <View style={styles.row}>
          <View style={[styles.iconWrap, { backgroundColor: `${iconColor}22` }]}>
            <FontAwesome name="cube" size={18} color={iconColor} />
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={[styles.name, { color: palette.text }]} numberOfLines={1}>
              {item.name}
            </Text>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.3} style={{ marginTop: 3 }}>
              {item.code} · {t('MIN').toUpperCase()} {min ?? 0} {item.unit_of_measure?.toUpperCase() ?? ''}
            </Mono>
          </View>
          <View style={{ alignItems: 'flex-end' }}>
            <Mono
              size={15}
              color={belowMin ? palette.warning : palette.text}
              weight="700"
              letterSpacing={-0.3}>
              {stock != null ? formatStock(stock) : '—'}
            </Mono>
            <Mono size={9} color={palette.textFaint} letterSpacing={0.4} weight="600">
              {item.unit_of_measure?.toUpperCase() ?? ''}
            </Mono>
          </View>
        </View>
      </Card>
    </Pressable>
  );
}

function formatStock(n: number): string {
  if (Number.isInteger(n)) return String(n);
  return n.toFixed(3).replace(/\.?0+$/, '');
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconWrap: {
    width: 38,
    height: 38,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  name: { fontSize: 13, fontWeight: '600' },
});
