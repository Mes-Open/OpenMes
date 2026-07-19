/**
 * Inspection plans — mirrors the web admin inspection-plans page
 * (Pages/admin/inspection-plans/Index.jsx) via the shared DataTable: the web's
 * column set (Name / Scope / Criteria / Status) with per-row actions (edit /
 * delete). The web's Version column and Publish action are omitted — the mobile
 * InspectionPlan payload has no version/published_at and there is no publish
 * hook yet. "+ New Plan"; tap a row to edit. Data via REST useInspectionPlans.
 */
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteInspectionPlan, useInspectionPlans } from '@/hooks/queries/useInspections';
import type { InspectionPlan } from '@/api/inspections';

const PLAN_TONE: Record<string, { fg: string; bg: string }> = {
  draft: { fg: colors.downtime, bg: colors.downtimeBg },
  published: { fg: colors.running, bg: colors.runningBg },
};

export function InspectionPlansList() {
  const { t } = useTranslation();
  const router = useRouter();

  const q = useInspectionPlans({});
  const del = useDeleteInspectionPlan();
  const rows = q.data ?? [];

  const onDelete = (p: InspectionPlan) =>
    Alert.alert(t('Delete plan'), p.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(p.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  const scopeOf = (p: InspectionPlan) =>
    p.material?.name
      ? `${t('Material')}: ${p.material.name}`
      : p.materialType?.name
        ? `${t('Type')}: ${p.materialType.name}`
        : t('Generic');

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Inspection Plans')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Plan')} size="sm" onPress={() => router.push('/admin/inspection-plans/new' as never)} />
      </View>

      <DataTable<InspectionPlan>
        data={rows as InspectionPlan[]}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['name']}
        emptyText={t('No inspection plans yet.')}
        onRowPress={(p) => router.push(`/admin/inspection-plans/${p.id}/edit` as never)}
        columns={[
          {
            key: 'name',
            label: t('Name'),
            flex: 1.4,
            render: (p) => <Text numberOfLines={1} style={styles.name}>{p.name}</Text>,
          },
          { key: 'scope', label: t('Scope'), flex: 1.2, render: scopeOf },
          { key: 'criteria', label: t('Criteria'), width: 80, align: 'right', render: (p) => String(p.criteria?.length ?? 0) },
          {
            key: 'status',
            label: t('Status'),
            width: 100,
            render: (p) => {
              const key = p.is_active ? 'published' : 'draft';
              const tone = PLAN_TONE[key];
              return (
                <View style={[styles.pill, { backgroundColor: tone.bg }]}>
                  <Mono size={9} color={tone.fg} letterSpacing={0.5}>{(p.is_active ? t('Published') : t('Draft')).toUpperCase()}</Mono>
                </View>
              );
            },
          },
        ]}
        actions={(p) => [
          { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/inspection-plans/${p.id}/edit` as never) },
          { label: t('Delete'), icon: 'delete', onPress: () => onDelete(p) },
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
  pill: { alignSelf: 'flex-start', paddingVertical: 2, paddingHorizontal: 6, borderRadius: 3 },
});
