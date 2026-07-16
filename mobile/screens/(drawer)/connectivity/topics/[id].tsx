/**
 * Topic detail — mirrors the web MQTT topic card: the pattern header with an
 * active/inactive pill, an optional description, and the mapping-rules table
 * (Field path / Action / Status). Edit / activate / delete are the page actions.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { StatusPill, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
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
  const { t } = useTranslation();

  const query = useTopic(numericId);
  const mappingsQuery = useMappings({ machine_topic_id: numericId, include_inactive: true });
  const toggleMutation = useToggleTopicActive();
  const deleteMutation = useDeleteTopic();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const topic = query.data;
  const mappings = mappingsQuery.data ?? [];

  return (
    <View style={styles.screen}>
      <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
        <View>
          <View style={{ alignSelf: 'flex-start' }}>
            <StatusPill status={topic.is_active ? 'running' : 'pending'} label={(topic.is_active ? t('Active') : t('Inactive')).toUpperCase()} />
          </View>
          <Text style={styles.h1}>{topic.topic_pattern}</Text>
        </View>

        {topic.description ? (
          <View style={styles.box}>
            <Text style={styles.description}>{topic.description}</Text>
          </View>
        ) : null}

        <View style={{ gap: 8 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Mappings').toUpperCase()}</Mono>
          <View style={styles.box}>
            <View style={[styles.row, styles.headerRow]}>
              <HCell flex={1.4}>{t('Field Path')}</HCell>
              <HCell flex={1}>{t('Action')}</HCell>
              <HCell w={54}>{t('Status')}</HCell>
            </View>
            {mappings.map((m) => (
              <View key={m.id} style={[styles.row, styles.dataRow]}>
                <View style={{ flex: 1.4 }}>
                  <Mono size={11} color={colors.ink} numberOfLines={1}>{m.field_path ?? '—'}</Mono>
                </View>
                <View style={{ flex: 1 }}>
                  <Text numberOfLines={1} style={styles.cellText}>{m.action_type}</Text>
                </View>
                <View style={{ width: 54 }}>
                  <Mono size={9.5} color={m.is_active ? colors.running : colors.faint}>
                    {(m.is_active ? t('On') : t('Off')).toUpperCase()}
                  </Mono>
                </View>
              </View>
            ))}
            {mappings.length === 0 ? <Text style={styles.emptyBox}>{t('No mappings.')}</Text> : null}
          </View>
        </View>

        <Button title={t('Edit topic')} onPress={() => router.push(`/connectivity/topics/${topic.id}/edit` as never)} />
        <Button
          title={topic.is_active ? t('Deactivate') : t('Activate')}
          variant="secondary"
          loading={toggleMutation.isPending}
          onPress={() => toggleMutation.mutate(topic.id, { onError: (e: Error) => Alert.alert(t('Failed'), e.message) })}
        />
        <Button
          title={t('Delete topic')}
          variant="danger"
          loading={deleteMutation.isPending}
          onPress={() =>
            Alert.alert(t('Delete topic'), t('Delete "{{name}}"?', { name: topic.topic_pattern }), [
              { text: t('Cancel'), style: 'cancel' },
              {
                text: t('Delete'),
                style: 'destructive',
                onPress: () =>
                  deleteMutation.mutate(topic.id, {
                    onSuccess: () => router.back(),
                    onError: (e: Error) => Alert.alert(t('Failed'), e.message),
                  }),
              },
            ])
          }
        />
      </ScrollView>
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
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 20, fontFamily: fonts.mono.native.semibold, color: colors.ink, letterSpacing: -0.2, marginTop: 10 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, overflow: 'hidden' },
  description: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular, padding: 14, lineHeight: 20 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 12 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 11, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  emptyBox: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 14 },
});
