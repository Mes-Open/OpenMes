import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useLotSequences } from '@/hooks/queries/useLot';

/**
 * LOT sequences list — design shows pattern preview alongside next-value.
 * Renders `{prefix}-{YY}-{NNNN}` style template + the next concrete value.
 */
export function LotSequencesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [search, setSearch] = useState('');

  const query = useLotSequences();
  const all = query.data ?? [];

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return all;
    return all.filter((s) =>
      `${s.prefix ?? ''} ${s.suffix ?? ''} ${s.name} ${s.product_type?.name ?? ''}`
        .toLowerCase()
        .includes(q),
    );
  }, [all, search]);

  return (
    <ListScreen
      title={t('LOT sequences')}
      eyebrow={`${t('ADMIN').toUpperCase()} · ${all.length} ${t('SEQUENCES').toUpperCase()}`}
      newRoute="/admin/lot-sequences/new"
      extraHeader={
        <SearchBar
          placeholder="Search by prefix or product"
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
      emptyTitle={t('No LOT sequences')}
      emptySubtitle={t('Add one to control how lot numbers are generated.')}
      renderItem={(item) => {
        // Build a visual pattern token: {prefix}-{YY?}-{NNNN}{suffix?}
        const tokens = [
          item.prefix ?? null,
          item.year_prefix ? '{YY}' : null,
          item.pad_size ? `{${'N'.repeat(item.pad_size)}}` : null,
          item.suffix ?? null,
        ].filter(Boolean);
        const pattern = tokens.join('-') || '—';
        return (
          <Card
            onPress={() => router.push(`/admin/lot-sequences/${item.id}` as never)}>
            <View style={styles.headerRow}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
                  {(item.product_type?.name?.toUpperCase()) ?? t('DEFAULT').toUpperCase()}
                </Mono>
                <Text style={[styles.name, { color: palette.text }]} numberOfLines={1}>
                  {item.name}
                </Text>
              </View>
            </View>

            {/* Pattern preview block — design's signature visual treatment. */}
            <View style={[styles.patternBlock, { backgroundColor: palette.surfaceAlt }]}>
              <View style={styles.patternHeader}>
                <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>
                  {t('PATTERN').toUpperCase()}
                </Mono>
                {item.next_value != null ? (
                  <View style={styles.nextPillRow}>
                    <Mono size={9.5} color={BRAND.amber} letterSpacing={0.6}>
                      {t('NEXT').toUpperCase()}
                    </Mono>
                    <Mono size={12} color="#8a5a0e" weight="700">{item.next_value}</Mono>
                  </View>
                ) : null}
              </View>
              <Text style={[styles.pattern, { color: palette.text, fontFamily: MONO }]}>
                {pattern}
              </Text>
            </View>
          </Card>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  name: { fontSize: 15, fontWeight: '600', letterSpacing: -0.2, marginTop: 3 },
  useCountPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 6,
  },
  patternBlock: { padding: 12, borderRadius: 8, marginTop: 12, gap: 6 },
  patternHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  nextPillRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  pattern: { fontSize: 16, fontWeight: '700', letterSpacing: 1.2 },
});
