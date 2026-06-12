import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useWorkstationTypes } from '@/hooks/queries/useWorkstationTypes';

export function WorkstationTypesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [search, setSearch] = useState('');

  const query = useWorkstationTypes({ include_inactive: true });
  const all = query.data ?? [];

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return all;
    return all.filter((wt) => `${wt.code} ${wt.name}`.toLowerCase().includes(q));
  }, [all, search]);

  return (
    <ListScreen
      title={t('Workstation types')}
      eyebrow={`${t('STRUCTURE').toUpperCase()} · ${all.length} ${t('TYPES').toUpperCase()}`}
      newRoute="/structure/workstation-types/new"
      extraHeader={
        <SearchBar
          placeholder="Search workstation types"
          value={search}
          onChangeText={setSearch}
        />
      }
      items={filtered}
      keyExtractor={(wt) => String(wt.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No workstation types')}
      renderItem={(item) => (
        <ListItem
          icon="cog"
          iconColor={palette.textMuted}
          title={item.name}
          subtitle={
            item.workstations_count != null
              ? `${item.code} · ${item.workstations_count} ${t('INSTALLED').toUpperCase()}`
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
          onPress={() => router.push(`/structure/workstation-types/${item.id}` as never)}
          chevron={false}
        />
      )}
    />
  );
}
