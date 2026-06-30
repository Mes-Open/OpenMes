import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useConnections, useMessages, useTopics } from '@/hooks/queries/useConnectivity';

type Palette = typeof Colors.light;

/**
 * Tablet Connectivity Admin — 3-pane dark: connections list +
 * topics-for-selected-connection + live message tail. Matches design
 * TabletConnectivityAdmin.
 */
export function TabletConnectivityAdmin() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const styles = useMemo(() => makeStyles(palette), [palette]);
  const { t } = useTranslation();
  const [selectedConnId, setSelectedConnId] = useState<number | null>(null);

  const connsQ = useConnections(true);
  const conns = connsQ.data ?? [];

  const selected = useMemo(
    () => conns.find((c) => c.id === selectedConnId) ?? conns[0] ?? null,
    [conns, selectedConnId],
  );

  const topicsQ = useTopics(selected ? { machine_connection_id: selected.id } : {});
  const topics = topicsQ.data ?? [];

  const messagesQ = useMessages(
    selected ? { machine_connection_id: selected.id, per_page: 30 } : { per_page: 30 },
  );
  const messages = messagesQ.data?.data ?? [];

  const liveCount = conns.filter((c) => c.status === 'connected').length;
  const errorCount = conns.filter((c) => c.status === 'error' || c.status === 'disconnected').length;

  return (
    <TabletShell
      dark={scheme === 'dark'}
      eyebrow={`${t('CONNECTIVITY').toUpperCase()} · ${liveCount} ${t('LIVE').toUpperCase()} · ${errorCount} ${t('ERROR').toUpperCase()}`}
      title={t('MQTT brokers & topics')}
      right={
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <Pressable
            style={({ pressed }) => [
              styles.headerBtnGhost,
              { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
            ]}>
            <Mono size={11.5} color={palette.text} weight="600" letterSpacing={0.4}>
              {t('Open on web').toUpperCase()}
            </Mono>
          </Pressable>
          <Pressable
            style={({ pressed }) => [
              styles.headerBtnPrimary,
              { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
            ]}>
            <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.5}>
              {t('Reconnect all').toUpperCase()}
            </Mono>
          </Pressable>
        </View>
      }>
      <View style={styles.grid}>
        {/* LEFT — connections */}
        <View style={[styles.panel, styles.connPanel]}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
            {t('CONNECTIONS').toUpperCase()} · {conns.length}
          </Mono>
          <ScrollView style={{ flex: 1, marginTop: 12 }} contentContainerStyle={{ gap: 8 }}>
            {conns.map((c) => {
              const sel = c.id === selected?.id;
              const stateColor =
                c.status === 'connected' ? palette.success
                  : c.status === 'error' || c.status === 'disconnected' ? palette.danger
                  : palette.textFaint;
              return (
                <Pressable
                  key={c.id}
                  onPress={() => setSelectedConnId(c.id)}
                  style={({ pressed }) => [
                    styles.connCard,
                    {
                      backgroundColor: sel ? `${BRAND.amber}1a` : palette.surfaceAlt,
                      borderColor: sel ? BRAND.amber : c.status === 'error' ? palette.danger : palette.border,
                      opacity: pressed ? 0.9 : 1,
                    },
                  ]}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                    <View style={[styles.stateDot, { backgroundColor: stateColor }]} />
                    <Text style={styles.connName}>{c.name}</Text>
                  </View>
                  {c.mqtt_connection?.broker_host ? (
                    <Mono size={10} color={palette.textFaint} letterSpacing={0.3} style={{ marginTop: 6 }}>
                      mqtt://{c.mqtt_connection.broker_host}:{c.mqtt_connection.broker_port}
                    </Mono>
                  ) : null}
                  <View style={styles.connStats}>
                    <Mono size={10} color={palette.textMuted}>
                      {c.topics_count ?? 0} {t('TOPICS').toUpperCase()}
                    </Mono>
                    <Mono
                      size={12}
                      color={c.messages_received ? BRAND.amber : palette.danger}
                      weight="700">
                      {c.messages_received ?? 0}
                      <Mono size={9} color={palette.textFaint}> msg</Mono>
                    </Mono>
                  </View>
                </Pressable>
              );
            })}
            {conns.length === 0 ? (
              <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 16 }}>
                {t('No connections').toUpperCase()}
              </Mono>
            ) : null}
          </ScrollView>
          <Pressable
            onPress={() => router.push('/connectivity/connections/new' as never)}
            style={({ pressed }) => [
              styles.addConn,
              { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
            ]}>
            <Mono size={11} color={palette.textMuted} weight="600" letterSpacing={0.5}>
              + {t('Add connection').toUpperCase()}
            </Mono>
          </Pressable>
        </View>

        {/* CENTER — topics */}
        <View style={[styles.panel, styles.topicsPanel]}>
          <View style={styles.topicsHead}>
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
              {t('TOPICS').toUpperCase()} · {selected?.name?.toUpperCase() ?? '—'}
            </Mono>
            <Mono size={10} color={BRAND.amber} weight="700">{topics.length}</Mono>
          </View>
          <ScrollView style={{ flex: 1, marginTop: 12 }} contentContainerStyle={{ gap: 6 }}>
            {topics.map((tp) => {
              const silent = (tp.mappings_count ?? 0) === 0;
              return (
                <Pressable
                  key={tp.id}
                  onPress={() => router.push(`/connectivity/topics/${tp.id}` as never)}
                  style={({ pressed }) => [
                    styles.topicRow,
                    {
                      backgroundColor: silent ? `${palette.danger}14` : palette.surfaceAlt,
                      borderLeftColor: silent ? palette.danger : 'transparent',
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}>
                  <FontAwesome
                    name="rss"
                    size={12}
                    color={silent ? palette.danger : BRAND.amber}
                  />
                  <Mono
                    size={10.5}
                    color={palette.text}
                    style={{ flex: 1, minWidth: 0 }}
                    numberOfLines={1}>
                    {tp.topic_pattern}
                  </Mono>
                  <View style={[styles.qosPill, { backgroundColor: palette.border }]}>
                    <Mono size={9} color={palette.textMuted} weight="700">
                      Q{tp.mappings_count ?? 0}
                    </Mono>
                  </View>
                </Pressable>
              );
            })}
            {topics.length === 0 ? (
              <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 16 }}>
                {t('No topics').toUpperCase()}
              </Mono>
            ) : null}
          </ScrollView>
        </View>

        {/* RIGHT — live messages */}
        <View style={[styles.panel, styles.messagesPanel, { backgroundColor: palette.surfaceAlt }]}>
          <View style={styles.messagesHead}>
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
              {t('LIVE MESSAGES').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.success} weight="700" letterSpacing={0.5}>
              ● {messages.length}/{t('VIEW').toUpperCase()}
            </Mono>
          </View>
          <View style={styles.messagesTerminal}>
            <ScrollView contentContainerStyle={{ gap: 4 }}>
              {messages.map((m) => {
                const color =
                  m.processing_status === 'error' ? palette.danger
                    : m.processing_status === 'skipped' ? '#7c3aed'
                    : palette.success;
                let timestamp = '';
                try {
                  timestamp = format(parseISO(m.received_at), 'HH:mm:ss');
                } catch {
                  timestamp = '';
                }
                return (
                  <View key={m.id} style={styles.messageLine}>
                    <Mono size={10.5} color={palette.textFaint} style={{ flexShrink: 0 }}>
                      {timestamp}
                    </Mono>
                    <Mono size={10.5} color={color} style={{ flexShrink: 0 }} numberOfLines={1}>
                      {m.topic}
                    </Mono>
                    <Text
                      style={[styles.messagePayload, { color: palette.textMuted, fontFamily: MONO }]}
                      numberOfLines={1}>
                      {m.raw_payload?.slice(0, 80) ?? ''}
                    </Text>
                  </View>
                );
              })}
              {messages.length === 0 ? (
                <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 16 }}>
                  {t('No messages yet').toUpperCase()}
                </Mono>
              ) : null}
            </ScrollView>
          </View>
        </View>
      </View>
    </TabletShell>
  );
}

// Theme-bound styles — built lazily inside the component via useMemo so the
// panel surfaces / borders / terminal background re-pick the active palette
// when the user toggles between light and dark.
function makeStyles(palette: Palette) {
  return StyleSheet.create({
  grid: { flex: 1, flexDirection: 'row', gap: 12, minHeight: 0 },
  panel: {
    borderRadius: 16,
    borderWidth: 1,
    borderColor: palette.border,
    padding: 14,
    backgroundColor: palette.surface,
  },
  connPanel: { width: 320, flexDirection: 'column' },
  topicsPanel: { width: 360 },
  messagesPanel: { flex: 1 },

  headerBtnGhost: {
    height: 38,
    paddingHorizontal: 14,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerBtnPrimary: {
    height: 38,
    paddingHorizontal: 14,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },

  connCard: { padding: 12, borderRadius: 10, borderWidth: 1 },
  stateDot: { width: 8, height: 8, borderRadius: 4 },
  connName: { color: palette.text, fontSize: 13, fontWeight: '700' },
  connStats: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 10 },

  addConn: {
    marginTop: 12,
    height: 40,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
    alignItems: 'center',
    justifyContent: 'center',
  },

  topicsHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  topicRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderRadius: 8,
    borderLeftWidth: 3,
  },
  qosPill: { paddingVertical: 1, paddingHorizontal: 4, borderRadius: 2 },

  messagesHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  messagesTerminal: {
    flex: 1,
    marginTop: 12,
    padding: 10,
    backgroundColor: palette.background,
    borderColor: palette.border,
    borderWidth: 1,
    borderRadius: 8,
  },
  messageLine: { flexDirection: 'row', gap: 8, paddingBottom: 4 },
  messagePayload: { fontSize: 10.5, flex: 1, minWidth: 0 },
  });
}
