/**
 * Label Templates — print layouts for WO / finished-goods / step / pallet labels,
 * mirroring the web admin screen (Name / Type / Size / Barcode / Default / Active).
 * Full CRUD — "New template" + tap a row to edit, long-press to delete.
 */
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { FontAwesome } from '@expo/vector-icons';

import { colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteLabelTemplate, useLabelTemplates } from '@/hooks/queries/usePackaging';
import type { LabelTemplate } from '@/api/packaging';

export function LabelTemplatesScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useLabelTemplates();
  const del = useDeleteLabelTemplate();
  const rows = q.data ?? [];

  const onDelete = (tpl: LabelTemplate) =>
    Alert.alert(t('Delete label template?'), tpl.name, [
      { text: t('Cancel'), style: 'cancel' },
      { text: t('Delete'), style: 'destructive', onPress: () => del.mutate(tpl.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }) },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Label Templates')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('New template')} size="sm" onPress={() => router.push('/(drawer)/pakowanie/label-templates/new' as never)} />
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
          <View style={[styles.row, styles.headerRow]}>
            <HCell w={24}> </HCell>
            <HCell flex={1.4}>{t('Name')}</HCell>
            <HCell flex={1}>{t('Type')}</HCell>
            <HCell w={84}>{t('Size')}</HCell>
            <HCell w={84}>{t('Barcode')}</HCell>
            <HCell w={80}>{t('Status')}</HCell>
          </View>
          {rows.map((tpl) => (
            <TemplateRow
              key={tpl.id}
              template={tpl}
              onPress={() => router.push(`/(drawer)/pakowanie/label-templates/${tpl.id}/edit` as never)}
              onLongPress={() => onDelete(tpl)}
            />
          ))}
          {rows.length === 0 ? <Text style={styles.empty}>{t('No label templates.')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function TemplateRow({ template, onPress, onLongPress }: { template: LabelTemplate; onPress: () => void; onLongPress: () => void }) {
  const { t } = useTranslation();
  return (
    <Pressable onPress={onPress} onLongPress={onLongPress} style={({ pressed }) => [styles.row, styles.dataRow, { opacity: pressed ? 0.6 : 1 }]}>
      <View style={{ width: 24, alignItems: 'center' }}>
        {template.is_default ? <FontAwesome name="star" size={12} color={colors.accent} /> : null}
      </View>
      <View style={{ flex: 1.4 }}>
        <Text numberOfLines={1} style={styles.name}>{template.name}</Text>
        <Mono size={8} color={colors.faint}>{`${template.fields_count} ${t('fields').toUpperCase()}`}</Mono>
      </View>
      <Cell flex={1}>{template.type_label}</Cell>
      <View style={{ width: 84 }}>
        <Mono size={10} color={colors.muted}>{template.size}</Mono>
      </View>
      <View style={{ width: 84 }}>
        <Mono size={10} color={colors.muted}>{template.barcode_format.toUpperCase()}</Mono>
      </View>
      <View style={{ width: 80 }}>
        <StatusPill status={template.is_active ? 'running' : 'cancelled'} label={template.is_active ? t('Active') : t('Inactive')} />
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

function Cell({ children, flex }: { children: React.ReactNode; flex?: number }) {
  return (
    <View style={{ flex }}>
      <Text numberOfLines={1} style={styles.cellText}>{children}</Text>
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
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
