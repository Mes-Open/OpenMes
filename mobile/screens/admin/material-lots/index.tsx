import { format, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useMaterialLots } from '@/hooks/queries/useMaterialLots';
import type { MaterialLot, MaterialLotStatus } from '@/api/materialLots';

type FilterId = 'all' | 'available' | 'low' | 'quarantined' | 'expired';

const STATE_COLOR: Record<string, string> = {
  available: '#1f9d6c',
  pending_inspection: BRAND.amber,
  quarantined: '#7c3aed',
  consumed: '#8a8780',
  scrapped: '#dc2626',
  expired: '#dc2626',
  low: BRAND.amber,
};

function classifyLot(lot: MaterialLot): FilterId {
  const status = (lot.status as MaterialLotStatus) ?? 'available';
  if (status === 'expired' || status === 'scrapped') return 'expired';
  if (status === 'quarantined' || status === 'pending_inspection') return 'quarantined';
  // Heuristic for "low" — qty_available < 10 (or 10% of received). The backend
  // doesn't have a low flag so we infer it here for the badge.
  const avail = Number(lot.quantity_available ?? 0);
  const received = Number(lot.quantity_received ?? 0);
  if (avail > 0 && (avail < 10 || (received > 0 && avail / received < 0.15))) {
    return 'low';
  }
  return 'available';
}

export function MaterialLotsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [filter, setFilter] = useState<FilterId>('all');
  const [search, setSearch] = useState('');

  const query = useMaterialLots({ per_page: 100 });
  const all = query.data?.data ?? [];

  const counts = useMemo(() => {
    const c = { all: all.length, available: 0, low: 0, quarantined: 0, expired: 0 };
    for (const l of all) {
      const cls = classifyLot(l);
      c[cls] += 1;
    }
    return c;
  }, [all]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return all.filter((l) => {
      if (filter !== 'all' && classifyLot(l) !== filter) return false;
      if (q) {
        const blob = `${l.lot_number} ${l.material?.name ?? ''} ${l.material?.code ?? ''} ${l.supplier_lot_no ?? ''}`.toLowerCase();
        if (!blob.includes(q)) return false;
      }
      return true;
    });
  }, [all, filter, search]);

  return (
    <ListScreen
      title={t('Material lots')}
      eyebrow={`${all.length} ${t('LOTS').toUpperCase()} · ${counts.low} ${t('LOW').toUpperCase()} · ${counts.expired} ${t('EXPIRED').toUpperCase()}`}
      filters={[
        { id: 'all', label: t('All'), count: counts.all },
        { id: 'available', label: t('Available'), count: counts.available },
        { id: 'low', label: t('Low'), count: counts.low },
        { id: 'quarantined', label: t('Quarantine'), count: counts.quarantined },
        { id: 'expired', label: t('Expired'), count: counts.expired },
      ]}
      activeFilter={filter}
      onFilterChange={(id) => setFilter(id as FilterId)}
      extraHeader={
        <SearchBar
          placeholder="Search by lot or material"
          value={search}
          onChangeText={setSearch}
        />
      }
      items={filtered}
      keyExtractor={(l) => String(l.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No material lots')}
      renderItem={(lot) => {
        const cls = classifyLot(lot);
        const railColor = STATE_COLOR[cls] ?? palette.textMuted;
        const dim = cls === 'expired';
        const expFmt = (() => {
          if (!lot.expiry_date) return null;
          try {
            return format(parseISO(lot.expiry_date), 'yyyy-MM-dd');
          } catch {
            return null;
          }
        })();
        return (
          <Pressable
            onPress={() =>
              router.push(`/admin/material-lots/${lot.id}` as never)
            }
            style={({ pressed }) => [
              styles.row,
              {
                backgroundColor: palette.surface,
                borderColor: palette.border,
                opacity: dim ? 0.6 : pressed ? 0.9 : 1,
              },
            ]}>
            <View style={[styles.rail, { backgroundColor: railColor }]} />
            <View style={{ flex: 1, minWidth: 0 }}>
              <Mono size={11.5} color={palette.text} weight="700" letterSpacing={0.3}>
                {lot.lot_number}
              </Mono>
              <Text
                style={[styles.matName, { color: palette.textMuted }]}
                numberOfLines={1}>
                {lot.material?.name ?? `Material #${lot.material_id}`}
              </Text>
              {expFmt || (lot.sublots?.length ?? 0) > 0 ? (
                <Mono
                  size={10}
                  color={palette.textFaint}
                  letterSpacing={0.3}
                  style={{ marginTop: 4 }}>
                  {expFmt ? `EXP ${expFmt}` : ''}
                  {expFmt && lot.sublots?.length ? ' · ' : ''}
                  {lot.sublots?.length ? `${lot.sublots.length} SUBLOTS` : ''}
                </Mono>
              ) : null}
            </View>
            <View style={{ alignItems: 'flex-end' }}>
              <Text
                style={[
                  styles.qty,
                  {
                    color: dim ? palette.danger : palette.text,
                    fontFamily: MONO,
                  },
                ]}>
                {lot.quantity_available}
              </Text>
              <Mono size={9} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 2 }}>
                {lot.unit_of_measure.toUpperCase()}
              </Mono>
            </View>
          </Pressable>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
  },
  rail: { width: 6, alignSelf: 'stretch', borderRadius: 3 },
  matName: { fontSize: 12.5, marginTop: 4 },
  qty: { fontSize: 16, fontWeight: '700', letterSpacing: -0.3 },
});
