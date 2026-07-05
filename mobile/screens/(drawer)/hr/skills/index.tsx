/**
 * Skills — 1:1 with the web admin/skills table (Pages/admin/skills/Index.jsx):
 * the shared DataTable (search + Columns menu + pagination) with the web's
 * column set (Code / Name / Description / Workers) and per-row actions
 * (edit / delete).
 */
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { SearchField } from '@openmes/ui/native';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useSkills } from '@/hooks/queries/useHr';
import { useDeleteSkill } from '@/hooks/mutations/hr';
import type { Skill } from '@/api/hr';

export function SkillsList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [search, setSearch] = useState('');
  const q = useSkills(search.trim() || undefined);
  const del = useDeleteSkill();
  const rows = q.data ?? [];

  const confirmDelete = (skill: Skill) => {
    Alert.alert(t('Delete skill'), t('Delete "{{name}}"?', { name: skill.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          del.mutate(skill.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);
  };

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Skills')}</Text>
        <View style={{ flex: 1 }} />
        <Button size="sm" onPress={() => router.push('/hr/skills/new' as never)}>{t('New Skill')}</Button>
      </View>
      <View style={styles.filterBar}>
        <SearchField value={search} onChange={setSearch} placeholder={t('Search name or code')} />
      </View>

      {q.isLoading && !q.data ? (
        <LoadingState />
      ) : q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
          <DataTable<Skill>
            data={rows}
            searchPlaceholder={t('Search…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['code', 'name', 'description']}
            emptyText={t('No skills yet.')}
            onRowPress={(s) => router.push(`/hr/skills/${s.id}` as never)}
            columns={[
              {
                key: 'code',
                label: t('Code'),
                width: 90,
                render: (s) => <Mono size={11} color={colors.muted}>{s.code}</Mono>,
              },
              {
                key: 'name',
                label: t('Name'),
                flex: 1.3,
                render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text>,
              },
              { key: 'description', label: t('Description'), flex: 1.6, render: (s) => s.description ?? '—' },
              { key: 'workers_count', label: t('Workers'), width: 80, render: (s) => String(s.workers_count ?? 0) },
            ]}
            actions={(s) => [
              { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/hr/skills/${s.id}` as never) },
              { label: t('Delete'), icon: 'delete', onPress: () => confirmDelete(s) },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16, paddingBottom: 8 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  filterBar: { paddingHorizontal: 18, paddingBottom: 12 },
  tableWrap: { paddingHorizontal: 18, paddingBottom: 24 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
