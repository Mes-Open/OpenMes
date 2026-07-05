/**
 * Pallets — 1:1 with the web admin pallets table (Pages/admin/pallets/Index.jsx):
 * the shared DataTable with the web's core column set (Pallet / Work order / Qty /
 * Status / Quality / Location) and per-row actions (Edit / Delete). A status
 * filter narrows the list; the DataTable's own search covers pallet/order/
 * location. Label printing stays on the web admin. Data via REST usePallets.
 */
import { useMemo, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeletePallet, usePallets } from '@/hooks/queries/usePackaging';
import type { Pallet, PalletStatus } from '@/api/packaging';

const STATUSES: PalletStatus[] = ['open', 'closed', 'shipped'];
const humanize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

const QUALITY_COLOR: Record<string, string> = {
  pass: colors.running,
  fail: colors.blocked,
  pending: colors.faint,
};

export function PalletsScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const [status, setStatus] = useState('');
  const q = usePallets((status || undefined) as PalletStatus | undefined);
  const del = useDeletePallet();
  const rows = q.data ?? [];

  const options = useMemo(
    () => [{ value: '', label: t('All statuses') }, ...STATUSES.map((s) => ({ value: s, label: humanize(s) }))],
    [t],
  );

  const onDelete = (p: Pallet) =>
    Alert.alert(t('Delete pallet?'), p.pallet_no, [
      { text: t('Cancel'), style: 'cancel' },
      { text: t('Delete'), style: 'destructive', onPress: () => del.mutate(p.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }) },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Pallets')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 150 }}>
          <Dropdown value={status} onChange={(v) => setStatus(v as string)} placeholder={t('All statuses')} options={options} />
        </View>
        <Button title={t('New pallet')} size="sm" onPress={() => router.push('/(drawer)/admin/pallets/new' as never)} />
      </View>

      {q.isLoading && !q.data ? (
        <LoadingState />
      ) : q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.content}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
          <DataTable<Pallet>
            data={rows as Pallet[]}
            searchPlaceholder={t('Search…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['pallet_no', 'order_no', 'location']}
            emptyText={t('No pallets.')}
            onRowPress={(p) => router.push(`/(drawer)/admin/pallets/${p.id}/edit` as never)}
            columns={[
              { key: 'pallet_no', label: t('Pallet'), width: 120, render: (p) => <Mono size={11} color={colors.ink}>{p.pallet_no}</Mono> },
              { key: 'order_no', label: t('Work Order'), flex: 1, render: (p) => (p.order_no ? <Mono size={11} color={colors.accent}>{p.order_no}</Mono> : <Text style={styles.dash}>—</Text>) },
              { key: 'qty', label: t('Qty'), width: 64, render: (p) => <Mono size={11} color={colors.muted}>{String(p.qty)}</Mono> },
              { key: 'status', label: t('Status'), width: 96, render: (p) => <StatusPill status={p.status} label={humanize(p.status)} /> },
              { key: 'quality_status', label: t('Quality'), width: 84, render: (p) => <Text style={[styles.quality, { color: QUALITY_COLOR[p.quality_status] ?? colors.faint }]}>{t(humanize(p.quality_status))}</Text> },
              { key: 'location', label: t('Location'), width: 96, render: (p) => p.location ?? '—' },
            ]}
            actions={(p) => [
              { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/(drawer)/admin/pallets/${p.id}/edit` as never) },
              { label: t('Delete'), icon: 'delete', onPress: () => onDelete(p) },
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
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  quality: { fontSize: 11, fontFamily: fonts.sans.native.medium },
  dash: { fontSize: 13, color: colors.faintest },
});
