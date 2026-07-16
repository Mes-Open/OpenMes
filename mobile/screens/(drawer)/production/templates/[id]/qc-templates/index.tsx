/**
 * QC templates for a process template — the quality-check requirements applied
 * to batches running this template. Table mirrors the web (Name / Params /
 * Samples / Min per batch); "New" opens the create form, a row opens its detail.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useQcTemplatesForProcessTemplate } from '@/hooks/queries/useProductionControls';

export function QcTemplatesList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const processTemplateId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const query = useQcTemplatesForProcessTemplate(processTemplateId);
  const items = query.data ?? [];

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('QC templates')}</Text>
        <View style={{ flex: 1 }} />
        <Button
          title={t('New')}
          size="sm"
          onPress={() => router.push(`/production/templates/${processTemplateId}/qc-templates/new` as never)}
        />
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <View style={[styles.row, styles.headerRow]}>
            <HCell flex={1}>{t('Name')}</HCell>
            <HCell w={72}>{t('Params')}</HCell>
            <HCell w={88}>{t('Samples')}</HCell>
            <HCell w={88}>{t('Min/Batch')}</HCell>
          </View>
          {items.map((item) => (
            <Pressable
              key={item.id}
              onPress={() => router.push(`/production/templates/${processTemplateId}/qc-templates/${item.id}` as never)}
              style={({ pressed }) => [styles.row, styles.dataRow, { opacity: pressed ? 0.6 : 1 }]}>
              <View style={{ flex: 1 }}>
                <Text numberOfLines={1} style={styles.title}>{item.name}</Text>
              </View>
              <View style={{ width: 72 }}>
                <Mono size={11} color={colors.muted}>{String(item.parameters.length)}</Mono>
              </View>
              <View style={{ width: 88 }}>
                <Mono size={11} color={colors.muted}>{item.samples_per_check ? String(item.samples_per_check) : '—'}</Mono>
              </View>
              <View style={{ width: 88 }}>
                <Mono size={11} color={colors.muted}>{item.min_checks_per_batch ? String(item.min_checks_per_batch) : '—'}</Mono>
              </View>
            </Pressable>
          ))}
          {items.length === 0 ? <Text style={styles.empty}>{t('No QC templates.')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{String(children).toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
