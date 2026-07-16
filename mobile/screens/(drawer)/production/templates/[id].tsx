/**
 * Process template detail — mirrors the web process-templates Show page: header
 * (name + active/version), a details box, the ordered Production Steps table
 * (Step / Name / Workstation / Est.), and links into the step + QC editors.
 * Full step editing (add/reorder/photos) lives on the web; "Open on web" jumps
 * there. Activate/deactivate and delete stay here.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { FontAwesome } from '@expo/vector-icons';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useProcessTemplate } from '@/hooks/queries/useProductTypes';
import { useDeleteTemplate, useToggleTemplateActive } from '@/hooks/mutations/productTypes';
import { useSettingsStore } from '@/stores/settingsStore';

export function TemplateDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const query = useProcessTemplate(numericId);
  const deleteMutation = useDeleteTemplate();
  const toggleMutation = useToggleTemplateActive();
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  if (query.isLoading && !query.data) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const template = query.data;
  const steps = (template.steps ?? []).slice().sort((a, b) => a.step_number - b.step_number);

  const onOpenOnWeb = () =>
    WebBrowser.openBrowserAsync(
      `${serverUrl}/admin/product-types/${template.product_type_id}/process-templates/${template.id}/edit`,
    );

  const onDelete = () =>
    Alert.alert(
      t('Delete template'),
      t('Active work orders snapshot the template, so deletion is safe but cannot be undone.'),
      [
        { text: t('Cancel'), style: 'cancel' },
        {
          text: t('Delete'),
          style: 'destructive',
          onPress: () =>
            deleteMutation.mutate(template.id, {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
            }),
        },
      ],
    );

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.head}>
        <View style={{ flex: 1 }}>
          <Text style={styles.h1}>{template.name}</Text>
        </View>
        <StatusPill
          status={template.is_active ? 'in_progress' : 'pending'}
          label={template.is_active ? t('Active') : t('Inactive')}
        />
      </View>

      <View style={styles.box}>
        <KeyValue label={t('Product type')} value={template.product_type?.name ?? '—'} />
        <KeyValue label={t('Version')} value={`v${template.version}`} mono />
        <KeyValue label={t('Steps')} value={String(steps.length)} mono last />
      </View>

      <View>
        <Mono size={9} color={colors.faint} letterSpacing={0.6} style={styles.sectionLabel}>
          {t('Production steps').toUpperCase()}
        </Mono>
        {steps.length === 0 ? (
          <Text style={styles.empty}>{t('No steps defined yet.')}</Text>
        ) : (
          <>
            <View style={[styles.row, styles.headerRow]}>
              <HCell w={44}>{t('Step')}</HCell>
              <HCell flex={1.4}>{t('Name')}</HCell>
              <HCell flex={1}>{t('Workstation')}</HCell>
              <HCell w={60}>{t('Est.')}</HCell>
            </View>
            {steps.map((step) => (
              <View key={step.id} style={[styles.row, styles.dataRow]}>
                <View style={{ width: 44 }}>
                  <Mono size={11} color={colors.accent}>{String(step.step_number)}</Mono>
                </View>
                <View style={{ flex: 1.4 }}>
                  <Text numberOfLines={1} style={styles.title}>{step.name}</Text>
                </View>
                <View style={{ flex: 1 }}>
                  <Text numberOfLines={1} style={styles.cellText}>{step.workstation?.name ?? '—'}</Text>
                </View>
                <View style={{ width: 60 }}>
                  <Mono size={11} color={colors.muted}>
                    {step.estimated_duration_minutes != null ? `${step.estimated_duration_minutes}m` : '—'}
                  </Mono>
                </View>
              </View>
            ))}
          </>
        )}
      </View>

      <View style={styles.box}>
        <LinkRow
          label={t('Edit steps')}
          hint={t('Add, edit, reorder, delete')}
          onPress={() => router.push(`/(drawer)/production/templates/${template.id}/steps` as never)}
        />
        <LinkRow
          label={t('QC templates')}
          hint={t('Quality check requirements per batch')}
          onPress={() => router.push(`/(drawer)/production/templates/${template.id}/qc-templates` as never)}
          last
        />
      </View>

      <Button
        title={t('Open on web to edit')}
        variant="outline"
        leftIcon={<FontAwesome name="external-link" size={13} color={colors.ink} />}
        onPress={onOpenOnWeb}
      />

      <View style={styles.actions}>
        <Button
          title={template.is_active ? t('Deactivate') : t('Activate')}
          variant="ghost"
          loading={toggleMutation.isPending}
          onPress={() =>
            toggleMutation.mutate(template.id, {
              onError: (e: Error) => Alert.alert(t('Could not save'), e.message),
            })
          }
        />
        <View style={{ flex: 1 }} />
        <Button title={t('Delete template')} variant="danger" loading={deleteMutation.isPending} onPress={onDelete} />
      </View>
    </ScrollView>
  );
}

function KeyValue({ label, value, mono, last }: { label: string; value: string; mono?: boolean; last?: boolean }) {
  return (
    <View style={[styles.kvRow, last ? null : styles.kvBorder]}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
      {mono ? (
        <Mono size={12} color={colors.ink}>{value}</Mono>
      ) : (
        <Text numberOfLines={1} style={styles.kvValue}>{value}</Text>
      )}
    </View>
  );
}

function LinkRow({ label, hint, onPress, last }: { label: string; hint: string; onPress: () => void; last?: boolean }) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.linkRow, last ? null : styles.kvBorder, { opacity: pressed ? 0.6 : 1 }]}>
      <View style={{ flex: 1 }}>
        <Text style={styles.title}>{label}</Text>
        <Mono size={9} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 3 }}>{hint.toUpperCase()}</Mono>
      </View>
      <FontAwesome name="chevron-right" size={12} color={colors.faint} />
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
  content: { padding: 18, gap: 18, maxWidth: 640, width: '100%', alignSelf: 'center' },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  sectionLabel: { marginBottom: 8 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md },
  kvRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, paddingVertical: 12, paddingHorizontal: 14 },
  kvBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  kvValue: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink, flexShrink: 1, textAlign: 'right' },
  linkRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 14 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 11, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, paddingVertical: 8 },
});
