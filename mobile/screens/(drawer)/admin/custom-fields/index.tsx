/**
 * Custom Fields — 1:1 with the web admin custom-fields page
 * (Pages/admin/custom-fields/Index.jsx): the shared DataTable with the web's
 * column set (Entity / Key / Label / Type / Required / Position / Status) and
 * per-row actions (edit / toggle-active / delete). Full CRUD — "New custom
 * field" + row actions. Data via REST useCustomFields.
 */
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCustomFields, useDeleteCustomField, useToggleCustomField } from '@/hooks/queries/useAdminConfig';
import type { CustomField } from '@/api/adminConfig';

export function CustomFieldsScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useCustomFields();
  const toggle = useToggleCustomField();
  const del = useDeleteCustomField();
  const rows = q.data ?? [];

  const onDelete = (f: CustomField) =>
    Alert.alert(t('Delete custom field?'), `${f.entity_label} · ${f.label}`, [
      { text: t('Cancel'), style: 'cancel' },
      { text: t('Delete'), style: 'destructive', onPress: () => del.mutate(f.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }) },
    ]);

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Custom Fields')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Custom Field')} size="sm" onPress={() => router.push('/(drawer)/admin/custom-fields/new' as never)} />
      </View>

      <DataTable<CustomField>
        data={rows as CustomField[]}
        searchPlaceholder={t('Search custom fields…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['entity_label', 'key', 'label', 'type_label']}
        emptyText={t('No custom fields yet.')}
        onRowPress={(f) => router.push(`/(drawer)/admin/custom-fields/${f.id}/edit` as never)}
        columns={[
          { key: 'entity', label: t('Entity'), width: 120, render: (f) => <Mono size={10} color={colors.muted}>{f.entity_label}</Mono> },
          { key: 'key', label: t('Key'), width: 120, render: (f) => <Mono size={10} color={colors.muted}>{f.key}</Mono> },
          {
            key: 'label',
            label: t('Label'),
            flex: 1.3,
            render: (f) => <Text numberOfLines={1} style={styles.name}>{f.label}</Text>,
          },
          {
            key: 'type',
            label: t('Type'),
            width: 110,
            render: (f) => `${f.type_label}${f.options_count > 0 ? ` (${f.options_count})` : ''}`,
          },
          { key: 'required', label: t('Required'), width: 80, align: 'right', render: (f) => (f.required ? t('Yes') : '—') },
          { key: 'position', label: t('Position'), width: 80, align: 'right', render: (f) => String(f.position) },
          {
            key: 'status',
            label: t('Status'),
            width: 80,
            align: 'right',
            render: (f) => (
              <StatusPill status={f.is_active ? 'running' : 'cancelled'} label={f.is_active ? t('Active') : t('Inactive')} />
            ),
          },
        ]}
        actions={(f) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/(drawer)/admin/custom-fields/${f.id}/edit` as never) },
          {
            label: f.is_active ? t('Deactivate') : t('Activate'),
            icon: f.is_active ? 'deactivate' : 'activate',
            onPress: () => toggle.mutate(f.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(f) },
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
