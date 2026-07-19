/**
 * Scrap reason create/edit form — 1:1 with the web admin form
 * (Pages/admin/scrap-reasons/fields.js): code, name, the 5M category (required),
 * an optional description, sort order and an active toggle. Category has no REST
 * meta endpoint, so the fixed Ishikawa taxonomy is inlined from SCRAP_CATEGORIES.
 */
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useCreateScrapReason,
  useScrapReason,
  useUpdateScrapReason,
} from '@/hooks/queries/useScrapReasons';
import { SCRAP_CATEGORIES } from '@/api/scrapReasons';

export function ScrapReasonFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const query = useScrapReason(isEdit ? id : undefined);
  const create = useCreateScrapReason();
  const update = useUpdateScrapReason();
  const existing = query.data;

  const categoryLabels: Record<string, string> = {
    material: t('Material'),
    machine: t('Machine'),
    method: t('Method'),
    man: t('Man'),
    environment: t('Environment'),
  };

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [category, setCategory] = useState('');
  const [description, setDescription] = useState('');
  const [sortOrder, setSortOrder] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(!isEdit);

  if (isEdit && !seeded && existing) {
    setCode(existing.code);
    setName(existing.name);
    setCategory(existing.category ?? '');
    setDescription(existing.description ?? '');
    setSortOrder(existing.sort_order != null ? String(existing.sort_order) : '');
    setIsActive(existing.is_active ?? true);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    if (!category) e.category = 'Required';
    return e;
  }, [code, name, category]);

  const pending = create.isPending || update.isPending;

  if (isEdit && query.isLoading && !existing) return <LoadingState />;
  if (isEdit && (query.isError || !existing)) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const input = {
      code: code.trim(),
      name: name.trim(),
      category,
      description: description.trim() || null,
      sort_order: sortOrder.trim() ? Number(sortOrder) : undefined,
      is_active: isActive,
    };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    else create.mutate(input, { onSuccess: () => router.back(), onError });
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? t('Edit Scrap Reason') : t('New Scrap Reason')}</Text>

      <View style={styles.codeRow}>
        <View style={{ width: 130 }}>
          <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
        </View>
      </View>

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Category').toUpperCase()} *</Mono>
        <Dropdown
          value={category}
          onChange={(v) => setCategory(v as string)}
          placeholder={t('— Select category —')}
          options={SCRAP_CATEGORIES.map((c) => ({ value: c, label: categoryLabels[c] }))}
        />
        {errors.category ? <Text style={styles.error}>{t('Required')}</Text> : null}
      </View>

      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={styles.textarea} />
      <Field label="Sort order" value={sortOrder} onChangeText={setSortOrder} keyboardType="number-pad" mono />

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
        <Button title={isEdit ? t('Save changes') : t('Save')} onPress={onSave} loading={pending} disabled={pending} />
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
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
