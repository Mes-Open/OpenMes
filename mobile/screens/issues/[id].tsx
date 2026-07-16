import { format, formatDistanceToNowStrict, parseISO } from 'date-fns';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
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
  const { t } = useTranslation();

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
        what: t('Reported issue'),
        who: data.reported_by ? `${data.reported_by.username} · ${t('Operator')}` : t('Operator'),
        dot: colors.blocked,
      });
    }
    if (data.acknowledged_at) {
      events.push({
        t: fmtTime(data.acknowledged_at),
        what: t('Acknowledged'),
        who: t('Supervisor'),
        dot: colors.downtime,
      });
    } else if (data.status === 'OPEN') {
      events.push({
        t: '—',
        what: t('Awaiting acknowledgement'),
        who: t('No supervisor on line yet'),
        dot: colors.faintest,
        faint: true,
      });
    }
    if (data.resolved_at) {
      events.push({
        t: fmtTime(data.resolved_at),
        what: t('Resolved'),
        who: t('Supervisor'),
        dot: colors.done,
      });
    } else if (data.status === 'ACKNOWLEDGED') {
      events.push({
        t: '—',
        what: t('Pending resolution'),
        who: t('Action required'),
        dot: colors.faintest,
        faint: true,
      });
    }
    return events;
  }, [issue.data, t]);

  if (issue.isLoading) return <LoadingState />;
  if (issue.isError || !issue.data) return <ErrorState error={issue.error} onRetry={issue.refetch} />;

  const data = issue.data;
  const isBlocking = data.issue_type?.is_blocking;
  const openedAgo = data.created_at
    ? (() => {
        try {
          return formatDistanceToNowStrict(parseISO(data.created_at));
        } catch {
          return null;
        }
      })()
    : null;

  const onAck = () => ack.mutate(numericId, { onError: (e: Error) => Alert.alert('Failed', e.message) });
  const onResolve = () =>
    resolve.mutate(
      { id: numericId, resolutionNotes: resolution || undefined },
      {
        onSuccess: () => setResolution(''),
        onError: (e: Error) => Alert.alert('Failed', e.message),
      },
    );
  const onClose = () => close.mutate(numericId, { onError: (e: Error) => Alert.alert('Failed', e.message) });

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
      {/* Hero */}
      <View>
        <View style={styles.tagsRow}>
          {isBlocking ? (
            <View style={[styles.severityTag, { backgroundColor: colors.blocked }]}>
              <Mono size={9.5} color="#fff" weight="700" letterSpacing={0.6}>{t('Blocking').toUpperCase()}</Mono>
            </View>
          ) : null}
          <Mono size={11} color={colors.faint}>
            {[data.line?.name, data.work_order?.order_no].filter(Boolean).join(' · ').toUpperCase()}
          </Mono>
        </View>
        <Mono size={10} color={colors.faint} letterSpacing={1.2} style={{ marginTop: 8 }}>{`ISSUE #${data.id}`}</Mono>
        <Text style={styles.title}>{data.issue_type?.name ?? `${t('Issue')} #${data.id}`}</Text>
        {data.description ? <Text style={styles.description}>{data.description}</Text> : null}
        <View style={styles.statusRow}>
          <StatusPill status={data.status} />
          {openedAgo ? <Mono size={10.5} color={colors.faint} letterSpacing={0.5}>{openedAgo.toUpperCase()}</Mono> : null}
          {isBlocking ? <Mono size={10.5} color={colors.faint} letterSpacing={0.6}>{t('High severity').toUpperCase()}</Mono> : null}
        </View>
      </View>

      {/* Action bar */}
      {canManage && data.status === 'OPEN' ? (
        <View style={styles.actionBar}>
          <Button title={t('Acknowledge')} variant="secondary" style={{ flex: 1 }} onPress={onAck} loading={ack.isPending} />
          <Pressable
            onPress={onAck}
            disabled={ack.isPending}
            style={({ pressed }) => [styles.iconBtn, { opacity: pressed ? 0.6 : 1 }]}>
            <FontAwesome name="check" size={18} color={colors.done} />
          </Pressable>
        </View>
      ) : null}

      {/* Timeline */}
      <View>
        <SectionLabel>Timeline</SectionLabel>
        <View style={[styles.card, { padding: 14 }]}>
          {timeline.map((e, i) => (
            <View key={i} style={[styles.tlRow, i < timeline.length - 1 ? { paddingBottom: 14 } : null]}>
              <View style={styles.tlCol}>
                <View style={[styles.tlDot, { backgroundColor: e.dot }]} />
                {i < timeline.length - 1 ? <View style={styles.tlLine} /> : null}
              </View>
              <View style={{ flex: 1, paddingBottom: 4 }}>
                <Mono size={11} color={e.faint ? colors.faint : colors.muted}>{e.t}</Mono>
                <Text
                  style={{
                    fontSize: 13,
                    fontFamily: e.faint ? fonts.sans.native.regular : fonts.sans.native.medium,
                    color: e.faint ? colors.faint : colors.ink,
                    marginTop: 3,
                  }}>
                  {e.what}
                </Text>
                <Mono size={10.5} color={colors.faint} style={{ marginTop: 3 }}>{e.who}</Mono>
              </View>
            </View>
          ))}
        </View>
      </View>

      {/* Linked WO */}
      {data.work_order ? (
        <View>
          <SectionLabel>Linked work order</SectionLabel>
          <Pressable
            onPress={() => router.push(`/work-orders/${data.work_order!.id}` as never)}
            style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
            <View style={[styles.card, { padding: 0, overflow: 'hidden' }]}>
              <View style={styles.linkedRow}>
                <View style={[styles.linkedRail, { backgroundColor: data.status === 'OPEN' ? colors.blocked : colors.done }]} />
                <View style={{ flex: 1, padding: 14 }}>
                  <Mono size={10.5} color={colors.faint} letterSpacing={0.5}>{data.work_order.order_no}</Mono>
                  <Text style={styles.linkedTitle}>{data.work_order.product_type?.name ?? t('Work order')}</Text>
                  <Mono size={10.5} color={data.status === 'OPEN' ? colors.blocked : colors.muted} style={{ marginTop: 4 }}>
                    {`● ${(data.work_order.status ?? '').toUpperCase()}`}
                  </Mono>
                </View>
                <View style={{ paddingRight: 14 }}>
                  <FontAwesome name="chevron-right" size={12} color={colors.faint} />
                </View>
              </View>
            </View>
          </Pressable>
        </View>
      ) : null}

      {/* Resolve form (Supervisor/Admin only, while OPEN/ACKNOWLEDGED) */}
      {canManage && (data.status === 'OPEN' || data.status === 'ACKNOWLEDGED') ? (
        <View style={[styles.card, { padding: 14, gap: 10 }]}>
          <SectionLabel>Resolve</SectionLabel>
          <Field
            label="Resolution notes"
            value={resolution}
            onChangeText={setResolution}
            multiline
            numberOfLines={3}
            style={{ minHeight: 80, textAlignVertical: 'top' }}
          />
          <Button title={t('Mark resolved')} variant="success" onPress={onResolve} loading={resolve.isPending} />
        </View>
      ) : null}

      {canManage && data.status === 'RESOLVED' ? (
        <Button title={t('Close')} onPress={onClose} loading={close.isPending} variant="outline" />
      ) : null}

      {data.resolution_notes ? (
        <View>
          <SectionLabel>Resolution notes</SectionLabel>
          <View style={[styles.card, { padding: 14 }]}>
            <Text style={styles.notes}>{data.resolution_notes}</Text>
          </View>
        </View>
      ) : null}
    </ScrollView>
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
  screen: { flex: 1, backgroundColor: colors.bg },
  container: { padding: 18, gap: 14 },
  card: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md },
  tagsRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  severityTag: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: radius.sm },
  title: { fontSize: 22, fontFamily: fonts.sans.native.semibold, letterSpacing: -0.4, color: colors.ink, marginTop: 4 },
  description: { fontSize: 13, lineHeight: 19, marginTop: 8, color: colors.muted, fontFamily: fonts.sans.native.regular },
  statusRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 12 },
  actionBar: { flexDirection: 'row', gap: 8, alignItems: 'center' },
  iconBtn: { width: 48, height: 48, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, alignItems: 'center', justifyContent: 'center' },
  tlRow: { flexDirection: 'row', gap: 10 },
  tlCol: { width: 12, alignItems: 'center' },
  tlDot: { width: 10, height: 10, borderRadius: 5, marginTop: 4 },
  tlLine: { width: 1, flex: 1, marginTop: 4, backgroundColor: colors.line },
  linkedRow: { flexDirection: 'row', alignItems: 'center' },
  linkedRail: { width: 4, alignSelf: 'stretch' },
  linkedTitle: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink, marginTop: 3 },
  notes: { color: colors.ink, fontSize: 14, lineHeight: 20, fontFamily: fonts.sans.native.regular },
});
