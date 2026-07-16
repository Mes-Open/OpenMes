/**
 * Process templates for a product type — mirrors the web process-templates
 * Index (Name / Version / Steps / Status). Read-only list: the drag-and-drop
 * step builder lives on the web; tapping a row opens the template detail.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useProcessTemplatesForProductType } from '@/hooks/queries/useProductTypes';

export function TemplatesList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productTypeId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();
  const [scope, setScope] = useState('all');
  const includeInactive = scope === 'all';

  const query = useProcessTemplatesForProductType(productTypeId, includeInactive);
  const items = query.data ?? [];

  const options = useMemo(
    () => [
      { value: 'all', label: t('All templates') },
      { value: 'active', label: t('Active only') },
    ],
    [t],
  );

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Process templates')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 160 }}>
          <Dropdown value={scope} onChange={(v) => setScope(v as string)} options={options} />
        </View>
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
            <HCell w={64}>{t('Version')}</HCell>
            <HCell w={64}>{t('Steps')}</HCell>
            <HCell w={96}>{t('Status')}</HCell>
          </View>
          {items.map((item) => (
            <TemplateRow
              key={item.id}
              template={item}
              onPress={() => router.push(`/production/templates/${item.id}` as never)}
            />
          ))}
          {items.length === 0 ? <Text style={styles.empty}>{t('No process templates.')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function TemplateRow({ template, onPress }: { template: any; onPress: () => void }) {
  const { t } = useTranslation();
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, styles.dataRow, { opacity: pressed ? 0.6 : 1 }]}>
      <View style={{ flex: 1 }}>
        <Text numberOfLines={1} style={styles.title}>{template.name}</Text>
      </View>
      <View style={{ width: 64 }}>
        <Mono size={11} color={colors.accent}>v{template.version}</Mono>
      </View>
      <View style={{ width: 64 }}>
        <Mono size={11} color={colors.muted}>{String(template.steps?.length ?? 0)}</Mono>
      </View>
      <View style={{ width: 96 }}>
        <StatusPill status={template.is_active ? 'in_progress' : 'pending'} label={template.is_active ? t('Active') : t('Inactive')} />
      </View>
    </Pressable>
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
