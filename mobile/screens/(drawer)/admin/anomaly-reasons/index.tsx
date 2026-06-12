import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAnomalyReasons } from '@/hooks/queries/useOps';

type SevFilter = 'all' | 'minor' | 'major' | 'scrap' | 'inactive';

const SEV_COLORS: Record<string, string> = {
  minor: BRAND.amber,
  major: '#dc2626',
  scrap: '#7c3aed',
  cosmetic: '#8a8780',
};

/**
 * Catalog list screen — anomaly reasons is the visual template the design
 * specifies for all catalog entities (cost-sources, companies,
 * subassemblies, lot-sequences also follow this layout).
 *
 * Pattern: search bar + filter chips with counts + card with colored-icon
 * rows + inline severity badge + dim/OFF for inactive entries.
 */
export function AnomalyReasonsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [filter, setFilter] = useState<SevFilter>('all');
  const [search, setSearch] = useState('');

  // Always include inactive — the "Inactive" filter chip toggles visibility
  // rather than refetching, so we have everything in hand for counts.
  const query = useAnomalyReasons({ include_inactive: true });
  const all = query.data ?? [];

  const counts = useMemo(() => {
    const c = { all: all.length, minor: 0, major: 0, scrap: 0, inactive: 0 };
    for (const r of all) {
      const sev = (r.category ?? '').toLowerCase();
      if (!r.is_active) c.inactive++;
      if (sev === 'minor') c.minor++;
      else if (sev === 'major') c.major++;
      else if (sev === 'scrap') c.scrap++;
    }
    return c;
  }, [all]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return all.filter((r) => {
      const sev = (r.category ?? '').toLowerCase();
      if (filter === 'inactive' && r.is_active) return false;
      if (filter !== 'all' && filter !== 'inactive' && sev !== filter) return false;
      if (q && !`${r.code} ${r.name}`.toLowerCase().includes(q)) return false;
      return true;
    });
  }, [all, filter, search]);

  return (
    <ListScreen
      title={t('Anomaly reasons')}
      eyebrow={`${t('ADMIN').toUpperCase()} · ${t('CATALOG').toUpperCase()} · ${all.length} ${t('ENTRIES').toUpperCase()}`}
      newRoute="/admin/anomaly-reasons/new"
      filters={[
        { id: 'all', label: t('All'), count: counts.all },
        { id: 'minor', label: t('Minor'), count: counts.minor },
        { id: 'major', label: t('Major'), count: counts.major },
        { id: 'scrap', label: t('Scrap'), count: counts.scrap },
        { id: 'inactive', label: t('Inactive'), count: counts.inactive },
      ]}
      activeFilter={filter}
      onFilterChange={(id) => setFilter(id as SevFilter)}
      extraHeader={
        <View style={{ gap: 10 }}>
          <SearchBar
            placeholder="Search by code or name"
            value={search}
            onChangeText={setSearch}
          />
        </View>
      }
      items={filtered}
      keyExtractor={(r) => String(r.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No anomaly reasons')}
      renderItem={(item) => {
        const sev = (item.category ?? 'minor').toLowerCase();
        const sevColor = SEV_COLORS[sev] ?? palette.textMuted;
        return (
          <ListItem
            icon="flag"
            iconColor={sevColor}
            title={item.name}
            inlineBadge={item.category ? { label: item.category, color: sevColor } : undefined}
            subtitle={item.code}
            disabled={!item.is_active}
            trailing={
              !item.is_active ? (
                <Mono size={9} color={palette.textFaint} weight="700" letterSpacing={0.5}>
                  {t('OFF').toUpperCase()}
                </Mono>
              ) : undefined
            }
            onPress={() => router.push(`/admin/anomaly-reasons/${item.id}` as never)}
            chevron={false}
          />
        );
      }}
    />
  );
}
