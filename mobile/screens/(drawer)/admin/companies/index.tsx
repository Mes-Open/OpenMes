import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCompanies } from '@/hooks/queries/useOps';
import type { CompanyType } from '@/api/ops';

type FilterId = 'all' | CompanyType | 'inactive';

const TYPE_COLORS: Record<CompanyType, string> = {
  supplier: '#3a6ed6',
  customer: '#1f9d6c',
  both: BRAND.amber,
};

export function CompaniesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [filter, setFilter] = useState<FilterId>('all');
  const [search, setSearch] = useState('');

  const query = useCompanies({ include_inactive: true });
  const all = query.data ?? [];

  const counts = useMemo(() => {
    const c = { all: all.length, supplier: 0, customer: 0, both: 0, inactive: 0 };
    for (const x of all) {
      if (!x.is_active) c.inactive++;
      if (x.type === 'supplier') c.supplier++;
      else if (x.type === 'customer') c.customer++;
      else if (x.type === 'both') c.both++;
    }
    return c;
  }, [all]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return all.filter((c) => {
      if (filter === 'inactive' && c.is_active) return false;
      if (filter !== 'all' && filter !== 'inactive' && c.type !== filter) return false;
      if (q && !`${c.code} ${c.name}`.toLowerCase().includes(q)) return false;
      return true;
    });
  }, [all, filter, search]);

  return (
    <ListScreen
      title={t('Companies')}
      eyebrow={`${t('ADMIN').toUpperCase()} · ${all.length} ${t('COMPANIES').toUpperCase()}`}
      newRoute="/admin/companies/new"
      filters={[
        { id: 'all', label: t('All'), count: counts.all },
        { id: 'supplier', label: t('Suppliers'), count: counts.supplier },
        { id: 'customer', label: t('Customers'), count: counts.customer },
        { id: 'both', label: t('Both'), count: counts.both },
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
      emptyTitle={t('No companies')}
      renderItem={(item) => {
        const typeColor = TYPE_COLORS[item.type as CompanyType] ?? BRAND.amber;
        return (
          <ListItem
            icon="building"
            iconColor={typeColor}
            title={item.name}
            inlineBadge={{ label: t(item.type), color: typeColor }}
            subtitle={item.email ? `${item.code} · ${item.email}` : item.code}
            disabled={!item.is_active}
            trailing={
              !item.is_active ? (
                <Mono size={9} color={palette.textFaint} weight="700" letterSpacing={0.5}>
                  {t('OFF').toUpperCase()}
                </Mono>
              ) : undefined
            }
            onPress={() => router.push(`/admin/companies/${item.id}` as never)}
            chevron={false}
          />
        );
      }}
    />
  );
}
