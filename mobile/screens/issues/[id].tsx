import { format, formatDistanceToNowStrict, parseISO } from 'date-fns';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useIssue } from '@/hooks/queries/useIssues';
import {
  useAcknowledgeIssue,
  useCloseIssue,
  useResolveIssue,
} from '@/hooks/mutations/issues';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';

interface TimelineEvent {
  /** Display time. */
  t: string;
  /** Headline (e.g. "Reported issue", "Acknowledged", "Resolved"). */
  what: string;
  /** Who did it (mono). */
  who: string;
  /** Marker dot color. */
  dot: string;
  /** Greyed-out future/pending event. */
  faint?: boolean;
}

export function IssueDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const issue = useIssue(numericId);
  const ack = useAcknowledgeIssue();
  const resolve = useResolveIssue();
  const close = useCloseIssue();

  const user = useAuthStore((s) => s.user);
  const canManage = isSupervisorOrAdmin(user);

  const [resolution, setResolution] = useState('');

  // Timeline derived from the issue's lifecycle timestamps. The backend
  // doesn't expose a per-issue activity stream (TODO(api/issue-activities)
  // — would need an `issue_activities` table for richer threads), so we
  // synthesize one from `created_at` / `acknowledged_at` / `resolved_at`.
  const timeline: TimelineEvent[] = useMemo(() => {
    const data = issue.data;
    if (!data) return [];
    const events: TimelineEvent[] = [];
    if (data.created_at) {
      events.push({
        t: fmtTime(data.created_at),
        what: 'Reported issue',
        who: data.reported_by ? `${data.reported_by.username} · Operator` : 'Operator',
        dot: palette.danger,
      });
    }
    if (data.acknowledged_at) {
      events.push({
        t: fmtTime(data.acknowledged_at),
        what: 'Acknowledged',
        who: 'Supervisor',
        dot: palette.warning,
      });
    } else if (data.status === 'OPEN') {
      events.push({
        t: '—',
        what: 'Awaiting acknowledgement',
        who: 'No supervisor on line yet',
        dot: '#cfccc4',
        faint: true,
      });
    }
    if (data.resolved_at) {
      events.push({
        t: fmtTime(data.resolved_at),
        what: 'Resolved',
        who: 'Supervisor',
        dot: palette.success,
      });
    } else if (data.status === 'ACKNOWLEDGED') {
      events.push({
        t: '—',
        what: 'Pending resolution',
        who: 'Action required',
        dot: '#cfccc4',
        faint: true,
      });
    }
    return events;
  }, [issue.data, palette.danger, palette.warning, palette.success]);

  if (issue.isLoading) return <LoadingState />;
  if (issue.isError || !issue.data) return <ErrorState error={issue.error} onRetry={issue.refetch} />;

  const data = issue.data;
  const isBlocking = data.issue_type?.is_blocking;
  const accent = isBlocking ? palette.danger : palette.warning;
  const openedAgo = data.created_at
    ? (() => {
        try {
          return formatDistanceToNowStrict(parseISO(data.created_at));
        } catch {
          return null;
        }
      })()
    : null;

  const onAck = () =>
    ack.mutate(numericId, { onError: (e: Error) => Alert.alert('Failed', e.message) });
  const onResolve = () =>
    resolve.mutate(
      { id: numericId, resolutionNotes: resolution || undefined },
      {
        onSuccess: () => setResolution(''),
        onError: (e: Error) => Alert.alert('Failed', e.message),
      },
    );
  const onClose = () =>
    close.mutate(numericId, { onError: (e: Error) => Alert.alert('Failed', e.message) });

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title={`Issue #${data.id}`}
        subtitle={(data.issue_type?.name ?? '').toUpperCase()}
      />
      <ScrollView
        style={{ flex: 1, backgroundColor: palette.background }}
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled">
        {/* Hero */}
      <View>
        <View style={styles.tagsRow}>
          {isBlocking ? (
            <View style={[styles.severityTag, { backgroundColor: palette.danger }]}>
              <Mono size={9.5} color="#fff" weight="700" letterSpacing={0.6}>BLOCKING</Mono>
            </View>
          ) : null}
          <Mono size={11} color={palette.textFaint}>
            {[data.line?.name, data.work_order?.order_no].filter(Boolean).join(' · ').toUpperCase()}
          </Mono>
        </View>
        <Text style={[styles.title, { color: palette.text }]}>
          {data.issue_type?.name ?? `Issue #${data.id}`}
        </Text>
        {data.description ? (
          <Text style={[styles.description, { color: palette.textMuted }]}>
            {data.description}
          </Text>
        ) : null}
        <View style={styles.statusRow}>
          <View
            style={[
              styles.openPill,
              {
                backgroundColor: data.status === 'OPEN' ? palette.dangerSoft : palette.surfaceAlt,
              },
            ]}>
            <View
              style={[
                styles.openDot,
                { backgroundColor: data.status === 'OPEN' ? palette.danger : palette.textFaint },
              ]}
            />
            <Mono
              size={10.5}
              color={data.status === 'OPEN' ? palette.danger : palette.textMuted}
              weight="700"
              letterSpacing={0.5}>
              {data.status}{openedAgo ? ` · ${openedAgo.toUpperCase()}` : ''}
            </Mono>
          </View>
          {isBlocking ? (
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.6}>
              HIGH SEVERITY
            </Mono>
          ) : null}
        </View>
      </View>

      {/* Action bar */}
      {canManage && data.status === 'OPEN' ? (
        <View style={styles.actionBar}>
          <Button
            title="Acknowledge"
            variant="secondary"
            style={{ flex: 1 }}
            onPress={onAck}
            loading={ack.isPending}
          />
          <Pressable
            onPress={onAck}
            disabled={ack.isPending}
            style={({ pressed }) => [
              styles.iconBtn,
              { borderColor: palette.border, opacity: pressed ? 0.6 : 1 },
            ]}>
            <FontAwesome name="check" size={18} color={palette.success} />
          </Pressable>
        </View>
      ) : null}

      {/* Timeline */}
      <View>
        <SectionLabel>Timeline</SectionLabel>
        <Card style={{ padding: 14, gap: 0 }}>
          {timeline.map((e, i) => (
            <View
              key={i}
              style={[
                styles.tlRow,
                i < timeline.length - 1 ? { paddingBottom: 14 } : null,
              ]}>
              <View style={styles.tlCol}>
                <View style={[styles.tlDot, { backgroundColor: e.dot }]} />
                {i < timeline.length - 1 ? (
                  <View style={[styles.tlLine, { backgroundColor: palette.border }]} />
                ) : null}
              </View>
              <View style={{ flex: 1, paddingBottom: 4 }}>
                <Mono
                  size={11}
                  color={e.faint ? palette.textFaint : palette.textMuted}>
                  {e.t}
                </Mono>
                <Text
                  style={{
                    fontSize: 13,
                    fontWeight: e.faint ? '400' : '600',
                    color: e.faint ? palette.textFaint : palette.text,
                    marginTop: 3,
                  }}>
                  {e.what}
                </Text>
                <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 3 }}>
                  {e.who}
                </Mono>
              </View>
            </View>
          ))}
        </Card>
      </View>

      {/* Linked WO */}
      {data.work_order ? (
        <View>
          <SectionLabel>Linked work order</SectionLabel>
          <Pressable
            onPress={() => router.push(`/work-orders/${data.work_order!.id}` as never)}
            style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
            <Card style={{ padding: 0, overflow: 'hidden' }}>
              <View style={styles.linkedRow}>
                <View
                  style={[
                    styles.linkedRail,
                    { backgroundColor: data.status === 'OPEN' ? palette.danger : palette.success },
                  ]}
                />
                <View style={{ flex: 1, padding: 14 }}>
                  <Mono size={10.5} color={palette.textFaint} letterSpacing={0.5}>
                    {data.work_order.order_no}
                  </Mono>
                  <Text
                    style={{
                      fontSize: 13,
                      fontWeight: '600',
                      color: palette.text,
                      marginTop: 3,
                    }}>
                    {data.work_order.product_type?.name ?? 'Work order'}
                  </Text>
                  <Mono
                    size={10.5}
                    color={data.status === 'OPEN' ? palette.danger : palette.textMuted}
                    style={{ marginTop: 4 }}>
                    {`● ${(data.work_order.status ?? '').toUpperCase()}`}
                  </Mono>
                </View>
                <View style={{ paddingRight: 14 }}>
                  <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
                </View>
              </View>
            </Card>
          </Pressable>
        </View>
      ) : null}

      {/* Resolve form (Supervisor/Admin only, while OPEN/ACKNOWLEDGED) */}
      {canManage && (data.status === 'OPEN' || data.status === 'ACKNOWLEDGED') ? (
        <Card style={{ gap: 10 }}>
          <SectionLabel>Resolve</SectionLabel>
          <Field
            label="Resolution notes"
            value={resolution}
            onChangeText={setResolution}
            multiline
            numberOfLines={3}
            style={{ minHeight: 80, textAlignVertical: 'top' }}
          />
          <Button
            title="Mark resolved"
            variant="success"
            onPress={onResolve}
            loading={resolve.isPending}
          />
        </Card>
      ) : null}

      {canManage && data.status === 'RESOLVED' ? (
        <Button title="Close" onPress={onClose} loading={close.isPending} variant="outline" />
      ) : null}

      {data.resolution_notes ? (
        <View>
          <SectionLabel>Resolution notes</SectionLabel>
          <Card>
            <Text style={{ color: palette.text, fontSize: 14, lineHeight: 20 }}>
              {data.resolution_notes}
            </Text>
          </Card>
        </View>
      ) : null}
      </ScrollView>
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
  container: { padding: 18, gap: 14 },
  tagsRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  severityTag: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 4 },
  title: { fontSize: 22, fontWeight: '700', letterSpacing: -0.3, marginTop: 8 },
  description: { fontSize: 13, lineHeight: 19, marginTop: 8 },
  statusRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 12 },
  openPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
  },
  openDot: { width: 6, height: 6, borderRadius: 3 },
  actionBar: { flexDirection: 'row', gap: 8, alignItems: 'center' },
  iconBtn: {
    width: 48,
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tlRow: { flexDirection: 'row', gap: 10 },
  tlCol: { width: 12, alignItems: 'center' },
  tlDot: { width: 10, height: 10, borderRadius: 5, marginTop: 4 },
  tlLine: { width: 1, flex: 1, marginTop: 4 },
  linkedRow: { flexDirection: 'row', alignItems: 'center' },
  linkedRail: { width: 4, alignSelf: 'stretch' },
});
