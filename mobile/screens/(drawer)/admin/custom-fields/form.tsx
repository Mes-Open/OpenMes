/**
 * Custom field create/edit form — 1:1 with the web admin form: entity, key
 * (lowercase slug), label, type, an options editor shown for dropdown/multi-
 * select types, plus required + active toggles.
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
import { Switch } from '@/components/ui/Switch';
import { LoadingState } from '@/components/ui/StateViews';
import {
  useCreateCustomField,
  useCustomFieldMeta,
  useCustomFields,
  useUpdateCustomField,
} from '@/hooks/queries/useAdminConfig';
import type { CustomFieldOption } from '@/api/adminConfig';

export function CustomFieldFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const list = useCustomFields();
  const meta = useCustomFieldMeta();
  const existing = isEdit ? list.data?.find((f) => f.id === id) : undefined;
  const create = useCreateCustomField();
  const update = useUpdateCustomField();

  const [entityType, setEntityType] = useState(existing?.entity_type ?? '');
  const [key, setKey] = useState(existing?.key ?? '');
  const [label, setLabel] = useState(existing?.label ?? '');
  const [type, setType] = useState(existing?.type ?? 'text');
  const [required, setRequired] = useState(existing?.required ?? false);
  const [isActive, setIsActive] = useState(existing?.is_active ?? true);
  const [options, setOptions] = useState<CustomFieldOption[]>(existing?.config?.options ?? []);
  const [seeded, setSeeded] = useState(!isEdit);

  if (isEdit && !seeded && existing) {
    setEntityType(existing.entity_type);
    setKey(existing.key);
    setLabel(existing.label);
    setType(existing.type);
    setRequired(existing.required);
    setIsActive(existing.is_active);
    setOptions(existing.config?.options ?? []);
    setSeeded(true);
  }

  const typeMeta = meta.data?.types.find((ty) => ty.value === type);
  const needsOptions = typeMeta?.has_options ?? false;

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!entityType) e.entityType = 'Required';
    if (!key.trim()) e.key = 'Required';
    else if (!/^[a-z][a-z0-9_]*$/.test(key.trim())) e.key = 'Lowercase letters, numbers and underscores; must start with a letter';
    if (!label.trim()) e.label = 'Required';
    if (needsOptions && options.filter((o) => o.value.trim()).length === 0) e.options = 'Add at least one option';
    return e;
  }, [entityType, key, label, needsOptions, options]);

  const pending = create.isPending || update.isPending;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const cleanOptions = options.filter((o) => o.value.trim()).map((o) => ({ value: o.value.trim(), label: (o.label || o.value).trim() }));
    const input = {
      entity_type: entityType,
      key: key.trim(),
      label: label.trim(),
      type,
      required,
      is_active: isActive,
      ...(needsOptions ? { config: { options: cleanOptions } } : {}),
    };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    else create.mutate(input, { onSuccess: () => router.back(), onError });
  };

  if ((isEdit && list.isLoading && !existing) || meta.isLoading) return <LoadingState />;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? t('Edit custom field') : t('New custom field')}</Text>

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Entity').toUpperCase()} *</Mono>
        <Dropdown
          value={entityType}
          onChange={(v) => setEntityType(v as string)}
          placeholder={t('Select entity')}
          options={(meta.data?.entities ?? []).map((en) => ({ value: en.value, label: en.label }))}
        />
        {errors.entityType ? <Text style={styles.error}>{t(errors.entityType)}</Text> : null}
      </View>

      <Field label="Key" value={key} onChangeText={setKey} error={errors.key} required autoCapitalize="none" mono
        editable={!isEdit} hint={isEdit ? 'Key cannot change after creation' : 'e.g. customer_ref'} placeholder="customer_ref" />
      <Field label="Label" value={label} onChangeText={setLabel} error={errors.label} required placeholder="Customer reference" />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Type').toUpperCase()} *</Mono>
        <Dropdown
          value={type}
          onChange={(v) => setType(v as string)}
          options={(meta.data?.types ?? []).map((ty) => ({ value: ty.value, label: ty.label }))}
        />
      </View>

      {needsOptions ? (
        <View style={styles.optionsBox}>
          <View style={styles.optHead}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Options').toUpperCase()} *</Mono>
            <View style={{ flex: 1 }} />
            <Button title={t('Add option')} size="sm" variant="ghost" onPress={() => setOptions((o) => [...o, { value: '', label: '' }])} />
          </View>
          {options.map((opt, i) => (
            <View key={i} style={styles.optRow}>
              <View style={{ flex: 1 }}>
                <Field label="" value={opt.value} onChangeText={(v) => setOptions((o) => o.map((x, j) => (j === i ? { ...x, value: v } : x)))} autoCapitalize="none" mono placeholder="value" />
              </View>
              <View style={{ flex: 1 }}>
                <Field label="" value={opt.label} onChangeText={(v) => setOptions((o) => o.map((x, j) => (j === i ? { ...x, label: v } : x)))} placeholder="Label" />
              </View>
              <Pressable onPress={() => setOptions((o) => o.filter((_, j) => j !== i))} hitSlop={8} style={styles.optDel}>
                <FontAwesome name="trash-o" size={16} color={colors.blocked} />
              </Pressable>
            </View>
          ))}
          {options.length === 0 ? <Text style={styles.muted}>{t('No options yet.')}</Text> : null}
          {errors.options ? <Text style={styles.error}>{t(errors.options)}</Text> : null}
        </View>
      ) : null}

      <ToggleRow label={t('Required')} hint={t('Must be filled in')} value={required} onValueChange={setRequired} />
      <ToggleRow label={t('Active')} hint={t('Field is shown on forms')} value={isActive} onValueChange={setIsActive} />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={pending} disabled={pending} />
      </View>
    </ScrollView>
  );
}

function ToggleRow({ label, hint, value, onValueChange }: { label: string; hint: string; value: boolean; onValueChange: (b: boolean) => void }) {
  return (
    <View style={styles.toggleRow}>
      <View style={{ flex: 1 }}>
        <Text style={styles.toggleLabel}>{label}</Text>
        <Mono size={9} color={colors.faint}>{hint}</Mono>
      </View>
      <Switch value={value} onValueChange={onValueChange} />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  muted: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },
  optionsBox: { gap: 10, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  optHead: { flexDirection: 'row', alignItems: 'center' },
  optRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  optDel: { padding: 8 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
