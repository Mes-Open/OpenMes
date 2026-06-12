import { zodResolver } from '@hookform/resolvers/zod';
import { useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { z } from 'zod';

import { Button } from '@/components/ui/Button';
import { ControlledField } from '@/components/ui/ControlledField';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useIssueTypes } from '@/hooks/queries/useIssues';
import { useCreateIssue } from '@/hooks/mutations/issues';
import { useAuthStore } from '@/stores/authStore';

const schema = z.object({
  issue_type_id: z.number(),
  description: z.string().trim(),
});

type FormValues = z.infer<typeof schema>;

const SEVERITY_OPTIONS = [
  { id: 'minor', label: 'Minor', color: '#f5a524' },
  { id: 'major', label: 'Major', color: '#f97316' },
  { id: 'block', label: 'Block', color: '#ef4444' },
] as const;

export function NewIssueScreen() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

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

  const blockingType = (issueTypes.data ?? []).find((t) => t.is_blocking);

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader back title="Report issue" />
      <ScrollView
        style={{ flex: 1, backgroundColor: palette.background }}
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled">
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
                    borderColor: active ? s.color : palette.border,
                    backgroundColor: active ? s.color + '22' : palette.surface,
                  },
                ]}>
                <View style={[styles.dot, { backgroundColor: s.color }]} />
                <Text
                  style={[
                    styles.severityLabel,
                    { color: active ? s.color : palette.text },
                  ]}>
                  {s.label}
                </Text>
              </View>
            );
          })}
        </View>
        {blockingType ? (
          <View style={styles.blockingHint}>
            <FontAwesome name="exclamation-triangle" size={11} color="#f97316" />
            <Mono size={11} color="#f97316" letterSpacing={0.4}>
              BLOCKING TYPES NOTIFY SUPERVISOR IMMEDIATELY
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
              {(issueTypes.data ?? []).map((t) => {
                const active = t.id === value;
                return (
                  <Pressable
                    key={t.id}
                    onPress={() => onChange(t.id)}
                    style={({ pressed }) => [
                      styles.catCard,
                      {
                        backgroundColor: active ? '#fbe9c8' : palette.surface,
                        borderColor: active ? BRAND.amber : palette.border,
                        opacity: pressed ? 0.85 : 1,
                      },
                    ]}>
                    <FontAwesome
                      name={iconFor(t.name)}
                      size={20}
                      color={active ? BRAND.amber : palette.text}
                    />
                    <Text
                      style={{
                        fontSize: 12,
                        fontWeight: '600',
                        color: active ? BRAND.amber : palette.text,
                        textAlign: 'center',
                      }}
                      numberOfLines={1}>
                      {t.name}
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
        title="Submit & escalate"
        size="lg"
        variant="danger"
        leftIcon={<FontAwesome name="exclamation-triangle" size={16} color="#fff" />}
        onPress={handleSubmit(onSubmit)}
        loading={createMutation.isPending}
        disabled={!isValid}
      />
      </ScrollView>
    </View>
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
  container: { padding: 18, gap: 18 },
  severityRow: { flexDirection: 'row', gap: 8 },
  severity: {
    flex: 1,
    height: 64,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  dot: { width: 8, height: 8, borderRadius: 4 },
  severityLabel: { fontSize: 13, fontWeight: '600' },
  blockingHint: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 8 },
  catGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  catCard: {
    flexBasis: '31%',
    flexGrow: 1,
    minHeight: 88,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    padding: 10,
  },
});
