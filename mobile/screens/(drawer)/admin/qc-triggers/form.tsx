/**
 * QC trigger create/edit form — 1:1 with the web admin form: name, trigger type
 * (threshold shown for frequency types), the QC template to run, an optional
 * scope (line / workstation / product type) and blocking + active toggles.
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
import { LoadingState } from '@/components/ui/StateViews';
import {
  useCreateQcTrigger,
  useQcTriggerMeta,
  useQcTriggers,
  useUpdateQcTrigger,
} from '@/hooks/queries/useAdminConfig';
import type { NamedRef } from '@/api/adminConfig';

type ScopeKind = '' | 'line' | 'workstation' | 'product_type';

export function QcTriggerFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const list = useQcTriggers();
  const meta = useQcTriggerMeta();
  const existing = isEdit ? list.data?.find((x) => x.id === id) : undefined;
  const create = useCreateQcTrigger();
  const update = useUpdateQcTrigger();

  const initialScope: ScopeKind = existing?.line_id
    ? 'line'
    : existing?.workstation_id
      ? 'workstation'
      : existing?.product_type_id
        ? 'product_type'
        : '';

  const [name, setName] = useState(existing?.name ?? '');
  const [triggerType, setTriggerType] = useState(existing?.trigger_type ?? 'in_production');
  const [templateId, setTemplateId] = useState(existing?.quality_check_template_id ? String(existing.quality_check_template_id) : '');
  const [scopeKind, setScopeKind] = useState<ScopeKind>(initialScope);
  const [scopeId, setScopeId] = useState(
    existing?.line_id ? String(existing.line_id) : existing?.workstation_id ? String(existing.workstation_id) : existing?.product_type_id ? String(existing.product_type_id) : '',
  );
  const [threshold, setThreshold] = useState(existing?.threshold_n ? String(existing.threshold_n) : '');
  const [isBlocking, setIsBlocking] = useState(existing?.is_blocking ?? false);
  const [isActive, setIsActive] = useState(existing?.is_active ?? true);
  const [seeded, setSeeded] = useState(!isEdit);

  if (isEdit && !seeded && existing) {
    setName(existing.name);
    setTriggerType(existing.trigger_type);
    setTemplateId(existing.quality_check_template_id ? String(existing.quality_check_template_id) : '');
    setScopeKind(initialScope);
    setScopeId(existing.line_id ? String(existing.line_id) : existing.workstation_id ? String(existing.workstation_id) : existing.product_type_id ? String(existing.product_type_id) : '');
    setThreshold(existing.threshold_n ? String(existing.threshold_n) : '');
    setIsBlocking(existing.is_blocking);
    setIsActive(existing.is_active);
    setSeeded(true);
  }

  const typeMeta = meta.data?.types.find((ty) => ty.value === triggerType);
  const needsThreshold = typeMeta?.needs_threshold ?? false;

  const scopeOptions: NamedRef[] = useMemo(() => {
    if (scopeKind === 'line') return meta.data?.lines ?? [];
    if (scopeKind === 'workstation') return meta.data?.workstations ?? [];
    if (scopeKind === 'product_type') return meta.data?.product_types ?? [];
    return [];
  }, [scopeKind, meta.data]);

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    if (needsThreshold && (!threshold.trim() || Number(threshold) < 1)) e.threshold = 'Enter a number ≥ 1';
    if (scopeKind && !scopeId) e.scope = 'Select a scope target';
    return e;
  }, [name, needsThreshold, threshold, scopeKind, scopeId]);

  const pending = create.isPending || update.isPending;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const sid = scopeId ? Number(scopeId) : null;
    const input = {
      name: name.trim(),
      trigger_type: triggerType,
      quality_check_template_id: templateId ? Number(templateId) : null,
      line_id: scopeKind === 'line' ? sid : null,
      workstation_id: scopeKind === 'workstation' ? sid : null,
      product_type_id: scopeKind === 'product_type' ? sid : null,
      threshold_n: needsThreshold ? Number(threshold) : null,
      is_blocking: isBlocking,
      is_active: isActive,
    };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    else create.mutate(input, { onSuccess: () => router.back(), onError });
  };

  if ((isEdit && list.isLoading && !existing) || meta.isLoading) return <LoadingState />;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? t('Edit QC trigger') : t('New QC trigger')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="Every 50 units" />

      <Picker label={t('Trigger type')} required value={triggerType} onChange={(v) => setTriggerType(v)}
        options={(meta.data?.types ?? []).map((ty) => ({ value: ty.value, label: ty.label }))} />

      {needsThreshold ? (
        <Field label="Threshold (N)" value={threshold} onChangeText={setThreshold} error={errors.threshold} required keyboardType="number-pad" mono placeholder="50" />
      ) : null}

      <Picker label={t('QC template')} value={templateId} onChange={setTemplateId} placeholder={t('No template')}
        options={[{ value: '', label: t('No template') }, ...(meta.data?.templates ?? []).map((x) => ({ value: String(x.id), label: x.name }))]} />

      <Picker label={t('Scope')} value={scopeKind} onChange={(v) => { setScopeKind(v as ScopeKind); setScopeId(''); }}
        options={[
          { value: '', label: t('All production') },
          { value: 'line', label: t('Line') },
          { value: 'workstation', label: t('Workstation') },
          { value: 'product_type', label: t('Product type') },
        ]} />
      {scopeKind ? (
        <Picker label={t('Scope target')} required value={scopeId} onChange={setScopeId} error={errors.scope} placeholder={t('Select…')}
          options={scopeOptions.map((x) => ({ value: String(x.id), label: x.name }))} />
      ) : null}

      <ToggleRow label={t('Blocking')} hint={t('Block the line until the check passes')} value={isBlocking} onValueChange={setIsBlocking} />
      <ToggleRow label={t('Active')} hint={t('Trigger is enabled')} value={isActive} onValueChange={setIsActive} />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={pending} disabled={pending} />
      </View>
    </ScrollView>
  );
}

function Picker({ label, value, onChange, options, placeholder, required, error }: {
  label: string; value: string; onChange: (v: string) => void;
  options: { value: string; label: string }[]; placeholder?: string; required?: boolean; error?: string;
}) {
  return (
    <View style={{ gap: 6 }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}{required ? ' *' : ''}</Mono>
      <Dropdown value={value} onChange={(v) => onChange(v as string)} placeholder={placeholder} options={options} />
      {error ? <Text style={styles.error}>{error}</Text> : null}
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
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
