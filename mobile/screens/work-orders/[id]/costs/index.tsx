/**
 * Work-order costs / attachments / anomalies — a single tabbed view. Each tab
 * fetches its own data and a summary box sits above the list. Re-skin to the
 * shared token/box idiom; all hooks, mutations and navigation targets unchanged.
 */
import { FontAwesome } from '@expo/vector-icons';
import * as DocumentPicker from 'expo-document-picker';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useMemo, useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { attachmentDownloadUrl } from '@/api/woExtras';
import {
  useAdditionalCosts,
  useAttachments,
  useDeleteAdditionalCost,
  useDeleteAttachment,
  useDeleteProductionAnomaly,
  useProductionAnomalies,
  useUploadAttachment,
} from '@/hooks/queries/useWoExtras';
import { useWorkOrder } from '@/hooks/queries/useWorkOrders';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';

type Tab = 'costs' | 'attachments' | 'anomalies';
const ENTITY_TYPE = 'work_order';

export function CostsList() {
  const { id, tab: initialTab } = useLocalSearchParams<{ id: string; tab?: Tab }>();
  const woId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const [tab, setTab] = useState<Tab>(initialTab ?? 'costs');

  const wo = useWorkOrder(woId);
  const costsQ = useAdditionalCosts(woId);
  const anomaliesQ = useProductionAnomalies({ work_order_id: woId });
  const attachmentsQ = useAttachments(ENTITY_TYPE, woId);

  const user = useAuthStore((s) => s.user);
  const canManage = isSupervisorOrAdmin(user);
  const userId = user?.id;

  const costs = costsQ.data ?? [];
  const anomalies = anomaliesQ.data?.data ?? [];
  const attachments = attachmentsQ.data ?? [];

  const total = costs.reduce((s, c) => s + Number(c.amount), 0);
  const currency = costs[0]?.currency ?? '';

  const counts = useMemo(
    () => ({
      costs: costs.length,
      attachments: attachments.length,
      anomalies: anomalies.length,
    }),
    [costs.length, attachments.length, anomalies.length],
  );

  const isLoading = wo.isLoading;

  const uploadMutation = useUploadAttachment();
  const onPickAndUpload = async () => {
    const result = await DocumentPicker.getDocumentAsync({ multiple: false, copyToCacheDirectory: true });
    if (result.canceled) return;
    const file = result.assets?.[0];
    if (!file) return;
    uploadMutation.mutate(
      {
        entityType: ENTITY_TYPE,
        entityId: woId,
        uri: file.uri,
        name: file.name,
        mimeType: file.mimeType ?? undefined,
      },
      { onError: (e: Error) => Alert.alert(t('Upload failed'), e.message) },
    );
  };

  const onAdd = () => {
    if (tab === 'costs') router.push(`/work-orders/${woId}/costs/new` as never);
    else if (tab === 'anomalies') router.push(`/work-orders/${woId}/anomalies/new` as never);
    else if (tab === 'attachments') void onPickAndUpload();
  };
  const addLabel = tab === 'costs' ? t('New cost') : tab === 'anomalies' ? t('New anomaly') : t('Upload file');

  if (isLoading) return <LoadingState />;
  if (wo.isError || !wo.data) return <ErrorState error={wo.error} onRetry={wo.refetch} />;

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1} numberOfLines={1}>{wo.data.order_no}</Text>
        <View style={{ flex: 1 }} />
        {canManage ? <Button title={addLabel} size="sm" onPress={onAdd} loading={uploadMutation.isPending} /> : null}
      </View>

      <ScrollView
        style={styles.screen}
        contentContainerStyle={styles.scroll}
        refreshControl={
          <RefreshControl
            tintColor={colors.accent}
            refreshing={costsQ.isFetching || anomaliesQ.isFetching || attachmentsQ.isFetching}
            onRefresh={() => {
              costsQ.refetch();
              anomaliesQ.refetch();
              attachmentsQ.refetch();
            }}
          />
        }>
        {/* Tabs */}
        <View style={styles.toggle}>
          {(
            [
              { id: 'costs', label: t('Costs') },
              { id: 'attachments', label: t('Attachments') },
              { id: 'anomalies', label: t('Anomalies') },
            ] as const
          ).map((tb) => {
            const active = tb.id === tab;
            return (
              <Pressable
                key={tb.id}
                onPress={() => setTab(tb.id)}
                style={[styles.toggleBtn, active && styles.toggleBtnActive]}>
                <Text style={[styles.toggleText, active && styles.toggleTextActive]}>{tb.label}</Text>
              </Pressable>
            );
          })}
        </View>

        {/* Summary */}
        {tab === 'costs' ? (
          <View style={styles.box}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Total additional costs').toUpperCase()}</Mono>
            <Mono size={28} color={colors.ink} weight="600" style={{ marginTop: 6 }}>
              {total.toFixed(2)}{currency ? <Mono size={14} color={colors.faint}> {currency}</Mono> : null}
            </Mono>
            <Mono size={11} color={colors.muted} style={{ marginTop: 4 }}>
              {counts.costs} {counts.costs === 1 ? t('entry') : t('entries')}
            </Mono>
          </View>
        ) : (
          <View style={styles.box}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>
              {tab === 'attachments' ? t('Files').toUpperCase() : t('Anomalies').toUpperCase()}
            </Mono>
            <Mono size={28} color={colors.ink} weight="600" style={{ marginTop: 6 }}>
              {tab === 'attachments' ? counts.attachments : counts.anomalies}
            </Mono>
            {tab === 'anomalies' ? (
              <Mono size={11} color={colors.muted} style={{ marginTop: 4 }}>
                {anomalies.filter((a) => a.status === 'draft').length} {t('draft')} · {anomalies.filter((a) => a.status === 'processed').length} {t('processed')}
              </Mono>
            ) : null}
          </View>
        )}

        {/* Tab content */}
        {tab === 'costs' ? (
          <CostsTab
            items={costs}
            canManage={canManage}
            isLoading={costsQ.isLoading}
            isError={costsQ.isError}
            error={costsQ.error}
            onRefresh={costsQ.refetch}
          />
        ) : tab === 'attachments' ? (
          <AttachmentsTab
            items={attachments}
            isLoading={attachmentsQ.isLoading}
            isError={attachmentsQ.isError}
            error={attachmentsQ.error}
            onRefresh={attachmentsQ.refetch}
            uploading={uploadMutation.isPending}
            onUpload={onPickAndUpload}
            userId={userId}
            canManage={canManage}
          />
        ) : (
          <AnomaliesTab
            items={anomalies}
            isLoading={anomaliesQ.isLoading}
            isError={anomaliesQ.isError}
            error={anomaliesQ.error}
            onRefresh={anomaliesQ.refetch}
          />
        )}
      </ScrollView>
    </View>
  );
}

// ── tabs ────────────────────────────────────────────────────────────────────

function CostsTab({
  items,
  canManage,
  isLoading,
  isError,
  error,
  onRefresh,
}: {
  items: NonNullable<ReturnType<typeof useAdditionalCosts>['data']>;
  canManage: boolean;
  isLoading: boolean;
  isError: boolean;
  error: unknown;
  onRefresh: () => void;
}) {
  const { t } = useTranslation();
  const deleteMutation = useDeleteAdditionalCost();

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState error={error} onRetry={onRefresh} />;
  if (items.length === 0) return <EmptyState title={t('No additional costs')} subtitle={t('Tap Add to record one.')} />;

  return (
    <View style={styles.listBox}>
      {items.map((item, i) => (
        <View key={item.id} style={[styles.rowItem, i < items.length - 1 ? styles.rowBorder : null]}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={styles.rowTitle} numberOfLines={1}>{item.description}</Text>
            <Mono size={10} color={colors.faint} letterSpacing={0.5} style={{ marginTop: 3 }}>
              {(item.cost_source?.name ?? '—').toUpperCase()}
              {item.created_at ? ` · ${item.created_at.slice(0, 10)}` : ''}
            </Mono>
          </View>
          <Mono size={13} color={colors.ink} weight="700">{Number(item.amount).toFixed(2)}</Mono>
          {canManage ? (
            <Pressable
              onPress={() =>
                Alert.alert(t('Delete cost?'), item.description, [
                  { text: t('Cancel'), style: 'cancel' },
                  { text: t('Delete'), style: 'destructive', onPress: () => deleteMutation.mutate(item.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }) },
                ])
              }
              hitSlop={6}
              style={({ pressed }) => [styles.iconBtn, { opacity: pressed ? 0.6 : 1 }]}>
              <FontAwesome name="trash-o" size={15} color={colors.blocked} />
            </Pressable>
          ) : null}
        </View>
      ))}
    </View>
  );
}

function AttachmentsTab({
  items,
  isLoading,
  isError,
  error,
  onRefresh,
  uploading,
  onUpload,
  userId,
  canManage,
}: {
  items: NonNullable<ReturnType<typeof useAttachments>['data']>;
  isLoading: boolean;
  isError: boolean;
  error: unknown;
  onRefresh: () => void;
  uploading: boolean;
  onUpload: () => void;
  userId: number | undefined;
  canManage: boolean;
}) {
  const { t } = useTranslation();
  const deleteMutation = useDeleteAttachment();

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState error={error} onRetry={onRefresh} />;

  return (
    <View style={{ gap: 10 }}>
      <Pressable
        onPress={onUpload}
        disabled={uploading}
        style={({ pressed }) => [styles.uploadBtn, { opacity: pressed ? 0.7 : 1 }]}>
        <FontAwesome name="cloud-upload" size={14} color={colors.ink} />
        <Mono size={11} color={colors.ink} weight="700" letterSpacing={0.5}>
          {uploading ? t('Uploading…').toUpperCase() : t('Upload file').toUpperCase()}
        </Mono>
      </Pressable>
      {items.length === 0 ? (
        <EmptyState title={t('No attachments')} />
      ) : (
        <View style={styles.listBox}>
          {items.map((item, i) => {
            const canDelete = canManage || item.uploaded_by_id === userId;
            return (
              <View key={item.id} style={[styles.rowItem, i < items.length - 1 ? styles.rowBorder : null]}>
                <Pressable
                  onPress={() => WebBrowser.openBrowserAsync(attachmentDownloadUrl(item.id))}
                  style={{ flex: 1, minWidth: 0 }}>
                  <Text style={styles.rowTitle} numberOfLines={1}>{item.original_name}</Text>
                  <Mono size={10} color={colors.faint} style={{ marginTop: 3 }}>
                    {humanSize(item.file_size)}
                    {item.uploaded_by ? ` · ${item.uploaded_by.username.toUpperCase()}` : ''}
                  </Mono>
                </Pressable>
                {canDelete ? (
                  <Pressable
                    onPress={() =>
                      Alert.alert(t('Delete attachment?'), item.original_name, [
                        { text: t('Cancel'), style: 'cancel' },
                        { text: t('Delete'), style: 'destructive', onPress: () => deleteMutation.mutate(item.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }) },
                      ])
                    }
                    hitSlop={6}
                    style={({ pressed }) => [styles.iconBtn, { opacity: pressed ? 0.6 : 1 }]}>
                    <FontAwesome name="trash-o" size={15} color={colors.blocked} />
                  </Pressable>
                ) : null}
              </View>
            );
          })}
        </View>
      )}
    </View>
  );
}

function AnomaliesTab({
  items,
  isLoading,
  isError,
  error,
  onRefresh,
}: {
  items: NonNullable<ReturnType<typeof useProductionAnomalies>['data']>['data'];
  isLoading: boolean;
  isError: boolean;
  error: unknown;
  onRefresh: () => void;
}) {
  const { t } = useTranslation();
  const deleteMutation = useDeleteProductionAnomaly();

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState error={error} onRetry={onRefresh} />;
  if (items.length === 0) return <EmptyState title={t('No anomalies')} subtitle={t('Tap Add to record one.')} />;

  return (
    <View style={styles.listBox}>
      {items.map((item, i) => {
        const dev = item.deviation_pct != null ? Number(item.deviation_pct) : null;
        const devColor = dev != null && Math.abs(dev) > 10 ? colors.blocked : dev != null && Math.abs(dev) > 0 ? colors.downtime : colors.muted;
        return (
          <View key={item.id} style={[styles.anomItem, i < items.length - 1 ? styles.rowBorder : null]}>
            <View style={styles.anomHead}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono size={10} color={colors.faint}>{(item.anomaly_reason?.code ?? `ANOM-${item.id}`).toUpperCase()}</Mono>
                <Text style={styles.rowTitle} numberOfLines={1}>{item.anomaly_reason?.name ?? t('Anomaly')}</Text>
              </View>
              <StatusPill status={item.status === 'draft' ? 'PENDING' : 'DONE'} label={item.status} />
            </View>
            <View style={styles.anomMeta}>
              <Mono size={10} color={colors.faint}>{t('Planned').toUpperCase()}</Mono>
              <Mono size={11} color={colors.ink} weight="700">{String(item.planned_qty)}</Mono>
              <View style={{ width: 12 }} />
              <Mono size={10} color={colors.faint}>{t('Actual').toUpperCase()}</Mono>
              <Mono size={11} color={colors.ink} weight="700">{String(item.actual_qty)}</Mono>
              {dev != null ? (
                <>
                  <View style={{ width: 12 }} />
                  <Mono size={10} color={colors.faint}>{t('Dev').toUpperCase()}</Mono>
                  <Mono size={11} color={devColor} weight="700">{`${dev}%`}</Mono>
                </>
              ) : null}
              <View style={{ flex: 1 }} />
              <Pressable
                onPress={() =>
                  Alert.alert(t('Delete anomaly?'), item.anomaly_reason?.name ?? `ANOM-${item.id}`, [
                    { text: t('Cancel'), style: 'cancel' },
                    { text: t('Delete'), style: 'destructive', onPress: () => deleteMutation.mutate(item.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }) },
                  ])
                }
                hitSlop={6}
                style={({ pressed }) => [styles.iconBtn, { opacity: pressed ? 0.6 : 1 }]}>
                <FontAwesome name="trash-o" size={15} color={colors.blocked} />
              </Pressable>
            </View>
            {item.comment ? <Text style={styles.comment} numberOfLines={3}>{item.comment}</Text> : null}
          </View>
        );
      })}
    </View>
  );
}

function humanSize(bytes?: number | null): string {
  if (!bytes) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1_048_576) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1_048_576).toFixed(1)} MB`;
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  scroll: { padding: 14, gap: 14, paddingBottom: 32 },
  toggle: { flexDirection: 'row', borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, overflow: 'hidden' },
  toggleBtn: { flex: 1, paddingVertical: 8, alignItems: 'center' },
  toggleBtnActive: { backgroundColor: colors.ink },
  toggleText: { fontSize: 12, fontFamily: fonts.sans.native.medium, color: colors.muted },
  toggleTextActive: { color: colors.bg },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  listBox: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, overflow: 'hidden' },
  rowItem: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14 },
  rowBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  rowTitle: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink, marginTop: 3 },
  iconBtn: { padding: 6 },
  uploadBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    borderStyle: 'dashed',
  },
  anomItem: { padding: 14, gap: 8 },
  anomHead: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  anomMeta: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  comment: { color: colors.muted, fontSize: 13, lineHeight: 19, fontFamily: fonts.sans.native.regular },
});
