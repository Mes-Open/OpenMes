/**
 * Personnel classes (ISA-95) — 1:1 with the web admin personnel-classes page
 * (Pages/admin/personnel-classes/Index.jsx): the shared DataTable (search +
 * Columns menu + pagination) with the web's column set (Code / Name /
 * Req. Skills / Workers / Status) and per-row actions (edit / delete).
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
import { usePersonnelClasses, useDeletePersonnelClass } from '@/hooks/queries/usePersonnel';
import type { PersonnelClass } from '@/api/personnel';

export function PersonnelClassesList() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = usePersonnelClasses({ per_page: 100 });
  const del = useDeletePersonnelClass();
  const rows = q.data?.data ?? [];

  const onDelete = (cls: PersonnelClass) =>
    Alert.alert(t('Delete personnel class'), t('Delete "{{name}}"?', { name: cls.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          del.mutate({ id: cls.id }, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Personnel Classes')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Class')} size="sm" onPress={() => router.push('/hr/personnel-classes/new' as never)} />
      </View>

      <DataTable<PersonnelClass>
        data={rows as unknown as PersonnelClass[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name']}
        emptyText={t('No personnel classes yet.')}
        onRowPress={(c) => router.push(`/hr/personnel-classes/${c.id}/edit` as never)}
        columns={[
          {
            key: 'code',
            label: t('Code'),
            width: 90,
            render: (c) => <Mono size={11} color={colors.muted}>{c.code}</Mono>,
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (c) => <Text numberOfLines={1} style={styles.name}>{c.name}</Text>,
          },
          {
            key: 'skills',
            label: t('Req. Skills'),
            width: 90,
            render: (c) => <Mono size={11} color={colors.muted}>{String(c.required_skill_ids?.length ?? 0)}</Mono>,
          },
          { key: 'workers', label: t('Workers'), width: 80, render: (c) => String(c.workers_count ?? 0) },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (c) => (
              <StatusPill status={c.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={t(c.is_active ? 'Active' : 'Inactive')} />
            ),
          },
        ]}
        actions={(c) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/hr/personnel-classes/${c.id}/edit` as never) },
          { label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(c) },
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
