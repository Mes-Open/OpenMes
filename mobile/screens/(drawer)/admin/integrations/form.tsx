/**
 * Integration create/edit form — 1:1 with the web admin form: system type
 * (unique slug), display name and an active toggle. The encrypted credential
 * config is managed elsewhere and not edited here.
 */
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { LoadingState } from '@/components/ui/StateViews';
import {
  useCreateIntegration,
  useIntegrations,
  useUpdateIntegration,
} from '@/hooks/queries/useAdminConfig';

export function IntegrationFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const list = useIntegrations();
  const existing = isEdit ? list.data?.find((i) => i.id === id) : undefined;
  const create = useCreateIntegration();
  const update = useUpdateIntegration();

  const [systemType, setSystemType] = useState(existing?.system_type ?? '');
  const [systemName, setSystemName] = useState(existing?.system_name ?? '');
  const [isActive, setIsActive] = useState(existing?.is_active ?? true);
  const [seeded, setSeeded] = useState(!isEdit);

  if (isEdit && !seeded && existing) {
    setSystemType(existing.system_type);
    setSystemName(existing.system_name);
    setIsActive(existing.is_active);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!systemType.trim()) e.systemType = 'Required';
    if (!systemName.trim()) e.systemName = 'Required';
    return e;
  }, [systemType, systemName]);

  const pending = create.isPending || update.isPending;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const input = { system_type: systemType.trim(), system_name: systemName.trim(), is_active: isActive };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    else create.mutate(input, { onSuccess: () => router.back(), onError });
  };

  if (isEdit && list.isLoading && !existing) return <LoadingState />;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? t('Edit integration') : t('New integration')}</Text>

      <Field
        label="System type"
        value={systemType}
        onChangeText={setSystemType}
        error={errors.systemType}
        required
        autoCapitalize="none"
        mono
        hint="A unique identifier, e.g. subiekt"
        placeholder="subiekt"
      />
      <Field
        label="System name"
        value={systemName}
        onChangeText={setSystemName}
        error={errors.systemName}
        required
        placeholder="Subiekt GT"
      />

      <View style={styles.activeRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.label}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Integration is enabled')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={pending} disabled={pending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  label: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  activeRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
