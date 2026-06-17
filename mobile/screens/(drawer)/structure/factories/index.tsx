import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useFactories } from '@/hooks/queries/useOrgStructure';

/**
 * Factories list — design specifies a rich card per factory with stat tiles
 * (DIVISIONS / LINES / STATUS) rather than a compact ListItem row.
 */
export function FactoriesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [search, setSearch] = useState('');

  const query = useFactories(true);
  const all = query.data ?? [];

  const totalDivisions = useMemo(
    () => all.reduce((sum, f) => sum + (f.divisions_count ?? 0), 0),
    [all],
  );

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return all;
    return all.filter((f) =>
      `${f.code} ${f.name} ${f.description ?? ''}`.toLowerCase().includes(q),
    );
  }, [all, search]);

  return (
    <ListScreen
      title={t('Factories')}
      eyebrow={`${t('STRUCTURE').toUpperCase()} · ${all.length} ${t('SITES').toUpperCase()} · ${totalDivisions} ${t('DIVISIONS').toUpperCase()}`}
      newRoute="/structure/factories/new"
      extraHeader={
        <SearchBar
          placeholder="Search by name or address"
          value={search}
          onChangeText={setSearch}
        />
      }
      items={filtered}
      keyExtractor={(f) => String(f.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No factories')}
      renderItem={(item) => {
        const active = item.is_active !== false;
        return (
          <Pressable
            onPress={() => router.push(`/structure/factories/${item.id}` as never)}
            style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
            <View
              style={[
                styles.card,
                {
                  backgroundColor: palette.surface,
                  borderColor: palette.border,
                  opacity: active ? 1 : 0.55,
                },
              ]}>
              <View style={styles.head}>
                <View style={[styles.iconWrap, { backgroundColor: palette.surfaceAlt }]}>
                  <FontAwesome name="building" size={22} color={palette.textMuted} />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={[styles.name, { color: palette.text }]} numberOfLines={1}>
                    {item.name}
                  </Text>
                  <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 3 }}>
                    {item.code.toUpperCase()}
                  </Mono>
                  {item.description ? (
                    <Text style={[styles.addr, { color: palette.textMuted }]} numberOfLines={2}>
                      {item.description}
                    </Text>
                  ) : null}
                </View>
              </View>

              <View style={styles.statsRow}>
                <Tile
                  label={t('DIVISIONS').toUpperCase()}
                  value={String(item.divisions_count ?? 0)}
                  bg={palette.surfaceAlt}
                  color={palette.text}
                />
                <Tile
                  label={t('STATUS').toUpperCase()}
                  value={active ? t('ACTIVE').toUpperCase() : t('PAUSED').toUpperCase()}
                  bg={active ? `${palette.success}22` : `${palette.danger}22`}
                  color={active ? palette.success : palette.danger}
                  bold
                />
              </View>
            </View>
          </Pressable>
        );
      }}
    />
  );
}

function Tile({
  label,
  value,
  bg,
  color,
  bold,
}: {
  label: string;
  value: string;
  bg: string;
  color: string;
  bold?: boolean;
}) {
  return (
    <View style={[styles.tile, { backgroundColor: bg }]}>
      <Mono size={9.5} color={color} letterSpacing={0.5} weight={bold ? '700' : '500'}>
        {label}
      </Mono>
      <Mono size={bold ? 13 : 16} color={color} weight="700" style={{ marginTop: 2 }}>
        {value}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    gap: 12,
  },
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  iconWrap: {
    width: 48,
    height: 48,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  name: { fontSize: 16, fontWeight: '700', letterSpacing: -0.2 },
  addr: { fontSize: 11.5, marginTop: 6, lineHeight: 16 },
  statsRow: { flexDirection: 'row', gap: 8 },
  tile: { flex: 1, padding: 10, borderRadius: 8 },
});
