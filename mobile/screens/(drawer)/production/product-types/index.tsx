/**
 * Product Types — 1:1 with the web admin/product-types card grid
 * (Pages/admin/product-types/Index.jsx): per-card name + active pill, unit,
 * description, a Templates / Work Orders stats box, an active toggle, and a
 * "View Details" tap into the detail screen. Data via REST useProductTypes.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { StatusPill, colors, fonts, radius } from '@openmes/ui';
import { SearchField } from '@openmes/ui/native';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { toggleProductTypeActive, type ProductTypeWithCount } from '@/api/productTypes';
import { useProductTypes } from '@/hooks/queries/useProductTypes';

export function ProductTypesList() {
  const { t } = useTranslation();
  const router = useRouter();
  const qc = useQueryClient();
  const [search, setSearch] = useState('');

  const query = useProductTypes({ include_inactive: true });
  const all = query.data ?? [];

  const toggle = useMutation({
    mutationFn: (id: number) => toggleProductTypeActive(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['product-types'] }),
  });

  const list = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return all;
    return all.filter((p) => `${p.code ?? ''} ${p.name}`.toLowerCase().includes(q));
  }, [all, search]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Product Types')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 180 }}>
          <Button title="+ Add Product Type" variant="accent" size="sm" onPress={() => router.push('/production/product-types/new' as never)} />
        </View>
      </View>

      <View style={styles.filters}>
        <SearchField value={search} onChange={setSearch} placeholder={t('Search by code or name')} />
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.grid}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          {list.map((pt) => (
            <PtCard
              key={pt.id}
              pt={pt}
              onPress={() => router.push(`/production/product-types/${pt.id}` as never)}
              onToggle={() => toggle.mutate(pt.id)}
            />
          ))}
          {list.length === 0 ? <Text style={styles.empty}>{t('No product types')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function PtCard({ pt, onPress, onToggle }: { pt: ProductTypeWithCount; onPress: () => void; onToggle: () => void }) {
  const { t } = useTranslation();
  const active = pt.is_active !== false;
  return (
    <View style={styles.card}>
      <View style={styles.cardTop}>
        <Text numberOfLines={1} style={styles.cardName}>{pt.name}</Text>
        <StatusPill status={active ? 'running' : 'downtime'} label={active ? t('Active') : t('Inactive')} />
      </View>
      {pt.unit_of_measure ? <Text style={styles.unit}>{`${t('Unit:')} ${pt.unit_of_measure}`}</Text> : null}
      {pt.description ? <Text numberOfLines={2} style={styles.desc}>{pt.description}</Text> : null}

      <View style={styles.statsBox}>
        <View style={styles.statCol}>
          <Mono size={20} color={colors.ink}>{String(pt.process_templates_count ?? 0)}</Mono>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Templates').toUpperCase()}</Mono>
        </View>
        <View style={styles.statCol}>
          <Mono size={20} color={colors.ink}>{String(pt.work_orders_count ?? 0)}</Mono>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Work Orders').toUpperCase()}</Mono>
        </View>
      </View>

      <View style={styles.cardFoot}>
        <View style={styles.toggleRow}>
          <Switch value={active} onValueChange={onToggle} />
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{(active ? t('Active') : t('Inactive')).toUpperCase()}</Mono>
        </View>
        <Pressable onPress={onPress} hitSlop={6}>
          <Text style={styles.viewLink}>{t('View Details')} →</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  filters: { paddingHorizontal: 18, paddingVertical: 12 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 14, padding: 16 },

  card: { flexGrow: 1, flexBasis: '30%', minWidth: 260, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 16 },
  cardTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, marginBottom: 4 },
  cardName: { flex: 1, fontSize: 15, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  unit: { fontSize: 12, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: 2 },
  desc: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: 8 },

  statsBox: { flexDirection: 'row', backgroundColor: colors.bg, borderRadius: radius.sm, padding: 12, marginTop: 12, marginBottom: 12 },
  statCol: { flex: 1, alignItems: 'center', gap: 2 },

  cardFoot: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line, paddingTop: 12 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  viewLink: { fontSize: 12.5, color: colors.accent, fontFamily: fonts.sans.native.medium },

  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
