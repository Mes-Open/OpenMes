import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useSubassemblies } from '@/hooks/queries/useOps';

type FilterId = 'all' | 'inactive';

export function SubassembliesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [filter, setFilter] = useState<FilterId>('all');
  const [search, setSearch] = useState('');

  const query = useSubassemblies({ include_inactive: true });
  const all = query.data ?? [];

  const counts = { all: all.length, inactive: all.filter((s) => !s.is_active).length };

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return all.filter((s) => {
      if (filter === 'inactive' && s.is_active) return false;
      if (q && !`${s.code} ${s.name}`.toLowerCase().includes(q)) return false;
      return true;
    });
  }, [all, filter, search]);

  return (
    <ListScreen
      title={t('Subassemblies')}
      eyebrow={`${t('ADMIN').toUpperCase()} · ${all.length} ${t('SUBASSEMBLIES').toUpperCase()}`}
      newRoute="/admin/subassemblies/new"
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
      keyExtractor={(s) => String(s.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No subassemblies')}
      renderItem={(item) => (
        <ListItem
          icon="puzzle-piece"
          iconColor={BRAND.amber}
          title={item.name}
          subtitle={
            item.product_type?.name
              ? `${item.code} · ${item.product_type.name}`
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
          onPress={() => router.push(`/admin/subassemblies/${item.id}` as never)}
          chevron={false}
        />
      )}
    />
  );
}
