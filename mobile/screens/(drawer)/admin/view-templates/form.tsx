/**
 * View template create/edit form — 1:1 with the web admin column builder: a
 * name + description and an ordered list of columns, each a label, a key and a
 * source (a work-order field or an extra_data key). 1–20 columns.
 */
import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { LoadingState } from '@/components/ui/StateViews';
import {
  useCreateViewTemplate,
  useUpdateViewTemplate,
  useViewTemplates,
} from '@/hooks/queries/useViewTemplates';
import type { ViewColumn } from '@/api/viewTemplates';

export function ViewTemplateFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const list = useViewTemplates();
  const existing = isEdit ? list.data?.find((x) => x.id === id) : undefined;
  const create = useCreateViewTemplate();
  const update = useUpdateViewTemplate();

  const [name, setName] = useState(existing?.name ?? '');
  const [description, setDescription] = useState(existing?.description ?? '');
  const [columns, setColumns] = useState<ViewColumn[]>(existing?.columns ?? [{ label: '', key: '', source: 'field' }]);
  const [seeded, setSeeded] = useState(!isEdit);

  if (isEdit && !seeded && existing) {
    setName(existing.name);
    setDescription(existing.description ?? '');
    setColumns(existing.columns?.length ? existing.columns : [{ label: '', key: '', source: 'field' }]);
    setSeeded(true);
  }

  const setCol = (i: number, patch: Partial<ViewColumn>) =>
    setColumns((cur) => cur.map((c, j) => (j === i ? { ...c, ...patch } : c)));

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    const valid = columns.filter((c) => c.label.trim() && c.key.trim());
    if (valid.length === 0) e.columns = 'Add at least one column with a label and key';
    return e;
  }, [name, columns]);

  const pending = create.isPending || update.isPending;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const cleanColumns = columns
      .filter((c) => c.label.trim() && c.key.trim())
      .map((c) => ({ label: c.label.trim(), key: c.key.trim(), source: c.source }));
    const input = { name: name.trim(), description: description.trim() || null, columns: cleanColumns };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    else create.mutate(input, { onSuccess: () => router.back(), onError });
  };

  if (isEdit && list.isLoading && !existing) return <LoadingState />;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? t('Edit view template') : t('New view template')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="Compact line view" />
      <Field label="Description" value={description} onChangeText={setDescription} placeholder="Optional" multiline />

      <View style={styles.colsBox}>
        <View style={styles.colsHead}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Columns').toUpperCase()} *</Mono>
          <View style={{ flex: 1 }} />
          <Button title={t('Add column')} size="sm" variant="ghost" disabled={columns.length >= 20}
            onPress={() => setColumns((c) => [...c, { label: '', key: '', source: 'field' }])} />
        </View>

        {columns.map((col, i) => (
          <View key={i} style={styles.colCard}>
            <View style={styles.colTop}>
              <Mono size={9} color={colors.faint}>{`#${i + 1}`}</Mono>
              <View style={{ flex: 1 }} />
              <Pressable onPress={() => setColumns((c) => c.filter((_, j) => j !== i))} hitSlop={8}>
                <FontAwesome name="trash-o" size={15} color={colors.blocked} />
              </Pressable>
            </View>
            <Field label="" value={col.label} onChangeText={(v) => setCol(i, { label: v })} placeholder={t('Column label')} />
            <Field label="" value={col.key} onChangeText={(v) => setCol(i, { key: v })} autoCapitalize="none" mono placeholder={t('Field key')} />
            <Dropdown
              value={col.source}
              onChange={(v) => setCol(i, { source: v as ViewColumn['source'] })}
              options={[
                { value: 'field', label: t('Work order field') },
                { value: 'extra_data', label: t('Extra data') },
              ]}
            />
          </View>
        ))}
        {errors.columns ? <Text style={styles.error}>{t(errors.columns)}</Text> : null}
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={pending} disabled={pending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  colsBox: { gap: 12 },
  colsHead: { flexDirection: 'row', alignItems: 'center' },
  colCard: { gap: 8, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 12 },
  colTop: { flexDirection: 'row', alignItems: 'center' },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
