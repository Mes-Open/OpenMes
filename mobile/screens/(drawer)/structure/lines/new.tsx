/**
 * New production line — re-skinned to the spec form pattern. Mirrors the web
 * admin line form (Code / Name / Description / Active). Hook, payload and
 * navigation are unchanged.
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
import { useCreateLine } from '@/hooks/mutations/lines';

export function NewLineScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const createMutation = useCreateLine();

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);
  const invalid = Object.keys(errors).length > 0;

  const onSave = () => {
    if (invalid) return;
    createMutation.mutate(
      {
        code: code.trim(),
        name: name.trim(),
        description: description.trim() || undefined,
        is_active: isActive,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create line'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New line')}</Text>

      <View style={styles.codeNameRow}>
        <View style={{ width: 120 }}>
          <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} placeholder="L-04" />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="Pack & Ship" />
        </View>
      </View>
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={{ minHeight: 80, textAlignVertical: 'top' }} />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('INACTIVE ENTITIES ARE HIDDEN BY DEFAULT')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Create')} onPress={onSave} loading={createMutation.isPending} disabled={invalid || createMutation.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  codeNameRow: { flexDirection: 'row', gap: 10 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
