/**
 * LOT Sequences — 1:1 with the web admin/lot-sequences table
 * (Pages/admin/lot-sequences/Index.jsx): the shared DataTable with the web's
 * column set (Name / Product Type / Format / Next # / Pad) and per-row actions
 * (Edit / Delete). Format is the composed pattern token. The web "Reset" column
 * has no mobile REST field, so it's omitted. Data via REST useLotSequences.
 */
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useLotSequences, useDeleteLotSequence } from '@/hooks/queries/useLot';
import type { LotSequence } from '@/api/lot';

const patternFor = (s: LotSequence) => {
  const tokens = [
    s.prefix ?? null,
    s.year_prefix ? '{YY}' : null,
    s.pad_size ? `{${'N'.repeat(s.pad_size)}}` : null,
    s.suffix ?? null,
  ].filter(Boolean);
  return tokens.join('-') || '—';
};

export function LotSequencesList() {
  const { t } = useTranslation();
  const router = useRouter();

  const query = useLotSequences();
  const del = useDeleteLotSequence();
  const rows = query.data ?? [];

  const onDelete = (s: LotSequence) =>
    Alert.alert(t('Delete LOT sequence'), t('Delete "{{name}}"?', { name: s.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(s.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  if (query.isLoading && !query.data) return <LoadingState />;
  if (query.isError && !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('LOT Sequences')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Sequence')} size="sm" onPress={() => router.push('/admin/lot-sequences/new' as never)} />
      </View>

      <DataTable<LotSequence>
        data={rows as LotSequence[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['name', 'prefix', 'suffix']}
        emptyText={t('No LOT sequences yet.')}
        onRowPress={(s) => router.push(`/admin/lot-sequences/${s.id}` as never)}
        columns={[
          { key: 'name', label: t('Name'), flex: 1.3, render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text> },
          { key: 'product_type', label: t('Product Type'), flex: 1, render: (s) => s.product_type?.name ?? t('Global') },
          { key: 'format', label: t('Format'), flex: 1.2, render: (s) => <Mono size={11} color={colors.muted}>{patternFor(s)}</Mono> },
          { key: 'next_value', label: t('Next #'), width: 72, render: (s) => <Mono size={11} color={colors.muted}>{s.next_value != null ? String(s.next_value) : '—'}</Mono> },
          { key: 'pad_size', label: t('Pad'), width: 56, render: (s) => <Mono size={11} color={colors.muted}>{s.pad_size != null ? String(s.pad_size) : '—'}</Mono> },
        ]}
        actions={(s) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/lot-sequences/${s.id}` as never) },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(s) },
        ]}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 14 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
