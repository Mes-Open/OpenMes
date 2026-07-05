/**
 * Scrap reasons — 1:1 with the web admin scrap-reasons table
 * (Pages/admin/scrap-reasons/Index.jsx): the shared DataTable with the web's
 * column set (Code / Name / Category / Status) plus per-row actions (Edit /
 * Activate-Deactivate / Delete). Toggling active reuses the update endpoint
 * (no dedicated toggle route). The web's "Used" count has no REST counterpart,
 * so it's omitted. Data via REST useScrapReasons.
 */
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useDeleteScrapReason,
  useScrapReasons,
  useUpdateScrapReason,
} from '@/hooks/queries/useScrapReasons';
import type { ScrapReason } from '@/api/scrapReasons';

export default function ScrapReasonsPage() {
  const { t } = useTranslation();
  const router = useRouter();

  const q = useScrapReasons(true);
  const del = useDeleteScrapReason();
  const update = useUpdateScrapReason();
  const rows = q.data ?? [];

  const categoryLabels: Record<string, string> = {
    material: t('Material'),
    machine: t('Machine'),
    method: t('Method'),
    man: t('Man'),
    environment: t('Environment'),
  };

  const onToggle = (s: ScrapReason) =>
    update.mutate(
      { id: s.id, input: { is_active: !(s.is_active ?? true) } },
      { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );

  const onDelete = (s: ScrapReason) =>
    Alert.alert(t('Delete scrap reason'), t('Delete "{{name}}"?', { name: s.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(s.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Scrap Reasons')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Scrap Reason')} size="sm" onPress={() => router.push('/admin/scrap-reasons/new' as never)} />
      </View>

      <DataTable<ScrapReason>
        data={rows as ScrapReason[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name', 'category']}
        emptyText={t('No scrap reasons yet.')}
        onRowPress={(s) => router.push(`/admin/scrap-reasons/${s.id}` as never)}
        columns={[
          { key: 'code', label: t('Code'), width: 110, render: (s) => <Mono size={11} color={colors.muted}>{s.code}</Mono> },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text>,
          },
          { key: 'category', label: t('Category'), flex: 1, render: (s) => (s.category ? categoryLabels[s.category] ?? s.category : '—') },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (s) => (
              <StatusPill status={s.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={s.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(s) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/scrap-reasons/${s.id}` as never) },
          {
            label: s.is_active === false ? t('Activate') : t('Deactivate'),
            icon: s.is_active === false ? 'activate' : 'deactivate',
            onPress: () => onToggle(s),
          },
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
