import { FontAwesome } from '@expo/vector-icons';
import * as DocumentPicker from 'expo-document-picker';
import { useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { attachmentDownloadUrl } from '@/api/woExtras';
import {
  useAttachments,
  useDeleteAttachment,
  useUploadAttachment,
} from '@/hooks/queries/useWoExtras';
import { useAuthStore } from '@/stores/authStore';

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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

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
      { onError: (e: Error) => Alert.alert('Upload failed', e.message) },
    );
  };

  const onDownload = (attachmentId: number) =>
    WebBrowser.openBrowserAsync(attachmentDownloadUrl(attachmentId));

  const items = query.data ?? [];

  return (
    <ListScreen
      title="Attachments"
      eyebrow={`WO #${woId} · ${items.length} FILES`}
      items={items}
      keyExtractor={(a) => String(a.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No attachments"
      extraHeader={
        <Button
          title={uploadMutation.isPending ? 'Uploading…' : 'Upload file'}
          loading={uploadMutation.isPending}
          onPress={onPickAndUpload}
          leftIcon={<FontAwesome name="cloud-upload" size={14} color="#1a1208" />}
        />
      }
      renderItem={(item) => {
        const canDelete = isAdminOrSup || item.uploaded_by_id === userId;
        return (
          <Card>
            <Pressable onPress={() => onDownload(item.id)}>
              <View style={styles.row}>
                <View style={[styles.iconWrap, { backgroundColor: '#FAF0DD' }]}>
                  <FontAwesome name="file" size={16} color={BRAND.amber} />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={[styles.name, { color: palette.text }]} numberOfLines={1}>
                    {item.original_name}
                  </Text>
                  <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
                    {humanSize(item.file_size)}
                    {item.uploaded_by ? ` · ${item.uploaded_by.username.toUpperCase()}` : ''}
                    {item.created_at ? ` · ${item.created_at.slice(0, 10)}` : ''}
                  </Mono>
                </View>
                <FontAwesome name="download" size={14} color={palette.textMuted} />
              </View>
            </Pressable>
            {canDelete ? (
              <Pressable
                onPress={() =>
                  Alert.alert('Delete attachment', `Remove "${item.original_name}"?`, [
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
                style={({ pressed }) => [
                  styles.deleteBtn,
                  { borderColor: palette.border, backgroundColor: palette.dangerSoft, opacity: pressed ? 0.7 : 1 },
                ]}>
                <FontAwesome name="trash" size={11} color={palette.danger} />
                <Mono size={10} color={palette.danger} weight="700">DELETE</Mono>
              </Pressable>
            ) : null}
          </Card>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconWrap: { width: 40, height: 40, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  name: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  deleteBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 6,
    borderWidth: 1,
    alignSelf: 'flex-end',
    marginTop: 10,
  },
});
