import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCostSources } from '@/hooks/queries/useOps';

type FilterId = 'all' | 'inactive';

export function CostSourcesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [filter, setFilter] = useState<FilterId>('all');
  const [search, setSearch] = useState('');

  const query = useCostSources(true);
  const all = query.data ?? [];

  const counts = { all: all.length, inactive: all.filter((c) => !c.is_active).length };

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return all.filter((c) => {
      if (filter === 'inactive' && c.is_active) return false;
      if (q && !`${c.code} ${c.name}`.toLowerCase().includes(q)) return false;
      return true;
    });
  }, [all, filter, search]);

  return (
    <ListScreen
      title={t('Cost sources')}
      eyebrow={`${t('ADMIN').toUpperCase()} · ${all.length} ${t('SOURCES').toUpperCase()}`}
      newRoute="/admin/cost-sources/new"
      filters={[
        { id: 'all', label: t('All'), count: counts.all },
        { id: 'inactive', label: t('Inactive'), count: counts.inactive },
      ]}
      activeFilter={filter}
      onFilterChange={(id) => setFilter(id as FilterId)}
      extraHeader={
        <SearchBar
          placeholder="Search by code or name"
          value={search}
          onChangeText={setSearch}
        />
      }
      items={filtered}
      keyExtractor={(c) => String(c.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No cost sources')}
      renderItem={(item) => (
        <ListItem
          icon="usd"
          iconColor={BRAND.amber}
          title={item.name}
          subtitle={
            item.unit_cost
              ? `${item.code} · ${item.unit_cost} ${item.currency ?? ''}/${item.unit ?? 'unit'}`
              : item.code
          }
          disabled={!item.is_active}
          trailing={
            !item.is_active ? (
              <Mono size={9} color={palette.textFaint} weight="700" letterSpacing={0.5}>
                {t('OFF').toUpperCase()}
              </Mono>
            ) : undefined
          }
          onPress={() => router.push(`/admin/cost-sources/${item.id}` as never)}
          chevron={false}
        />
      )}
    />
  );
}
