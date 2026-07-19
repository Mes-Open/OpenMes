/**
 * Issue type create/edit form — 1:1 with the web admin form
 * (Pages/admin/issue-types/fields.js): code, name, severity (required), a
 * blocking toggle and an active toggle. Severity has no REST meta endpoint, so
 * the fixed LOW/MEDIUM/HIGH/CRITICAL scale is inlined.
 */
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useCreateIssueType,
  useIssueType,
  useUpdateIssueType,
} from '@/hooks/queries/useIssues';
import type { IssueSeverity } from '@/types/api';

const SEVERITIES: IssueSeverity[] = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];

export function IssueTypeFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const query = useIssueType(isEdit ? id : undefined);
  const create = useCreateIssueType();
  const update = useUpdateIssueType();
  const existing = query.data;

  const severityLabels: Record<IssueSeverity, string> = {
    LOW: t('Low'),
    MEDIUM: t('Medium'),
    HIGH: t('High'),
    CRITICAL: t('Critical'),
  };

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [severity, setSeverity] = useState<IssueSeverity>('MEDIUM');
  const [isBlocking, setIsBlocking] = useState(false);
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(!isEdit);

  if (isEdit && !seeded && existing) {
    setCode(existing.code ?? '');
    setName(existing.name);
    setSeverity(existing.severity ?? 'MEDIUM');
    setIsBlocking(existing.is_blocking ?? false);
    setIsActive(existing.is_active ?? true);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  const pending = create.isPending || update.isPending;

  if (isEdit && query.isLoading && !existing) return <LoadingState />;
  if (isEdit && (query.isError || !existing)) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const input = {
      code: code.trim(),
      name: name.trim(),
      severity,
      is_blocking: isBlocking,
      is_active: isActive,
    };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    else create.mutate(input, { onSuccess: () => router.back(), onError });
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? t('Edit Issue Type') : t('New Issue Type')}</Text>

      <View style={styles.codeRow}>
        <View style={{ width: 130 }}>
          <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
        </View>
      </View>

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Severity').toUpperCase()} *</Mono>
        <Dropdown
          value={severity}
          onChange={(v) => setSeverity(v as IssueSeverity)}
          options={SEVERITIES.map((s) => ({ value: s, label: severityLabels[s] }))}
        />
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Blocking')}</Text>
          <Mono size={9} color={colors.faint}>{t('Block the line until the issue is resolved')}</Mono>
        </View>
        <Switch value={isBlocking} onValueChange={setIsBlocking} />
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('INACTIVE ENTITIES ARE HIDDEN BY DEFAULT')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={isEdit ? t('Save changes') : t('Save')} onPress={onSave} loading={pending} disabled={pending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  codeRow: { flexDirection: 'row', gap: 10 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
