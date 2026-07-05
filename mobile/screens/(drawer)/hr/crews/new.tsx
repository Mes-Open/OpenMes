/**
 * New crew — inline form mirroring the web crew form (Code / Name / Leader /
 * Description / Active). Writes through useCreateCrew.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button, Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { useUsers } from '@/hooks/queries/useUsers';
import { useCreateCrew } from '@/hooks/mutations/hr';

export function NewCrewScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const create = useCreateCrew();
  const usersQ = useUsers({ role: 'Supervisor' });

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [leaderId, setLeaderId] = useState<number | null>(null);
  const [isActive, setIsActive] = useState(true);

  const leaderOptions = useMemo(
    () => [
      { value: '', label: t('— None —') },
      ...(usersQ.data?.data ?? []).map((u) => ({ value: String(u.id), label: u.name ?? u.username })),
    ],
    [usersQ.data, t],
  );

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    create.mutate(
      {
        code: code.trim(),
        name: name.trim(),
        description: description.trim() || undefined,
        leader_id: leaderId ?? undefined,
        is_active: isActive,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.h1}>{t('New crew')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required autoCapitalize="characters" autoCorrect={false} mono />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Leader').toUpperCase()}</Mono>
        <Dropdown
          value={leaderId == null ? '' : String(leaderId)}
          onChange={(v) => setLeaderId(v ? Number(v) : null)}
          placeholder={t('— None —')}
          options={leaderOptions}
        />
      </View>

      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={{ minHeight: 80, textAlignVertical: 'top' }} />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Show in operator dropdowns and crew rosters')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button variant="ghost" onPress={() => router.back()}>{t('Cancel')}</Button>
        <View style={{ flex: 1 }} />
        <Button onPress={onSave} loading={create.isPending} disabled={create.isPending}>{t('Save')}</Button>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
