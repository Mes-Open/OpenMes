/**
 * Material lots — 1:1 with the web admin material-lots table
 * (Pages/admin/material-lots/Index.jsx): the shared DataTable with the web's
 * column set (Lot Number / Material / Avail-Recv / Unit / Expiry / Status).
 * The web Edit / Hold / Release / Delete actions and "+ New Lot" have no mobile
 * REST counterparts, so they're omitted; rows open the lot genealogy detail.
 * Search is over the nested material, so the DataTable's own search box stays
 * off. Data via REST useMaterialLots.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { SearchField } from '@openmes/ui/native';

import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useMaterialLots } from '@/hooks/queries/useMaterialLots';
import type { MaterialLot } from '@/api/materialLots';

const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const trimQty = (v: unknown) => (v == null ? '—' : String(v));

export function MaterialLotsList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [search, setSearch] = useState('');

  const query = useMaterialLots({ per_page: 100 });
  const all = query.data?.data ?? [];

  const rows = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return all;
    return all.filter((l) =>
      `${l.lot_number} ${l.material?.name ?? ''} ${l.material?.code ?? ''} ${l.supplier_lot_no ?? ''}`
        .toLowerCase()
        .includes(q),
    );
  }, [all, search]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Material Lots')}</Text>
      </View>

      <View style={styles.filters}>
        <SearchField value={search} onChange={setSearch} placeholder={t('Search by lot or material')} />
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.content}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <DataTable<MaterialLot>
            data={rows as MaterialLot[]}
            searchable={false}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            emptyText={t('No material lots yet.')}
            onRowPress={(lot) => router.push(`/admin/material-lots/${lot.id}` as never)}
            columns={[
              { key: 'lot_number', label: t('Lot Number'), width: 130, render: (lot) => <Mono size={11} color={colors.ink}>{lot.lot_number}</Mono> },
              { key: 'material', label: t('Material'), flex: 1.4, render: (lot) => <Text numberOfLines={1} style={styles.name}>{lot.material?.name ?? `Material #${lot.material_id}`}</Text> },
              { key: 'qty', label: t('Avail / Recv'), width: 96, render: (lot) => <Mono size={11} color={colors.muted}>{`${trimQty(lot.quantity_available)} / ${trimQty(lot.quantity_received)}`}</Mono> },
              { key: 'unit_of_measure', label: t('Unit'), width: 56, render: (lot) => lot.unit_of_measure },
              { key: 'expiry_date', label: t('Expiry'), width: 96, render: (lot) => <Mono size={10} color={colors.muted}>{lot.expiry_date ? String(lot.expiry_date).slice(0, 10) : '—'}</Mono> },
              { key: 'status', label: t('Status'), width: 104, render: (lot) => <StatusPill status={lot.status} label={humanize(String(lot.status ?? 'available'))} /> },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { paddingHorizontal: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  filters: { paddingHorizontal: 18, paddingVertical: 12 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
