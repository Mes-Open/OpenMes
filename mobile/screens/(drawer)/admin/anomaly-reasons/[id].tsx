import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useAnomalyReason, useDeleteAnomalyReason, useUpdateAnomalyReason } from '@/hooks/queries/useOps';

export function EditAnomalyReasonScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useAnomalyReason(numericId);
  const updateMutation = useUpdateAnomalyReason();
  const deleteMutation = useDeleteAnomalyReason();
  const r = query.data;

  const [code, setCode] = useState(r?.code ?? '');
  const [name, setName] = useState(r?.name ?? '');
  const [category, setCategory] = useState(r?.category ?? '');
  const [description, setDescription] = useState(r?.description ?? '');
  const [isActive, setIsActive] = useState(r?.is_active ?? true);
  const [seeded, setSeeded] = useState(false);

  if (!seeded && r) {
    setCode(r.code);
    setName(r.name);
    setCategory(r.category ?? '');
    setDescription(r.description ?? '');
    setIsActive(r.is_active);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  if (query.isLoading && !r) return <LoadingState />;
  if (query.isError || !r) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    updateMutation.mutate(
      {
        id: r.id,
        payload: {
          code: code.trim(),
          name: name.trim(),
          category: category.trim() || undefined,
          description: description.trim() || undefined,
          is_active: isActive,
        },
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );
  };

  const onDelete = () =>
    Alert.alert(t('Delete reason'), r.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(r.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit Anomaly Reason')}</Text>

      <View style={styles.codeRow}>
        <View style={{ width: 130 }}>
          <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
        </View>
      </View>
      <Field label="Category" value={category} onChangeText={setCategory} placeholder="e.g. quality, process" />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={styles.textarea} />

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
        <Button title={t('Save changes')} onPress={onSave} loading={updateMutation.isPending} disabled={updateMutation.isPending} />
      </View>

      <View style={styles.danger}>
        <Button title={t('Delete reason')} variant="danger" onPress={onDelete} loading={deleteMutation.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  codeRow: { flexDirection: 'row', gap: 10 },
  textarea: { minHeight: 80, textAlignVertical: 'top', paddingTop: 12 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  danger: { marginTop: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2, paddingTop: 16 },
});
