/**
 * New tool — inline spec form mirroring the web admin tools Create page
 * (Code / Name / Description / Status / Next service). Uses useCreateTool.
 */
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { useCreateTool } from '@/hooks/queries/useMaintenance';
import type { ToolStatus } from '@/api/maintenance';

const STATUSES: { value: ToolStatus; label: string }[] = [
  { value: 'available', label: 'Available' },
  { value: 'in_use', label: 'In use' },
  { value: 'maintenance', label: 'Maintenance' },
  { value: 'retired', label: 'Retired' },
];

export function NewToolScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const m = useCreateTool();

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [status, setStatus] = useState<ToolStatus>('available');
  const [nextService, setNextService] = useState('');

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    m.mutate(
      {
        code: code.trim(),
        name: name.trim(),
        description: description.trim() || undefined,
        status,
        next_service_at: nextService.trim() || undefined,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New tool')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={styles.textarea} />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Status').toUpperCase()}</Mono>
        <Dropdown value={status} onChange={(v) => setStatus(v as ToolStatus)} options={STATUSES.map((s) => ({ value: s.value, label: t(s.label) }))} />
      </View>

      <Field label="Next service" value={nextService} onChangeText={setNextService} placeholder="YYYY-MM-DD" autoCapitalize="none" autoCorrect={false} hint="Optional — leave blank if no service is scheduled." mono />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Create')} onPress={onSave} loading={m.isPending} disabled={m.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  textarea: { minHeight: 80, textAlignVertical: 'top' },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
