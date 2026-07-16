/**
 * View Templates — 1:1 with the web admin view-templates page
 * (Pages/admin/view-templates/Index.jsx): the shared DataTable with the web's
 * column set (Name / Description / Lines using) and per-row actions (edit /
 * delete). Full CRUD — "New template" + row actions. Data via REST
 * useViewTemplates.
 */
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteViewTemplate, useViewTemplates } from '@/hooks/queries/useViewTemplates';
import type { ViewTemplate } from '@/api/viewTemplates';

export function ViewTemplatesScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useViewTemplates();
  const del = useDeleteViewTemplate();
  const rows = q.data ?? [];

  const onDelete = (vt: ViewTemplate) =>
    Alert.alert(t('Delete view template?'), vt.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(vt.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <View style={{ flex: 1 }}>
          <Text style={styles.h1}>{t('View Templates')}</Text>
          <Text style={styles.sub}>{t('Line dashboard column presets.')}</Text>
        </View>
        <Button title={t('+ New Template')} size="sm" onPress={() => router.push('/(drawer)/admin/view-templates/new' as never)} />
      </View>

      <DataTable<ViewTemplate>
        data={rows as ViewTemplate[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['name', 'description']}
        emptyText={t('No view templates yet.')}
        onRowPress={(vt) => router.push(`/(drawer)/admin/view-templates/${vt.id}/edit` as never)}
        columns={[
          {
            key: 'name',
            label: t('Name'),
            flex: 1.2,
            render: (vt) => <Text numberOfLines={1} style={styles.name}>{vt.name}</Text>,
          },
          { key: 'description', label: t('Description'), flex: 1.6, render: (vt) => vt.description ?? '—' },
          { key: 'lines', label: t('Lines using'), width: 100, align: 'right', render: (vt) => String(vt.lines_count ?? 0) },
        ]}
        actions={(vt) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/(drawer)/admin/view-templates/${vt.id}/edit` as never) },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(vt) },
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
  sub: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: 2 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
