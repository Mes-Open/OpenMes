/**
 * Edit workstation type — re-skinned to the spec form/detail pattern. Mirrors
 * the web admin workstation-type form (Code / Name / Description / Active) plus
 * an activate toggle and delete. Hooks, payloads and navigation are unchanged.
 */
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useWorkstationType } from '@/hooks/queries/useWorkstationTypes';
import {
  useDeleteWorkstationType,
  useToggleWorkstationTypeActive,
  useUpdateWorkstationType,
} from '@/hooks/mutations/workstationTypes';

export function EditWorkstationTypeScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useWorkstationType(numericId);
  const updateMutation = useUpdateWorkstationType();
  const deleteMutation = useDeleteWorkstationType();
  const toggleMutation = useToggleWorkstationTypeActive();

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(false);

  const wt = query.data;
  if (wt && !seeded) {
    setCode(wt.code);
    setName(wt.name);
    setDescription(wt.description ?? '');
    setIsActive(wt.is_active);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  if (query.isLoading && !wt) return <LoadingState />;
  if (query.isError || !wt) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const invalid = Object.keys(errors).length > 0;

  const onSave = () => {
    if (invalid) return;
    updateMutation.mutate(
      {
        id: wt.id,
        payload: {
          code: code.trim(),
          name: name.trim(),
          description: description.trim() || undefined,
          is_active: isActive,
        },
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );
  };

  const confirmDelete = () => {
    Alert.alert(t('Delete workstation type'), t('Delete "{{name}}"?', { name: wt.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(wt.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit workstation type')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
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
        <Button title={t('Save changes')} onPress={onSave} loading={updateMutation.isPending} disabled={invalid || updateMutation.isPending} />
      </View>

      <View style={styles.danger}>
        <Button
          title={isActive ? t('Deactivate') : t('Activate')}
          variant="outline"
          loading={toggleMutation.isPending}
          onPress={() => toggleMutation.mutate(wt.id, { onError: (e: Error) => Alert.alert(t('Failed'), e.message) })}
        />
        <Button
          title={t('Delete workstation type')}
          variant="danger"
          leftIcon={<FontAwesome name="trash" size={13} color={colors.blocked} />}
          loading={deleteMutation.isPending}
          onPress={confirmDelete}
        />
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
  danger: { gap: 10, marginTop: 8 },
});
