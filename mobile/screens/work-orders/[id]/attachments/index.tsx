/**
 * Work-order attachments — the files attached to a work order (File / Size /
 * By / Date) in the web table idiom. "Upload file" picks + uploads; tap a row to
 * download, long-press to delete (own files, or any as admin/supervisor).
 */
import { useLocalSearchParams } from 'expo-router';
import * as DocumentPicker from 'expo-document-picker';
import * as WebBrowser from 'expo-web-browser';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { attachmentDownloadUrl } from '@/api/woExtras';
import {
  useAttachments,
  useDeleteAttachment,
  useUploadAttachment,
} from '@/hooks/queries/useWoExtras';
import { useAuthStore } from '@/stores/authStore';
import type { Attachment } from '@/api/woExtras';

const ENTITY_TYPE = 'work_order';

function humanSize(bytes?: number | null): string {
  if (!bytes) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1_048_576) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1_048_576).toFixed(1)} MB`;
}

export function AttachmentsList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const woId = Number(id);
  const { t } = useTranslation();

  const query = useAttachments(ENTITY_TYPE, woId);
  const uploadMutation = useUploadAttachment();
  const deleteMutation = useDeleteAttachment();
  const userId = useAuthStore((s) => s.user?.id);
  const isAdminOrSup = useAuthStore((s) => {
    const roles = s.user?.roles?.map((r) => r.name) ?? [];
    return roles.includes('Admin') || roles.includes('Supervisor');
  });

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

  const onDownload = (attachmentId: number) => WebBrowser.openBrowserAsync(attachmentDownloadUrl(attachmentId));

  const onDelete = (item: Attachment) =>
    Alert.alert(t('Delete attachment?'), item.original_name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => deleteMutation.mutate(item.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  const items = query.data ?? [];

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Attachments')}</Text>
        <View style={{ flex: 1 }} />
        <Button
          title={uploadMutation.isPending ? t('Uploading…') : t('Upload file')}
          size="sm"
          loading={uploadMutation.isPending}
          onPress={onPickAndUpload}
        />
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <View style={[styles.row, styles.headerRow]}>
            <HCell flex={1}>{t('File')}</HCell>
            <HCell w={80}>{t('Size')}</HCell>
            <HCell w={96}>{t('By')}</HCell>
            <HCell w={96}>{t('Date')}</HCell>
          </View>
          {items.map((item) => {
            const canDelete = isAdminOrSup || item.uploaded_by_id === userId;
            return (
              <Pressable
                key={item.id}
                onPress={() => onDownload(item.id)}
                onLongPress={canDelete ? () => onDelete(item) : undefined}
                style={({ pressed }) => [styles.row, styles.dataRow, { opacity: pressed ? 0.6 : 1 }]}>
                <View style={{ flex: 1 }}>
                  <Text numberOfLines={1} style={styles.title}>{item.original_name}</Text>
                </View>
                <View style={{ width: 80 }}>
                  <Mono size={10} color={colors.muted}>{humanSize(item.file_size)}</Mono>
                </View>
                <View style={{ width: 96 }}>
                  <Text numberOfLines={1} style={styles.cellText}>{item.uploaded_by?.username ?? '—'}</Text>
                </View>
                <View style={{ width: 96 }}>
                  <Mono size={10} color={colors.muted}>{item.created_at ? item.created_at.slice(0, 10) : '—'}</Mono>
                </View>
              </Pressable>
            );
          })}
          {items.length === 0 ? <Text style={styles.empty}>{t('No attachments.')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{String(children).toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
