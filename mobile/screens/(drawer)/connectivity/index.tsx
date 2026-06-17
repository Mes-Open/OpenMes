import { formatDistanceToNowStrict, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { ScrollView, StatusBar, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Banner } from '@/components/ui/Banner';
import { HubGrid, HubTile } from '@/components/ui/HubTile';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useConnections, useMessages, useTopics } from '@/hooks/queries/useConnectivity';

const DARK = Colors.dark;

export function ConnectivityHub() {
  const router = useRouter();
  const { t } = useTranslation();

  // Always render in dark to match the design — Connectivity is a
  // monitoring-style screen and reads better as a dark surface.
  const connectionsQ = useConnections(true);
  const topicsQ = useTopics({ include_inactive: true });
  const messagesQ = useMessages();

  const connections = connectionsQ.data ?? [];
  const liveCount = connections.filter((c) => c.status === 'connected').length;
  const offCount = connections.length - liveCount;
  const topicCount = topicsQ.data?.length ?? 0;
  const messageCount = messagesQ.data?.data.length ?? 0;

  // Offline connections — surface the most recently disconnected one in the
  // banner, mirroring the design ("PLC-FLOOR-2 disconnected · 4m ago").
  const downConnection = connections.find((c) => c.status !== 'connected');
  const downAgo = downConnection?.last_connected_at
    ? (() => {
        try {
          return formatDistanceToNowStrict(parseISO(downConnection.last_connected_at));
        } catch {
          return null;
        }
      })()
    : null;

  return (
    <View style={{ flex: 1, backgroundColor: DARK.background }}>
      <StatusBar barStyle="light-content" />
      <ScreenHeader
        variant="dark"
        title={t('Connectivity')}
        subtitle={`MQTT · ${connections.length} ${t('BROKERS').toUpperCase()} · ${topicCount} ${t('TOPICS').toUpperCase()}`}
      />

      <ScrollView contentContainerStyle={styles.scroll}>
        <HubGrid>
          <HubTile
            icon="plug"
            label={t('Connections')}
            sub={`${liveCount} ${t('LIVE').toUpperCase()}${offCount > 0 ? ` · ${offCount} ${t('OFF').toUpperCase()}` : ''}`}
            count={connections.length}
            accent
            dark
            onPress={() => router.push('/(drawer)/connectivity/connections' as never)}
          />
          <HubTile
            icon="rss"
            label={t('Topics')}
            sub={t('Subscriptions')}
            count={topicCount}
            dark
            onPress={() => router.push('/(drawer)/connectivity/topics' as never)}
          />
          <HubTile
            icon="exchange"
            label={t('Messages')}
            sub={messageCount > 0 ? `${t('LIVE').toUpperCase()} · ${messageCount} ${t('RECENT').toUpperCase()}` : t('LIVE LOG').toUpperCase()}
            dark
            onPress={() => router.push('/(drawer)/connectivity/messages' as never)}
          />
          <HubTile
            icon="filter"
            label={t('Mappings')}
            sub={t('Payload rules')}
            dark
            onPress={() => router.push('/(drawer)/connectivity/topics' as never)}
          />
        </HubGrid>

        {downConnection ? (
          <Banner
            tone="danger"
            title={t('Connection {{name}} disconnected').replace(
              '{{name}}',
              (downConnection.name ?? 'unknown').toUpperCase(),
            )}
            detail={
              downAgo
                ? t('LAST SEEN {{ago}} AGO').replace('{{ago}}', downAgo.toUpperCase())
                : t('NEVER CONNECTED').toUpperCase()
            }
            cta={t('Open')}
            dark
            onPress={() =>
              router.push(
                `/connectivity/connections/${downConnection.id}` as never,
              )
            }
          />
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 18, gap: 14, paddingBottom: 32 },
});
