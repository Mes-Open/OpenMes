import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useProductTypes } from '@/hooks/queries/useProductTypes';

export function ProductTypesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [search, setSearch] = useState('');

  const query = useProductTypes({ include_inactive: true });
  const all = query.data ?? [];

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return all;
    return all.filter((p) => `${p.code ?? ''} ${p.name}`.toLowerCase().includes(q));
  }, [all, search]);

  return (
    <ListScreen
      title={t('Product types')}
      eyebrow={`${t('PRODUCTION').toUpperCase()} · ${all.length} ${t('TYPES').toUpperCase()}`}
      newRoute="/production/product-types/new"
      extraHeader={
        <SearchBar
          placeholder="Search by code or name"
          value={search}
          onChangeText={setSearch}
        />
      }
      items={filtered}
      keyExtractor={(p) => String(p.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No product types')}
      renderItem={(item) => (
        <ListItem
          icon="cube"
          iconColor={BRAND.amber}
          title={item.name}
          subtitle={
            [
              item.code,
              item.unit_of_measure?.toUpperCase(),
              item.process_templates_count != null
                ? `${item.process_templates_count} ${t('TEMPLATES').toUpperCase()}`
                : null,
            ]
              .filter(Boolean)
              .join(' · ')
          }
          disabled={!item.is_active}
          trailing={
            !item.is_active ? (
              <Mono size={9} color={palette.textFaint} weight="700" letterSpacing={0.5}>
                {t('OFF').toUpperCase()}
              </Mono>
            ) : undefined
          }
          onPress={() => router.push(`/production/product-types/${item.id}` as never)}
          chevron={false}
        />
      )}
    />
  );
}
