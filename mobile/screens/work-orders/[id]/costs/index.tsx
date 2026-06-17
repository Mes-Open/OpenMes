import { FontAwesome } from '@expo/vector-icons';
import * as DocumentPicker from 'expo-document-picker';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useMemo, useState } from 'react';
import {
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { StatusPill } from '@/components/ui/StatusPill';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
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

/**
 * Combined "WO costs / attachments / anomalies" screen — single tabbed view
 * matching the new design. Each tab fetches its own data; the header total
 * card sits above the list and switches its content with the active tab.
 */
export function CostsList() {
  const { id, tab: initialTab } = useLocalSearchParams<{ id: string; tab?: Tab }>();
  const woId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

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
    const result = await DocumentPicker.getDocumentAsync({
      multiple: false,
      copyToCacheDirectory: true,
    });
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
      { onError: (e: Error) => Alert.alert('Upload failed', e.message) },
    );
  };

  const onAdd = () => {
    if (tab === 'costs') router.push(`/work-orders/${woId}/costs/new` as never);
    else if (tab === 'anomalies') router.push(`/work-orders/${woId}/anomalies/new` as never);
    else if (tab === 'attachments') void onPickAndUpload();
  };

  if (isLoading) return <LoadingState />;
  if (wo.isError || !wo.data) return <ErrorState error={wo.error} onRetry={wo.refetch} />;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        title={wo.data.order_no}
        subtitle="Costs & attachments"
        rightSlot={
          canManage ? (
            <Pressable
              onPress={onAdd}
              hitSlop={6}
              style={({ pressed }) => [
                styles.addBtn,
                { backgroundColor: BRAND.amber, opacity: pressed ? 0.85 : 1 },
              ]}>
              <FontAwesome name="plus" size={14} color="#1a1208" />
            </Pressable>
          ) : undefined
        }
      />

      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          <RefreshControl
            refreshing={costsQ.isFetching || anomaliesQ.isFetching || attachmentsQ.isFetching}
            onRefresh={() => {
              costsQ.refetch();
              anomaliesQ.refetch();
              attachmentsQ.refetch();
            }}
          />
        }>
        {/* Tabs */}
        <View
          style={[
            styles.tabsTrack,
            { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
          ]}>
          {(
            [
              { id: 'costs', label: 'Costs' },
              { id: 'attachments', label: 'Attachments' },
              { id: 'anomalies', label: 'Anomalies' },
            ] as const
          ).map((t) => {
            const active = t.id === tab;
            return (
              <Pressable
                key={t.id}
                onPress={() => setTab(t.id)}
                style={[
                  styles.tabBtn,
                  active
                    ? {
                        backgroundColor: palette.surface,
                        boxShadow: '0px 1px 2px rgba(0, 0, 0, 0.08)',
                        elevation: 1,
                      }
                    : null,
                ]}>
                <Mono
                  size={11}
                  color={active ? palette.text : palette.textMuted}
                  weight="700"
                  letterSpacing={0.4}>
                  {t.label.toUpperCase()}
                </Mono>
              </Pressable>
            );
          })}
        </View>

        {/* Active-tab summary */}
        {tab === 'costs' ? (
          <Card>
            <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
              TOTAL ADDITIONAL COSTS
            </Mono>
            <Text style={[styles.totalValue, { color: palette.text, fontFamily: MONO }]}>
              {total.toFixed(2)}
              {currency ? <Text style={styles.totalUnit}> {currency}</Text> : null}
            </Text>
            <Mono size={11} color={palette.textMuted} style={{ marginTop: 4 }}>
              {`${counts.costs} ${counts.costs === 1 ? 'ENTRY' : 'ENTRIES'}`}
              {/* TODO(api/wo-planned-costs): WorkOrder doesn't currently expose a
                  planned_costs_total. When it does, surface "planned X · over by Y%". */}
            </Mono>
          </Card>
        ) : (
          <Card>
            <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
              {tab === 'attachments' ? 'FILES' : 'ANOMALIES'}
            </Mono>
            <Text style={[styles.totalValue, { color: palette.text, fontFamily: MONO }]}>
              {tab === 'attachments' ? counts.attachments : counts.anomalies}
            </Text>
            {tab === 'anomalies' ? (
              <Mono size={11} color={palette.textMuted} style={{ marginTop: 4 }}>
                {`${anomalies.filter((a) => a.status === 'draft').length} DRAFT · ${
                  anomalies.filter((a) => a.status === 'processed').length
                } PROCESSED`}
              </Mono>
            ) : null}
          </Card>
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const deleteMutation = useDeleteAdditionalCost();

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState error={error} onRetry={onRefresh} />;
  if (items.length === 0) {
    return <EmptyState title="No additional costs" subtitle="Tap + to add an entry." />;
  }

  return (
    <Card style={{ padding: 0, overflow: 'hidden' }}>
      {items.map((item, i) => (
        <View
          key={item.id}
          style={[
            styles.row,
            i < items.length - 1
              ? { borderBottomColor: palette.border, borderBottomWidth: StyleSheet.hairlineWidth }
              : null,
          ]}>
          <View style={[styles.rowIcon, { backgroundColor: palette.surfaceAlt }]}>
            <FontAwesome name="cube" size={14} color={palette.textMuted} />
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={[styles.rowTitle, { color: palette.text }]} numberOfLines={1}>
              {item.description}
            </Text>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.5} style={{ marginTop: 3 }}>
              {(item.cost_source?.name ?? '—').toUpperCase()}
              {item.created_at ? ` · ${item.created_at.slice(0, 10)}` : ''}
            </Mono>
          </View>
          <Mono size={13} color={palette.text} weight="700">
            {Number(item.amount).toFixed(2)}
          </Mono>
          {canManage ? (
            <Pressable
              onPress={() =>
                Alert.alert('Delete cost', 'Remove this entry?', [
                  { text: 'Cancel', style: 'cancel' },
                  {
                    text: 'Delete',
                    style: 'destructive',
                    onPress: () =>
                      deleteMutation.mutate(item.id, {
                        onError: (e: Error) => Alert.alert('Failed', e.message),
                      }),
                  },
                ])
              }
              hitSlop={6}
              style={({ pressed }) => [styles.iconRowBtn, { opacity: pressed ? 0.6 : 1 }]}>
              <FontAwesome name="trash" size={13} color={palette.danger} />
            </Pressable>
          ) : null}
        </View>
      ))}
    </Card>
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const deleteMutation = useDeleteAttachment();

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState error={error} onRetry={onRefresh} />;

  return (
    <View style={{ gap: 10 }}>
      <Pressable
        onPress={onUpload}
        disabled={uploading}
        style={({ pressed }) => [
          styles.uploadBtn,
          { borderColor: palette.border, opacity: pressed ? 0.85 : 1 },
        ]}>
        <FontAwesome name="cloud-upload" size={14} color={palette.text} />
        <Mono size={12} color={palette.text} weight="700">
          {uploading ? 'UPLOADING…' : 'UPLOAD FILE'}
        </Mono>
      </Pressable>
      {items.length === 0 ? (
        <EmptyState title="No attachments" />
      ) : (
        <Card style={{ padding: 0, overflow: 'hidden' }}>
          {items.map((item, i) => {
            const canDelete = canManage || item.uploaded_by_id === userId;
            return (
              <View
                key={item.id}
                style={[
                  styles.row,
                  i < items.length - 1
                    ? { borderBottomColor: palette.border, borderBottomWidth: StyleSheet.hairlineWidth }
                    : null,
                ]}>
                <View style={[styles.rowIcon, { backgroundColor: '#FAF0DD' }]}>
                  <FontAwesome name="file" size={14} color={BRAND.amber} />
                </View>
                <Pressable
                  onPress={() => WebBrowser.openBrowserAsync(attachmentDownloadUrl(item.id))}
                  style={{ flex: 1, minWidth: 0 }}>
                  <Text style={[styles.rowTitle, { color: palette.text }]} numberOfLines={1}>
                    {item.original_name}
                  </Text>
                  <Mono size={10} color={palette.textFaint} style={{ marginTop: 3 }}>
                    {humanSize(item.file_size)}
                    {item.uploaded_by ? ` · ${item.uploaded_by.username.toUpperCase()}` : ''}
                  </Mono>
                </Pressable>
                {canDelete ? (
                  <Pressable
                    onPress={() =>
                      Alert.alert('Delete', `Remove "${item.original_name}"?`, [
                        { text: 'Cancel', style: 'cancel' },
                        {
                          text: 'Delete',
                          style: 'destructive',
                          onPress: () =>
                            deleteMutation.mutate(item.id, {
                              onError: (e: Error) => Alert.alert('Failed', e.message),
                            }),
                        },
                      ])
                    }
                    hitSlop={6}
                    style={({ pressed }) => [styles.iconRowBtn, { opacity: pressed ? 0.6 : 1 }]}>
                    <FontAwesome name="trash" size={13} color={palette.danger} />
                  </Pressable>
                ) : null}
              </View>
            );
          })}
        </Card>
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const deleteMutation = useDeleteProductionAnomaly();

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState error={error} onRetry={onRefresh} />;
  if (items.length === 0) {
    return <EmptyState title="No anomalies" subtitle="Tap + to record one." />;
  }

  return (
    <View style={{ gap: 10 }}>
      {items.map((item) => {
        const dev = item.deviation_pct != null ? Number(item.deviation_pct) : null;
        const accent =
          dev != null && Math.abs(dev) > 10
            ? palette.danger
            : dev != null && Math.abs(dev) > 0
            ? palette.warning
            : palette.textMuted;
        return (
          <Card key={item.id} accent={accent}>
            <View style={styles.row}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono size={11} color={palette.textFaint}>
                  {(item.anomaly_reason?.code ?? `ANOM-${item.id}`).toUpperCase()}
                </Mono>
                <Text style={[styles.rowTitle, { color: palette.text }]} numberOfLines={1}>
                  {item.anomaly_reason?.name ?? 'Anomaly'}
                </Text>
              </View>
              <StatusPill
                status={item.status === 'draft' ? 'PENDING' : 'DONE'}
                label={item.status}
              />
            </View>
            <View style={styles.metaRow}>
              <Mono size={10} color={palette.textFaint}>PLANNED</Mono>
              <Mono size={11} color={palette.text} weight="700">{String(item.planned_qty)}</Mono>
              <View style={{ width: 12 }} />
              <Mono size={10} color={palette.textFaint}>ACTUAL</Mono>
              <Mono size={11} color={palette.text} weight="700">{String(item.actual_qty)}</Mono>
              {dev != null ? (
                <>
                  <View style={{ width: 12 }} />
                  <Mono size={10} color={palette.textFaint}>DEV</Mono>
                  <Mono size={11} color={accent} weight="700">{`${dev}%`}</Mono>
                </>
              ) : null}
              <View style={{ flex: 1 }} />
              <Pressable
                onPress={() =>
                  Alert.alert('Delete anomaly', 'Remove this entry?', [
                    { text: 'Cancel', style: 'cancel' },
                    {
                      text: 'Delete',
                      style: 'destructive',
                      onPress: () =>
                        deleteMutation.mutate(item.id, {
                          onError: (e: Error) => Alert.alert('Failed', e.message),
                        }),
                    },
                  ])
                }
                hitSlop={6}
                style={({ pressed }) => [styles.iconRowBtn, { opacity: pressed ? 0.6 : 1 }]}>
                <FontAwesome name="trash" size={13} color={palette.danger} />
              </Pressable>
            </View>
            {item.comment ? (
              <Text
                style={{ color: palette.text, fontSize: 13, marginTop: 8, lineHeight: 19 }}
                numberOfLines={3}>
                {item.comment}
              </Text>
            ) : null}
          </Card>
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
  scroll: { padding: 18, gap: 14, paddingBottom: 32 },
  addBtn: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tabsTrack: {
    flexDirection: 'row',
    padding: 4,
    gap: 4,
    borderRadius: 10,
    borderWidth: StyleSheet.hairlineWidth,
  },
  tabBtn: {
    flex: 1,
    paddingVertical: 8,
    borderRadius: 7,
    alignItems: 'center',
    justifyContent: 'center',
  },
  totalValue: { fontSize: 28, fontWeight: '600', letterSpacing: -0.5, marginTop: 6 },
  totalUnit: { fontSize: 14, fontWeight: '500', color: '#9B9892' },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12 },
  rowIcon: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rowTitle: { fontSize: 13, fontWeight: '500', marginTop: 3 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 8 },
  iconRowBtn: { padding: 6 },
  uploadBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
  },
});
