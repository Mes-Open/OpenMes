/**
 * Trash — 1:1 with the web admin Trash (Pages/admin/trash/Index.jsx): the
 * shared DataTable with the web's column set (Type / Item / Deleted by /
 * Deleted at) and a per-row Restore action (restore cascades to children
 * deleted with it). Keeps the entity-type filter beside the table. Data via
 * REST useTrash.
 */
import { useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useRestoreTrash, useTrash } from '@/hooks/queries/useTrash';
import type { TrashItem } from '@/api/trash';

const humanize = (type: string) =>
  type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function AdminTrashScreen() {
  const { t } = useTranslation();
  const [type, setType] = useState('');
  const q = useTrash(type || undefined);
  const restore = useRestoreTrash();

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const data = q.data!;

  const typeOptions = [
    { value: '', label: t('All types') },
    ...Object.entries(data.counts).map(([k, n]) => ({ value: k, label: `${humanize(k)} (${n})` })),
  ];

  const onRestore = (item: TrashItem) =>
    Alert.alert(t('Restore this item?'), t('Restore "{{label}}"? Related records deleted with it are restored too.', { label: item.label }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Restore'),
        onPress: () =>
          restore.mutate(
            { type: item.type, id: item.id },
            { onError: (e: Error) => Alert.alert(t('Could not restore'), e.message) },
          ),
      },
    ]);

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <View style={{ flex: 1 }}>
          <Text style={styles.h1}>{t('Trash')}</Text>
          <Text style={styles.sub}>{t('Deleted items are kept here and can be restored. Restoring also brings back records deleted together with the item.')}</Text>
        </View>
        <View style={{ width: 200 }}>
          <Dropdown value={type} onChange={(v) => setType(v as string)} placeholder={t('All types')} options={typeOptions} />
        </View>
      </View>

      <DataTable<TrashItem>
        data={data.items}
        getKey={(item) => `${item.type}-${item.id}`}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['label', 'type', 'deleted_by']}
        emptyText={t('Trash is empty.')}
        pageSize={15}
        columns={[
          { key: 'type', label: t('Type'), width: 130, render: (item) => humanize(item.type) },
          {
            key: 'item',
            label: t('Item'),
            flex: 1.4,
            render: (item) => <Mono size={12} color={colors.ink}>{item.label}</Mono>,
          },
          { key: 'deleted_by', label: t('Deleted by'), flex: 1, render: (item) => item.deleted_by ?? '—' },
          {
            key: 'deleted_at',
            label: t('Deleted at'),
            width: 160,
            render: (item) => (item.deleted_at ? new Date(item.deleted_at).toLocaleString() : '—'),
          },
        ]}
        actions={(item) => [
          { label: t('Restore'), variant: 'secondary', onPress: () => onRestore(item) },
        ]}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: 12, marginBottom: 14 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  sub: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: 2, maxWidth: 420 },
});
