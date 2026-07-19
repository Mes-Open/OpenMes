/**
 * Label template create/edit form — 1:1 with the web admin form: name, type,
 * size, barcode format, a checkbox grid of which fields appear on the label,
 * plus default + active toggles (one default per type).
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
  useCreateLabelTemplate,
  useLabelTemplateMeta,
  useLabelTemplates,
  useUpdateLabelTemplate,
} from '@/hooks/queries/usePackaging';

export function LabelTemplateFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const list = useLabelTemplates();
  const meta = useLabelTemplateMeta();
  const existing = isEdit ? list.data?.find((x) => x.id === id) : undefined;
  const create = useCreateLabelTemplate();
  const update = useUpdateLabelTemplate();

  const [name, setName] = useState(existing?.name ?? '');
  const [type, setType] = useState(existing?.type ?? '');
  const [size, setSize] = useState(existing?.size ?? '');
  const [barcode, setBarcode] = useState(existing?.barcode_format ?? '');
  const [fields, setFields] = useState<Record<string, boolean>>(existing?.fields_config ?? {});
  const [isDefault, setIsDefault] = useState(existing?.is_default ?? false);
  const [isActive, setIsActive] = useState(existing?.is_active ?? true);
  const [seeded, setSeeded] = useState(!isEdit);

  if (isEdit && !seeded && existing) {
    setName(existing.name);
    setType(existing.type);
    setSize(existing.size);
    setBarcode(existing.barcode_format);
    setFields(existing.fields_config ?? {});
    setIsDefault(existing.is_default);
    setIsActive(existing.is_active);
    setSeeded(true);
  }

  // Sensible defaults for a fresh template once the catalogs arrive.
  if (!isEdit && !type && meta.data) {
    setType(meta.data.types[0]?.value ?? '');
    setSize(meta.data.sizes[0]?.value ?? '');
    setBarcode(meta.data.barcode_formats[0]?.value ?? '');
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [name]);

  const pending = create.isPending || update.isPending;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const input = { name: name.trim(), type, size, barcode_format: barcode, fields_config: fields, is_default: isDefault, is_active: isActive };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    else create.mutate(input, { onSuccess: () => router.back(), onError });
  };

  if ((isEdit && list.isLoading && !existing) || meta.isLoading) return <LoadingState />;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? t('Edit label template') : t('New label template')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="Standard work order" />

      <Picker label={t('Type')} value={type} onChange={setType} options={meta.data?.types ?? []} />
      <Picker label={t('Size')} value={size} onChange={setSize} options={meta.data?.sizes ?? []} />
      <Picker label={t('Barcode')} value={barcode} onChange={setBarcode} options={meta.data?.barcode_formats ?? []} />

      <View style={styles.fieldsBox}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Fields on label').toUpperCase()}</Mono>
        <View style={styles.fieldGrid}>
          {(meta.data?.fields ?? []).map((f) => {
            const on = !!fields[f.value];
            return (
              <Pressable key={f.value} onPress={() => setFields((cur) => ({ ...cur, [f.value]: !on }))} style={styles.checkRow}>
                <View style={[styles.checkbox, on && styles.checkboxOn]}>
                  {on ? <FontAwesome name="check" size={10} color="#fff" /> : null}
                </View>
                <Text style={styles.checkLabel}>{f.label}</Text>
              </Pressable>
            );
          })}
        </View>
      </View>

      <ToggleRow label={t('Default')} hint={t('Default template for this type')} value={isDefault} onValueChange={setIsDefault} />
      <ToggleRow label={t('Active')} hint={t('Template is available for printing')} value={isActive} onValueChange={setIsActive} />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={pending} disabled={pending} />
      </View>
    </ScrollView>
  );
}

function Picker({ label, value, onChange, options }: { label: string; value: string; onChange: (v: string) => void; options: { value: string; label: string }[] }) {
  return (
    <View style={{ gap: 6 }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()} *</Mono>
      <Dropdown value={value} onChange={(v) => onChange(v as string)} options={options} />
    </View>
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
  fieldsBox: { gap: 10, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  fieldGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  checkRow: { flexDirection: 'row', alignItems: 'center', gap: 8, width: '46%', paddingVertical: 4 },
  checkbox: { width: 20, height: 20, borderRadius: 5, borderWidth: 1.5, borderColor: colors.line, alignItems: 'center', justifyContent: 'center' },
  checkboxOn: { backgroundColor: colors.accent, borderColor: colors.accent },
  checkLabel: { fontSize: 13, fontFamily: fonts.sans.native.regular, color: colors.ink, flexShrink: 1 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
