import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useSkill } from '@/hooks/queries/useHr';
import { useDeleteSkill, useUpdateSkill } from '@/hooks/mutations/hr';

export function EditSkillScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useSkill(numericId);
  const updateMutation = useUpdateSkill();
  const deleteMutation = useDeleteSkill();

  const s = query.data;
  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [seeded, setSeeded] = useState(false);

  if (!seeded && s) {
    setCode(s.code);
    setName(s.name);
    setDescription(s.description ?? '');
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !s) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    updateMutation.mutate(
      { id: s.id, payload: { code: code.trim(), name: name.trim(), description: description.trim() || undefined } },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );
  };

  const onDelete = () => {
    Alert.alert(t('Delete skill'), t('Delete "{{name}}"?', { name: s.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete skill'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(s.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit skill')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={{ minHeight: 80, textAlignVertical: 'top' }} />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={updateMutation.isPending} disabled={updateMutation.isPending} />
      </View>

      <View style={styles.danger}>
        <Button
          title={t('Delete skill')}
          variant="danger"
          leftIcon={<FontAwesome name="trash" size={13} color={colors.blocked} />}
          loading={deleteMutation.isPending}
          onPress={onDelete}
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  danger: { marginTop: 12, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2, paddingTop: 16 },
});
