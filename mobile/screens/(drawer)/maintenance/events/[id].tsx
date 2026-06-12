import { format, formatDistanceToNowStrict, parseISO } from 'date-fns';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useCancelMaintenanceEvent,
  useCompleteMaintenanceEvent,
  useDeleteMaintenanceEvent,
  useMaintenanceEvent,
  useMaintenanceEvents,
  useStartMaintenanceEvent,
} from '@/hooks/queries/useMaintenance';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { MaintenanceEventType } from '@/api/maintenance';

const TYPE_TONE: Record<MaintenanceEventType, { bg: string; fg: string; label: string }> = {
  planned:    { bg: '#1d4ed8', fg: '#fff', label: 'PLANNED' },
  corrective: { bg: '#dc2626', fg: '#fff', label: 'CORRECTIVE' },
  inspection: { bg: '#7c3aed', fg: '#fff', label: 'INSPECTION' },
};

export function MaintenanceEventDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = useMaintenanceEvent(numericId);
  const startMutation = useStartMaintenanceEvent();
  const completeMutation = useCompleteMaintenanceEvent();
  const cancelMutation = useCancelMaintenanceEvent();
  const deleteMutation = useDeleteMaintenanceEvent();

  const user = useAuthStore((s) => s.user);
  const isAdminOrSup = isSupervisorOrAdmin(user);

  const [resolutionNotes, setResolutionNotes] = useState('');
  const [actualCost, setActualCost] = useState('');
  const [currency, setCurrency] = useState('EUR');

  // Tool history — completed events scoped to the same tool. Keeps the list
  // small (5 most recent) so the screen stays glanceable.
  const toolId = query.data?.tool?.id ?? query.data?.tool_id;
  const historyQ = useMaintenanceEvents(toolId ? { tool_id: toolId, status: 'completed' } : {});

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const e = query.data;

  const canTransition = isAdminOrSup || e.assigned_to_id === user?.id;
  const canDelete = user?.roles?.some((r) => r.name === 'Admin') ?? false;

  const tone = TYPE_TONE[e.event_type as MaintenanceEventType] ?? TYPE_TONE.planned;
  const startedAgo = e.started_at
    ? (() => {
        try {
          return formatDistanceToNowStrict(parseISO(e.started_at));
        } catch {
          return null;
        }
      })()
    : null;

  const onStart = () =>
    startMutation.mutate(e.id, { onError: (err: Error) => Alert.alert('Failed', err.message) });
  const onComplete = () =>
    completeMutation.mutate(
      {
        id: e.id,
        resolution_notes: resolutionNotes || undefined,
        actual_cost: actualCost ? Number(actualCost) : undefined,
        currency: currency || undefined,
      },
      { onError: (err: Error) => Alert.alert('Failed', err.message) },
    );
  const onCancel = () =>
    cancelMutation.mutate(e.id, { onError: (err: Error) => Alert.alert('Failed', err.message) });

  const startedTime = e.started_at ? fmtTime(e.started_at) : '—';
  const history = (historyQ.data?.data ?? []).filter((h) => h.id !== e.id).slice(0, 5);

  return (
    <DetailScreen>
      {/* Hero */}
      <View>
        <View style={styles.tagsRow}>
          <View style={[styles.typeTag, { backgroundColor: tone.bg }]}>
            <Mono size={9.5} color={tone.fg} weight="700" letterSpacing={0.6}>
              {tone.label}
            </Mono>
          </View>
          <Mono size={11} color={palette.textFaint}>
            {[e.line?.name, e.workstation?.name, e.tool?.code]
              .filter(Boolean)
              .join(' · ')
              .toUpperCase()}
          </Mono>
        </View>
        <Text style={[styles.title, { color: palette.text }]}>{e.title}</Text>
        {e.status === 'in_progress' && startedAgo ? (
          <View style={[styles.statusPill, { backgroundColor: '#fbe9c8' }]}>
            <View style={[styles.statusDot, { backgroundColor: '#f5a524' }]} />
            <Mono size={11} color={'#8a5a0e'} weight="700" letterSpacing={0.5}>
              IN PROGRESS · {startedAgo.toUpperCase()}
            </Mono>
          </View>
        ) : null}
      </View>

      {/* 3-up meta grid */}
      <View style={styles.metaGrid}>
        <MetaCell label="TOOL" value={e.tool?.code ?? '—'} />
        <MetaCell label="ASSIGNED" value={e.assigned_to_id != null ? `#${e.assigned_to_id}` : '—'} />
        <MetaCell label="STARTED" value={startedTime} />
      </View>

      {/* Action bar */}
      {canTransition && e.status === 'pending' ? (
        <Button
          title="Start"
          variant="success"
          loading={startMutation.isPending}
          onPress={onStart}
          leftIcon={<FontAwesome name="play" size={13} color="#fff" />}
        />
      ) : null}
      {canTransition && e.status === 'in_progress' ? (
        <View style={styles.actionBar}>
          <Button
            title="COMPLETE EVENT"
            variant="success"
            style={{ flex: 2 }}
            loading={completeMutation.isPending}
            onPress={onComplete}
            leftIcon={<FontAwesome name="check" size={13} color="#fff" />}
          />
          <Button
            title="CANCEL"
            variant="outline"
            style={{ flex: 1 }}
            loading={cancelMutation.isPending}
            onPress={() =>
              Alert.alert('Cancel event', 'Mark this event as cancelled?', [
                { text: 'Back', style: 'cancel' },
                { text: 'Cancel event', style: 'destructive', onPress: onCancel },
              ])
            }
          />
        </View>
      ) : null}

      {canTransition && e.status === 'in_progress' ? (
        <Card style={{ gap: 12 }}>
          <SectionLabel>Completion details</SectionLabel>
          <Field
            label="Resolution notes"
            value={resolutionNotes}
            onChangeText={setResolutionNotes}
            multiline
            numberOfLines={3}
            style={{ minHeight: 80, textAlignVertical: 'top' }}
          />
          <Field
            label="Actual cost (optional)"
            value={actualCost}
            onChangeText={setActualCost}
            keyboardType="decimal-pad"
          />
          <Field
            label="Currency"
            value={currency}
            onChangeText={setCurrency}
            autoCapitalize="characters"
          />
        </Card>
      ) : null}

      {/* Notes */}
      {e.description ? (
        <View>
          <SectionLabel>Notes</SectionLabel>
          <Card>
            <Text style={{ color: palette.textMuted, fontSize: 13, lineHeight: 20 }}>
              {e.description}
            </Text>
          </Card>
        </View>
      ) : null}

      {/* Tool history */}
      {e.tool ? (
        <View>
          <SectionLabel
            right={
              <Mono size={11} color={palette.textFaint}>
                {history.length === 0 ? '—' : `LAST ${history.length}`}
              </Mono>
            }>
            {`History · ${e.tool.code}`}
          </SectionLabel>
          {history.length === 0 ? (
            <Card>
              <Mono size={11} color={palette.textFaint}>NO PREVIOUS EVENTS</Mono>
            </Card>
          ) : (
            <Card style={{ padding: 0, overflow: 'hidden' }}>
              {history.map((h, i) => (
                <View
                  key={h.id}
                  style={[
                    styles.historyRow,
                    i < history.length - 1
                      ? {
                          borderBottomColor: palette.border,
                          borderBottomWidth: StyleSheet.hairlineWidth,
                        }
                      : null,
                  ]}>
                  <Mono size={10.5} color={palette.textFaint} style={{ width: 84 }}>
                    {(h.completed_at ?? h.scheduled_at ?? '').slice(0, 10)}
                  </Mono>
                  <Text
                    style={{ flex: 1, color: palette.text, fontSize: 12 }}
                    numberOfLines={1}>
                    {h.event_type.charAt(0).toUpperCase() + h.event_type.slice(1)} · {h.title}
                  </Text>
                  <Mono size={10.5} color={palette.textMuted}>
                    {(h.assigned_to_id != null ? `#${h.assigned_to_id}` : '—')}
                  </Mono>
                </View>
              ))}
            </Card>
          )}
        </View>
      ) : null}

      {canDelete && e.status === 'pending' ? (
        <DangerZone
          deleteLabel="Delete event"
          deleteConfirmTitle="Delete event"
          deleteConfirmMessage={`Delete "${e.title}"?`}
          deleteLoading={deleteMutation.isPending}
          onDelete={() =>
            deleteMutation.mutate(e.id, {
              onSuccess: () => router.back(),
              onError: (err: Error) => Alert.alert('Failed', err.message),
            })
          }
        />
      ) : null}
    </DetailScreen>
  );
}

function MetaCell({ label, value }: { label: string; value: string }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View
      style={[
        styles.metaCell,
        { backgroundColor: palette.surface, borderColor: palette.border },
      ]}>
      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>
        {label}
      </Mono>
      <Mono size={12} color={palette.text} weight="700" style={{ marginTop: 4 }}>
        {value}
      </Mono>
    </View>
  );
}

function fmtTime(iso: string): string {
  try {
    return format(parseISO(iso), 'HH:mm');
  } catch {
    return iso.slice(11, 16);
  }
}

const styles = StyleSheet.create({
  tagsRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  typeTag: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 4 },
  title: { fontSize: 22, fontWeight: '700', letterSpacing: -0.3, marginTop: 8 },
  statusPill: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
    marginTop: 12,
  },
  statusDot: { width: 8, height: 8, borderRadius: 4 },
  metaGrid: { flexDirection: 'row', gap: 8 },
  metaCell: {
    flex: 1,
    padding: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  actionBar: { flexDirection: 'row', gap: 8 },
  historyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
  },
});
