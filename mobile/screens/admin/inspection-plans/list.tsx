import { FontAwesome } from '@expo/vector-icons';
import { LegendList } from '@legendapp/list';
import { useRouter } from 'expo-router';
import { Alert, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useDeleteInspectionPlan,
  useInspectionPlans,
} from '@/hooks/queries/useInspections';
import type { InspectionPlan } from '@/api/inspections';

/**
 * Inspection plans — admin templates that define which criteria are
 * measured during an Inspection run. Long-press to delete.
 */
export function InspectionPlansList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const q = useInspectionPlans({});
  const del = useDeleteInspectionPlan();

  const onDelete = (p: InspectionPlan) => {
    Alert.alert(t('Delete plan'), p.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          del.mutate(p.id, {
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);
  };

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        title={t('Inspection plans')}
        subtitle={`ADMIN · QUALITY · ${q.data?.length ?? 0} ${t('PLANS').toUpperCase()}`}
      />
      {q.isLoading ? (
        <LoadingState />
      ) : q.isError ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <LegendList
          data={q.data ?? []}
          keyExtractor={(p: InspectionPlan) => String(p.id)}
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} />}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          ListEmptyComponent={
            <EmptyState
              title={t('No inspection plans yet')}
              subtitle={t('Plans define which characteristics get measured on each inspection.')}
            />
          }
          ListFooterComponent={
            <Pressable
              onPress={() => router.push('/admin/inspection-plans/new' as never)}
              style={({ pressed }) => [
                styles.addBtn,
                { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
              ]}>
              <FontAwesome name="plus" size={12} color={palette.text} />
              <Mono size={11} weight="700" letterSpacing={0.5} color={palette.text}>
                {t('NEW PLAN')}
              </Mono>
            </Pressable>
          }
          renderItem={({ item: p }) => (
            <Pressable
              onPress={() => router.push(`/admin/inspection-plans/${p.id}/edit` as never)}
              onLongPress={() => onDelete(p)}
              style={({ pressed }) => [
                styles.row,
                {
                  backgroundColor: palette.surface,
                  borderColor: palette.border,
                  borderLeftColor: p.is_active ? palette.success : palette.textFaint,
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text style={[styles.name, { color: palette.text }]} numberOfLines={1}>
                  {p.name}
                </Text>
                <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
                  {(p.criteria?.length ?? 0)} {t('CRITERIA').toUpperCase()}
                  {p.material?.name ? `  ·  ${p.material.name}` : ''}
                  {p.materialType?.name ? `  ·  ${p.materialType.name}` : ''}
                </Mono>
              </View>
              <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
            </Pressable>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  list: { padding: 18, paddingBottom: 32 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderLeftWidth: 3,
  },
  name: { fontSize: 14, fontWeight: '600' },
  addBtn: {
    marginTop: 14,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
});
