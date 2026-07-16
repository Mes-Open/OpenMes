import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useProductType, useProcessTemplatesForProductType } from '@/hooks/queries/useProductTypes';
import {
  useDeleteProductType,
  useToggleProductTypeActive,
  useUpdateProductType,
} from '@/hooks/mutations/productTypes';

export function ProductTypeDetailScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const ptQuery = useProductType(numericId);
  const templatesQuery = useProcessTemplatesForProductType(numericId, true);
  const updateMutation = useUpdateProductType();
  const deleteMutation = useDeleteProductType();
  const toggleMutation = useToggleProductTypeActive();

  const pt = ptQuery.data;

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [unit, setUnit] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(false);

  if (!seeded && pt) {
    setCode(pt.code ?? '');
    setName(pt.name ?? '');
    setUnit(pt.unit_of_measure ?? '');
    setDescription(pt.description ?? '');
    setIsActive(pt.is_active ?? true);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  if (ptQuery.isLoading) return <LoadingState />;
  if (ptQuery.isError || !pt) return <ErrorState error={ptQuery.error} onRetry={ptQuery.refetch} />;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    updateMutation.mutate(
      {
        id: pt.id,
        payload: {
          code: code.trim(),
          name: name.trim(),
          description: description.trim() || undefined,
          unit_of_measure: unit.trim() || undefined,
          is_active: isActive,
        },
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );
  };

  const confirmDelete = () =>
    Alert.alert(t('Delete product type'), t('Delete "{{name}}"?', { name: pt.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete product type'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(pt.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);

  const templateCount = templatesQuery.data?.length ?? pt.process_templates_count ?? 0;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit product type')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required autoCapitalize="characters" autoCorrect={false} mono />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
      <Field label="Unit of measure (optional)" value={unit} onChangeText={setUnit} autoCapitalize="none" placeholder="pcs, kg, m" />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={{ minHeight: 80, textAlignVertical: 'top' }} />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Ready for production')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={updateMutation.isPending} disabled={updateMutation.isPending} />
      </View>

      <View style={styles.section}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Related').toUpperCase()}</Mono>
        <Pressable
          onPress={() => router.push(`/(drawer)/production/product-types/${pt.id}/templates` as never)}
          style={({ pressed }) => [styles.linkRow, { opacity: pressed ? 0.6 : 1 }]}>
          <View style={{ flex: 1 }}>
            <Text style={styles.linkTitle}>{t('Process templates')}</Text>
            <Mono size={9} color={colors.faint}>{t('Versioned recipes and step lists')}</Mono>
          </View>
          <Mono size={12} color={colors.muted}>{String(templateCount)}</Mono>
          <FontAwesome name="chevron-right" size={12} color={colors.faintest} />
        </Pressable>
      </View>

      <View style={styles.dangerZone}>
        <Button
          title={pt.is_active ? 'Deactivate' : 'Activate'}
          variant="outline"
          loading={toggleMutation.isPending}
          onPress={() => toggleMutation.mutate(pt.id, { onError: (e: Error) => Alert.alert(t('Failed'), e.message) })}
        />
        <Button
          title={t('Delete product type')}
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
  section: { gap: 8 },
  linkRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  linkTitle: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  dangerZone: { gap: 10, marginTop: 4 },
});
