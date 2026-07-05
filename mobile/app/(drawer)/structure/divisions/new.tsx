/**
 * New Division — create form with a Factory picker, mirroring the web
 * Pages/admin/divisions/Create.jsx fields (factory / code / name /
 * description / active). Posts via the nested factories/{id}/divisions API.
 */
import { useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, Switch, colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { useCreateDivision, useFactories } from '@/hooks/queries/useOrgStructure';

export default function NewDivisionPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const factoriesQ = useFactories(false);
  const create = useCreateDivision();

  const [factoryId, setFactoryId] = useState<string>('');
  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);

  const factories = factoriesQ.data ?? [];
  const canSave = factoryId !== '' && code.trim() !== '' && name.trim() !== '' && !create.isPending;

  const onSave = () =>
    create.mutate(
      {
        factoryId: Number(factoryId),
        payload: { code: code.trim(), name: name.trim(), description: description.trim() || undefined, is_active: isActive },
      },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not save'), e.message),
      },
    );

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.form}>
        <Text style={styles.h1}>{t('New Division')}</Text>

        <Dropdown
          label={t('Factory')}
          value={factoryId}
          onChange={(v) => setFactoryId(String(v))}
          placeholder={t('— Select factory —')}
          options={factories.map((f) => ({ value: String(f.id), label: f.name }))}
        />
        <Field label={t('Code')} value={code} onChangeText={setCode} autoCapitalize="characters" />
        <Field label={t('Name')} value={name} onChangeText={setName} />
        <Field label={t('Description')} value={description} onChangeText={setDescription} multiline />
        <View style={styles.switchRow}>
          <Text style={styles.switchLabel}>{t('Active')}</Text>
          <Switch checked={isActive} onChange={setIsActive} />
        </View>

        <View style={styles.actions}>
          <Button title={t('Cancel')} variant="secondary" onPress={() => router.back()} />
          <Button title={t('Create Division')} onPress={onSave} disabled={!canSave} loading={create.isPending} />
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 40, alignItems: 'center' },
  form: { width: '100%', maxWidth: 640, gap: 14 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4, marginBottom: 4 },
  switchRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 4 },
  switchLabel: { fontSize: 13.5, color: colors.ink, fontFamily: fonts.sans.native.medium },
  actions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 10, marginTop: 8 },
});
