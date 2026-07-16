/**
 * Companies — 1:1 with the web admin/companies table (Pages/admin/companies/Index.jsx):
 * the shared DataTable (search + Columns menu + pagination) with the web's column
 * set (Code / Name / Type / Email / Status) and per-row actions (Edit /
 * toggle-active / Delete). Data via REST useCompanies (inactive included).
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
import { useCompanies, useDeleteCompany, useToggleCompanyActive } from '@/hooks/queries/useOps';
import type { Company } from '@/api/ops';

const humanize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

export function CompaniesList() {
  const { t } = useTranslation();
  const router = useRouter();

  const query = useCompanies({ include_inactive: true });
  const toggle = useToggleCompanyActive();
  const del = useDeleteCompany();
  const rows = query.data ?? [];

  const onDelete = (c: Company) =>
    Alert.alert(t('Delete company'), t('Delete "{{name}}"?', { name: c.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(c.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Companies')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Company')} size="sm" onPress={() => router.push('/admin/companies/new' as never)} />
      </View>

      <DataTable<Company>
        data={rows as Company[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['code', 'name', 'email']}
        emptyText={t('No companies yet.')}
        onRowPress={(c) => router.push(`/admin/companies/${c.id}` as never)}
        columns={[
          { key: 'code', label: t('Code'), width: 96, render: (c) => <Mono size={11} color={colors.muted}>{c.code}</Mono> },
          { key: 'name', label: t('Name'), flex: 1.4, render: (c) => <Text numberOfLines={1} style={styles.name}>{c.name}</Text> },
          { key: 'type', label: t('Type'), width: 84, render: (c) => t(humanize(c.type)) },
          { key: 'email', label: t('Email'), flex: 1.2, render: (c) => c.email ?? '—' },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (c) => (
              <StatusPill status={c.is_active === false ? 'CANCELLED' : 'IN_PROGRESS'} label={c.is_active === false ? t('Inactive') : t('Active')} />
            ),
          },
        ]}
        actions={(c) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/companies/${c.id}` as never) },
          {
            label: c.is_active === false ? t('Activate') : t('Deactivate'),
            icon: c.is_active === false ? 'activate' : 'deactivate',
            onPress: () => toggle.mutate(c.id, { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) }),
          },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(c) },
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
