/**
 * Edit factory — re-skinned to the spec form/detail pattern. Mirrors the web
 * admin factory form (Code / Name / Description / Active) with a link row to the
 * factory's divisions plus an activate toggle and delete. Hooks, payloads and
 * navigation are unchanged.
 */
import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useFactory, useFactoryDivisions } from '@/hooks/queries/useOrgStructure';
import {
  useDeleteFactory,
  useToggleFactoryActive,
  useUpdateFactory,
} from '@/hooks/queries/useOrgStructure';

export function EditFactoryScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useFactory(numericId);
  const divisionsQuery = useFactoryDivisions(numericId, true);
  const updateMutation = useUpdateFactory();
  const deleteMutation = useDeleteFactory();
  const toggleMutation = useToggleFactoryActive();

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(false);

  const f = query.data;
  if (f && !seeded) {
    setCode(f.code);
    setName(f.name);
    setDescription(f.description ?? '');
    setIsActive(f.is_active);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  if (query.isLoading && !f) return <LoadingState />;
  if (query.isError || !f) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const invalid = Object.keys(errors).length > 0;
  const divisionsCount = divisionsQuery.data?.length ?? f.divisions_count ?? 0;

  const onSave = () => {
    if (invalid) return;
    updateMutation.mutate(
      {
        id: f.id,
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
    Alert.alert(t('Delete factory'), t('Delete "{{name}}"?', { name: f.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(f.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit factory')}</Text>

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

      <Pressable
        onPress={() => router.push(`/(drawer)/structure/factories/${f.id}/divisions` as never)}
        style={({ pressed }) => [styles.linkRow, { opacity: pressed ? 0.7 : 1 }]}>
        <View style={styles.iconWrap}>
          <FontAwesome name="th-large" size={14} color={colors.accent} />
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.linkTitle} numberOfLines={1}>
            {t('Divisions')}
            <Text style={styles.linkCount}> · {divisionsCount}</Text>
          </Text>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Manage divisions inside this factory').toUpperCase()}</Mono>
        </View>
        <FontAwesome name="chevron-right" size={12} color={colors.faintest} />
      </Pressable>

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
          onPress={() => toggleMutation.mutate(f.id, { onError: (e: Error) => Alert.alert(t('Failed'), e.message) })}
        />
        <Button
          title={t('Delete factory')}
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
  linkRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  iconWrap: { width: 34, height: 34, borderRadius: radius.sm, alignItems: 'center', justifyContent: 'center', backgroundColor: `${colors.accent}1A` },
  linkTitle: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  linkCount: { color: colors.faint, fontFamily: fonts.sans.native.medium },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  danger: { gap: 10, marginTop: 8 },
});
