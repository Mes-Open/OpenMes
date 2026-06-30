import { FontAwesome } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Alert, StyleSheet, Text, View } from 'react-native';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { DangerZone, DetailHero, DetailScreen, LinkRowCard } from '@/components/ui/Detail';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useProcessTemplate } from '@/hooks/queries/useProductTypes';
import { useDeleteTemplate, useToggleTemplateActive } from '@/hooks/mutations/productTypes';
import { useSettingsStore } from '@/stores/settingsStore';

export function TemplateDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = useProcessTemplate(numericId);
  const deleteMutation = useDeleteTemplate();
  const toggleMutation = useToggleTemplateActive();
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const template = query.data;
  const steps = template.steps ?? [];

  const onOpenOnWeb = () => {
    const productTypeId = template.product_type_id;
    WebBrowser.openBrowserAsync(
      `${serverUrl}/admin/product-types/${productTypeId}/process-templates/${template.id}/edit`,
    );
  };

  return (
    <DetailScreen>
      <DetailHero
        eyebrow={template.product_type ? `FOR ${template.product_type.name}` : undefined}
        title={template.name}
        trailing={
          <View style={[styles.versionPill, { borderColor: palette.border, backgroundColor: palette.surfaceAlt }]}>
            <Mono size={11} color={palette.text} weight="700" letterSpacing={0.4}>
              v{template.version}
            </Mono>
          </View>
        }
      />

      <View style={[styles.notice, { backgroundColor: '#FAF0DD', borderColor: BRAND.amber }]}>
        <FontAwesome name="info-circle" size={14} color="#8a5a0e" />
        <Text style={{ color: '#8a5a0e', fontSize: 12, flex: 1, lineHeight: 17 }}>
          Editing template steps (add, edit, reorder) is best done on the web app. Mobile supports
          quick edits.
        </Text>
      </View>

      <Card style={{ gap: 10 }}>
        <SectionLabel
          right={
            <StatusPill
              status={template.is_active ? 'IN_PROGRESS' : 'CANCELLED'}
              label={template.is_active ? 'Active' : 'Inactive'}
            />
          }>
          {`Steps · ${steps.length}`}
        </SectionLabel>
        {steps.length === 0 ? (
          <Mono size={11} color={palette.textFaint}>NO STEPS DEFINED YET</Mono>
        ) : (
          <View style={{ gap: 8 }}>
            {steps.map((step, idx) => (
              <View
                key={step.id}
                style={[
                  styles.stepRow,
                  idx > 0 ? { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: palette.border } : null,
                ]}>
                <Text style={[styles.stepNumber, { color: BRAND.amber, fontFamily: MONO }]}>
                  {step.step_number}
                </Text>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.stepName, { color: palette.text }]}>{step.name}</Text>
                  {step.workstation?.name ? (
                    <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
                      WS · {step.workstation.name.toUpperCase()}
                    </Mono>
                  ) : null}
                  {step.estimated_duration_minutes != null ? (
                    <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
                      EST {step.estimated_duration_minutes}M
                    </Mono>
                  ) : null}
                  {step.instruction ? (
                    <Text
                      style={{ color: palette.textMuted, fontSize: 13, marginTop: 6, lineHeight: 19 }}
                      numberOfLines={3}>
                      {step.instruction}
                    </Text>
                  ) : null}
                </View>
              </View>
            ))}
          </View>
        )}
      </Card>

      <LinkRowCard
        icon="list-ol"
        title="Edit steps"
        subtitle="Add, edit, reorder, delete"
        onPress={() => router.push(`/(drawer)/production/templates/${template.id}/steps` as never)}
      />

      <LinkRowCard
        icon="check-square-o"
        title="QC templates"
        subtitle="Quality check requirements per batch"
        onPress={() =>
          router.push(`/(drawer)/production/templates/${template.id}/qc-templates` as never)
        }
      />

      <Button
        title="Open on web to edit"
        variant="outline"
        leftIcon={<FontAwesome name="external-link" size={13} color={palette.text} />}
        onPress={onOpenOnWeb}
      />

      <DangerZone
        toggleLabel={template.is_active ? 'Deactivate' : 'Activate'}
        toggleLoading={toggleMutation.isPending}
        onToggle={() =>
          toggleMutation.mutate(template.id, {
            onError: (e: Error) => Alert.alert('Failed', e.message),
          })
        }
        deleteLabel="Delete template"
        deleteConfirmTitle="Delete template"
        deleteConfirmMessage="Active work orders snapshot the template, so deletion is safe but cannot be undone."
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(template.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}

const styles = StyleSheet.create({
  versionPill: {
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 4,
    borderWidth: 1,
  },
  notice: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  stepRow: { flexDirection: 'row', gap: 12, paddingTop: 8 },
  stepNumber: { fontSize: 16, fontWeight: '700', width: 22, letterSpacing: 0.4 },
  stepName: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
});
