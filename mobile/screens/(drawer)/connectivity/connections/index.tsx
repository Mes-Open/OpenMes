import { useRouter } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { InactiveToggle } from '@/components/ui/InactiveToggle';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useConnections } from '@/hooks/queries/useConnectivity';

const STATUS_MAP: Record<string, string> = {
  connected: 'IN_PROGRESS',
  connecting: 'PENDING',
  disconnected: 'CANCELLED',
  error: 'BLOCKED',
};

export function ConnectionsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const [includeInactive, setIncludeInactive] = useState(false);
  const query = useConnections(includeInactive);
  const items = query.data ?? [];

  return (
    <ListScreen
      title="Connections"
      eyebrow={`CONNECTIVITY · ${items.length} CONNECTIONS`}
      extraHeader={<InactiveToggle value={includeInactive} onValueChange={setIncludeInactive} />}
      items={items}
      keyExtractor={(c) => String(c.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No connections"
      renderItem={(item) => (
        <Card
          onPress={() => router.push(`/connectivity/connections/${item.id}` as never)}>
          <View style={styles.headerRow}>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Mono size={11} color={palette.textFaint}>
                {item.protocol.toUpperCase()}
              </Mono>
              <Text style={[styles.name, { color: palette.text }]} numberOfLines={1}>
                {item.name}
              </Text>
            </View>
            <StatusPill status={STATUS_MAP[item.status] ?? 'PENDING'} label={item.status} />
          </View>
          <View style={styles.metaRow}>
            {item.topics_count != null ? (
              <Mono size={11} color={palette.textFaint}>
                {item.topics_count} TOPICS
              </Mono>
            ) : null}
            {item.messages_received != null ? (
              <Mono size={11} color={palette.textFaint}>
                {item.messages_received} MSGS
              </Mono>
            ) : null}
            {item.last_connected_at ? (
              <Mono size={11} color={palette.textFaint}>
                LAST {item.last_connected_at.slice(11, 16)}
              </Mono>
            ) : null}
          </View>
        </Card>
      )}
    />
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  name: { fontSize: 15, fontWeight: '600', letterSpacing: -0.2, marginTop: 3 },
  metaRow: { flexDirection: 'row', gap: 14, marginTop: 10, flexWrap: 'wrap' },
});
