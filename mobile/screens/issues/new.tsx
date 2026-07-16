import { zodResolver } from '@hookform/resolvers/zod';
import { useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { ControlledField } from '@/components/ui/ControlledField';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { LoadingState } from '@/components/ui/StateViews';
import { useIssueTypes } from '@/hooks/queries/useIssues';
import { useCreateIssue } from '@/hooks/mutations/issues';
import { useAuthStore } from '@/stores/authStore';

const schema = z.object({
  issue_type_id: z.number(),
  description: z.string().trim(),
});

type FormValues = z.infer<typeof schema>;

// Visual-only severity chips — the create-issue payload has no severity field;
// these communicate urgency but don't submit (kept as-is from the old design).
const SEVERITY_OPTIONS = [
  { id: 'minor', label: 'Minor', color: '#EA5A2B' },
  { id: 'major', label: 'Major', color: '#f97316' },
  { id: 'block', label: 'Block', color: '#D6442F' },
] as const;

export function NewIssueScreen() {
  const { t } = useTranslation();
  const router = useRouter();

  const issueTypes = useIssueTypes();
  const createMutation = useCreateIssue();
  const lineId = useAuthStore((s) => s.activeLineId);

  const { control, handleSubmit, formState: { isValid } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: { issue_type_id: undefined as unknown as number, description: '' },
  });

  if (issueTypes.isLoading) return <LoadingState />;

  const onSubmit = (values: FormValues) => {
    createMutation.mutate(
      {
        issue_type_id: values.issue_type_id,
        description: values.description || undefined,
        line_id: lineId ?? undefined,
      },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert('Could not create issue', e.message),
      },
    );
  };

  const blockingType = (issueTypes.data ?? []).find((it) => it.is_blocking);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
      <Text style={styles.h1}>{t('Report issue')}</Text>

      {/* Severity (visual only — drives icon prominence) */}
      <View>
        <SectionLabel>Severity</SectionLabel>
        <View style={styles.severityRow}>
          {SEVERITY_OPTIONS.map((s, i) => {
            const active = i === 1; // visual default — major
            return (
              <View
                key={s.id}
                style={[
                  styles.severity,
                  {
                    borderColor: active ? s.color : colors.line,
                    backgroundColor: active ? s.color + '22' : colors.card,
                  },
                ]}>
                <View style={[styles.dot, { backgroundColor: s.color }]} />
                <Text style={[styles.severityLabel, { color: active ? s.color : colors.ink }]}>{t(s.label)}</Text>
              </View>
            );
          })}
        </View>
        {blockingType ? (
          <View style={styles.blockingHint}>
            <FontAwesome name="exclamation-triangle" size={11} color={colors.downtime} />
            <Mono size={11} color={colors.downtime} letterSpacing={0.4}>
              {t('Blocking types notify supervisor immediately').toUpperCase()}
            </Mono>
          </View>
        ) : null}
      </View>

      {/* Categories */}
      <View>
        <SectionLabel>Issue type</SectionLabel>
        <Controller
          control={control}
          name="issue_type_id"
          render={({ field: { value, onChange } }) => (
            <View style={styles.catGrid}>
              {(issueTypes.data ?? []).map((it) => {
                const active = it.id === value;
                return (
                  <Pressable
                    key={it.id}
                    onPress={() => onChange(it.id)}
                    style={({ pressed }) => [
                      styles.catCard,
                      {
                        backgroundColor: active ? `${colors.accent}1A` : colors.card,
                        borderColor: active ? colors.accent : colors.line,
                        opacity: pressed ? 0.85 : 1,
                      },
                    ]}>
                    <FontAwesome name={iconFor(it.name)} size={20} color={active ? colors.accent : colors.ink} />
                    <Text
                      style={[styles.catLabel, { color: active ? colors.accent : colors.ink }]}
                      numberOfLines={1}>
                      {it.name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          )}
        />
      </View>

      {/* Description */}
      <View>
        <SectionLabel>Description</SectionLabel>
        <ControlledField
          control={control}
          name="description"
          label="What happened?"
          multiline
          numberOfLines={5}
          style={{ minHeight: 120, textAlignVertical: 'top' }}
        />
      </View>

      <Button
        title={t('Submit & escalate')}
        size="lg"
        variant="danger"
        leftIcon={<FontAwesome name="exclamation-triangle" size={16} color={colors.blocked} />}
        onPress={handleSubmit(onSubmit)}
        loading={createMutation.isPending}
        disabled={!isValid}
      />
    </ScrollView>
  );
}

function iconFor(name: string): React.ComponentProps<typeof FontAwesome>['name'] {
  const n = name.toLowerCase();
  if (n.includes('material')) return 'cube';
  if (n.includes('tool')) return 'wrench';
  if (n.includes('quality') || n.includes('jakość')) return 'shield';
  if (n.includes('machine') || n.includes('maszyn')) return 'cog';
  if (n.includes('safety') || n.includes('bezpiecz')) return 'flag';
  return 'ellipsis-h';
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  container: { padding: 18, gap: 18, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  severityRow: { flexDirection: 'row', gap: 8 },
  severity: { flex: 1, height: 64, borderRadius: radius.md, borderWidth: 1, alignItems: 'center', justifyContent: 'center', gap: 4 },
  dot: { width: 8, height: 8, borderRadius: 4 },
  severityLabel: { fontSize: 13, fontFamily: fonts.sans.native.medium },
  blockingHint: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 8 },
  catGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  catCard: { flexBasis: '31%', flexGrow: 1, minHeight: 88, borderRadius: radius.md, borderWidth: 1, alignItems: 'center', justifyContent: 'center', gap: 6, padding: 10 },
  catLabel: { fontSize: 12, fontFamily: fonts.sans.native.medium, textAlign: 'center' },
});
