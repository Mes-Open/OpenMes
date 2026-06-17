import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useDeleteTopic,
  useMappings,
  useToggleTopicActive,
  useTopic,
} from '@/hooks/queries/useConnectivity';

export function TopicDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme();
  const palette = Colors[scheme];

  const query = useTopic(numericId);
  const mappingsQuery = useMappings({ machine_topic_id: numericId, include_inactive: true });
  const toggleMutation = useToggleTopicActive();
  const deleteMutation = useDeleteTopic();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const t = query.data;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title="Topic"
        subtitle={t.topic_pattern}
        rightSlot={<StatusPill status={t.is_active ? 'ACTIVE' : 'INACTIVE'} label={t.is_active ? 'Active' : 'Inactive'} />}
      />
      <ScrollView style={{ backgroundColor: palette.background }} contentContainerStyle={styles.container}>
      {t.description ? <Card><Text style={{ color: palette.text }}>{t.description}</Text></Card> : null}

      <Card style={{ gap: 4 }}>
        <Text style={[styles.section, { color: palette.text }]}>
          Mappings ({mappingsQuery.data?.length ?? 0})
        </Text>
        {(mappingsQuery.data ?? []).map((m) => (
          <View key={m.id} style={styles.mappingRow}>
            <Text style={{ color: palette.text, fontFamily: 'GeistMono_500Medium' }}>{m.field_path ?? '—'}</Text>
            <Text style={{ color: palette.textMuted, fontSize: 13 }}>{m.action_type}</Text>
            <StatusPill status={m.is_active ? 'ACTIVE' : 'INACTIVE'} label={m.is_active ? 'on' : 'off'} />
          </View>
        ))}
        {(mappingsQuery.data?.length ?? 0) === 0 ? (
          <Text style={{ color: palette.textMuted, fontSize: 13 }}>No mappings.</Text>
        ) : null}
      </Card>

      <Button
        title="Edit topic"
        variant="primary"
        onPress={() => router.push(`/connectivity/topics/${t.id}/edit` as never)}
      />
      <Button
        title={t.is_active ? 'Deactivate' : 'Activate'}
        variant="secondary"
        loading={toggleMutation.isPending}
        onPress={() => toggleMutation.mutate(t.id, { onError: (e: Error) => Alert.alert('Failed', e.message) })}
      />
      <Button
        title="Delete topic"
        variant="danger"
        loading={deleteMutation.isPending}
        onPress={() => Alert.alert('Delete topic', `Delete "${t.topic_pattern}"?`, [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Delete', style: 'destructive',
            onPress: () => deleteMutation.mutate(t.id, {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Failed', e.message),
            }) },
        ])}
      />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { padding: 16, gap: 14 },
  headerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  pattern: { fontSize: 18, fontWeight: '800', flex: 1, fontFamily: 'GeistMono_600SemiBold' },
  section: { fontSize: 14, fontWeight: '700', marginBottom: 6 },
  mappingRow: { flexDirection: 'row', gap: 8, alignItems: 'center', paddingVertical: 4 },
});
