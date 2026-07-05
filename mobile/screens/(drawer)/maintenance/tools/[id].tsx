/**
 * Tool detail — inline spec re-skin combining the tool profile (status + spec
 * key/value rows + recent maintenance history + schedule action), the edit
 * form (mirrors the web admin tools Edit page) and a delete action. Keeps the
 * useTool / useUpdateTool / useDeleteTool / useMaintenanceEvents hooks and the
 * "schedule maintenance" navigation target unchanged.
 */
import { useMemo, useState } from 'react';
import { format, parseISO } from 'date-fns';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useDeleteTool,
  useMaintenanceEvents,
  useTool,
  useUpdateTool,
} from '@/hooks/queries/useMaintenance';
import type { MaintenanceEvent, Tool, ToolStatus } from '@/api/maintenance';

const STATUSES: { value: ToolStatus; label: string }[] = [
  { value: 'available', label: 'Available' },
  { value: 'in_use', label: 'In use' },
  { value: 'maintenance', label: 'Maintenance' },
  { value: 'retired', label: 'Retired' },
];

const STATUS_TOKEN: Record<ToolStatus, string> = {
  available: colors.done,
  in_use: colors.running,
  maintenance: colors.downtime,
  retired: colors.faint,
};

const STATUS_LABEL: Record<ToolStatus, string> = {
  available: 'Available',
  in_use: 'In use',
  maintenance: 'In maintenance',
  retired: 'Retired',
};

function safeDate(iso: string | null | undefined): string {
  if (!iso) return '';
  try {
    return format(parseISO(iso), 'yyyy-MM-dd');
  } catch {
    return '';
  }
}

function typeLabel(type: MaintenanceEvent['event_type']): string {
  if (type === 'planned') return 'Preventive';
  if (type === 'corrective') return 'Corrective';
  if (type === 'inspection') return 'Inspection';
  return type;
}

export function EditToolScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useTool(numericId);
  const updateMutation = useUpdateTool();
  const deleteMutation = useDeleteTool();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const tool = query.data;

  return (
    <ToolDetail
      tool={tool}
      updating={updateMutation.isPending}
      deleting={deleteMutation.isPending}
      onSave={(payload) =>
        updateMutation.mutate(
          { id: tool.id, payload },
          { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
        )
      }
      onDelete={() =>
        deleteMutation.mutate(tool.id, {
          onSuccess: () => router.back(),
          onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
        })
      }
      onSchedule={() => router.push(`/maintenance/events/new?tool_id=${tool.id}` as never)}
    />
  );
}

function ToolDetail({
  tool,
  updating,
  deleting,
  onSave,
  onDelete,
  onSchedule,
}: {
  tool: Tool;
  updating: boolean;
  deleting: boolean;
  onSave: (payload: { code: string; name: string; description?: string; status: ToolStatus; next_service_at?: string }) => void;
  onDelete: () => void;
  onSchedule: () => void;
}) {
  const { t } = useTranslation();

  const eventsQ = useMaintenanceEvents({ tool_id: tool.id, per_page: 8 });
  const events: MaintenanceEvent[] = eventsQ.data?.data ?? [];

  const [code, setCode] = useState(tool.code);
  const [name, setName] = useState(tool.name);
  const [description, setDescription] = useState(tool.description ?? '');
  const [status, setStatus] = useState<ToolStatus>(tool.status);
  const [nextService, setNextService] = useState(tool.next_service_at?.slice(0, 10) ?? '');

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  const onSubmit = () => {
    if (Object.keys(errors).length > 0) return;
    onSave({
      code: code.trim(),
      name: name.trim(),
      description: description.trim() || undefined,
      status,
      next_service_at: nextService.trim() || undefined,
    });
  };

  const confirmDelete = () =>
    Alert.alert(t('Delete tool'), t('Delete "{{name}}"?', { name: tool.name }), [
      { text: t('Cancel'), style: 'cancel' },
      { text: t('Delete tool'), style: 'destructive', onPress: onDelete },
    ]);

  const token = STATUS_TOKEN[tool.status] ?? colors.faint;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View>
        <Text style={styles.h1}>{tool.name}</Text>
        <Mono size={10} color={colors.faint} letterSpacing={0.6}>{`${tool.code.toUpperCase()} · ${t('Maintenance').toUpperCase()}`}</Mono>
      </View>

      {/* Status */}
      <View style={[styles.box, { borderColor: token }]}>
        <View style={styles.statusRow}>
          <Mono size={9} color={token} letterSpacing={0.6}>{t('Status').toUpperCase()}</Mono>
          <Text style={[styles.statusValue, { color: token }]}>{t(STATUS_LABEL[tool.status])}</Text>
        </View>
      </View>

      {/* Details */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Details').toUpperCase()}</Mono>
        <View style={styles.box}>
          <KV label={t('Workstation type')} value={tool.workstation_type?.name ?? '—'} />
          <KV label={t('Next service due')} value={safeDate(tool.next_service_at) || '—'} divider mono />
          <KV label={t('Code')} value={tool.code} divider mono />
          {tool.description ? <KV label={t('Description')} value={tool.description} divider /> : null}
        </View>
      </View>

      {/* Maintenance history */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{`${t('Maintenance history').toUpperCase()} · ${events.length}`}</Mono>
        <View style={styles.box}>
          {events.length === 0 ? (
            <View style={{ padding: 14 }}>
              <Text style={styles.empty}>{t('No maintenance events yet.')}</Text>
            </View>
          ) : (
            events.map((e, i) => (
              <View key={e.id} style={[styles.historyRow, i < events.length - 1 ? styles.rowDivider : null]}>
                <Mono size={10} color={colors.faint} style={{ width: 78 }}>{safeDate(e.scheduled_at ?? e.started_at ?? e.completed_at) || '—'}</Mono>
                <View style={{ flex: 1 }}>
                  <Text numberOfLines={2} style={styles.historyTitle}>{`${t(typeLabel(e.event_type))} · ${e.title}`}</Text>
                </View>
                <StatusPill status={e.status} />
              </View>
            ))
          )}
        </View>
      </View>

      <Button title={t('Schedule maintenance')} variant="outline" onPress={onSchedule} />

      {/* Edit */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Edit tool').toUpperCase()}</Mono>
        <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
        <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
        <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={styles.textarea} />
        <View style={{ gap: 6 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Status').toUpperCase()}</Mono>
          <Dropdown value={status} onChange={(v) => setStatus(v as ToolStatus)} options={STATUSES.map((s) => ({ value: s.value, label: t(s.label) }))} />
        </View>
        <Field label="Next service" value={nextService} onChangeText={setNextService} placeholder="YYYY-MM-DD" autoCapitalize="none" autoCorrect={false} mono />
      </View>

      <Button title={t('Save changes')} onPress={onSubmit} loading={updating} disabled={updating} />

      <Button title={t('Delete tool')} variant="danger" onPress={confirmDelete} loading={deleting} disabled={deleting} />
    </ScrollView>
  );
}

function KV({ label, value, divider, mono }: { label: string; value: string; divider?: boolean; mono?: boolean }) {
  return (
    <View style={[styles.kvRow, divider ? styles.rowDivider : null]}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6} style={{ flex: 1 }}>{label.toUpperCase()}</Mono>
      {mono ? (
        <Mono size={12} color={colors.ink} style={styles.kvValue}>{value}</Mono>
      ) : (
        <Text numberOfLines={2} style={[styles.kvValueText, styles.kvValue]}>{value}</Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md },
  statusRow: { padding: 14, gap: 4 },
  statusValue: { fontSize: 18, fontFamily: fonts.sans.native.semibold, letterSpacing: -0.3 },
  kvRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 12, paddingHorizontal: 14 },
  kvValue: { flexShrink: 1, textAlign: 'right' },
  kvValueText: { fontSize: 12.5, color: colors.ink, fontFamily: fonts.sans.native.regular },
  rowDivider: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2 },
  historyRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 12, paddingHorizontal: 14 },
  historyTitle: { fontSize: 12.5, color: colors.ink, fontFamily: fonts.sans.native.regular },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular },
  textarea: { minHeight: 80, textAlignVertical: 'top' },
});
