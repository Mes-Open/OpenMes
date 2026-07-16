/**
 * Select Production Line — native 1:1 port of the web operator SelectLine page
 * (backend/resources/js/Pages/operator/SelectLine.jsx). Minimal operator top-bar
 * chrome (logo + ONLINE + user), an h1 + subtitle, then one card per assigned
 * line: name + Active pill, description, an optional Workstation dropdown, and an
 * orange "Select ›" button. Selecting stores the line AND the chosen workstation,
 * then routes operators to /operator/queue (admins/supervisors keep the drawer
 * Orders view).
 */
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button, Dropdown, StatusPill, colors, fonts, radius } from '@openmes/ui';

import { OperatorTopBar } from '@/components/operator/OperatorTopBar';
import { Mono } from '@/components/ui/Mono';
import { EmptyState } from '@/components/ui/StateViews';
import { useWorkstations } from '@/hooks/queries/useLines';
import { getRole, useAuthStore } from '@/stores/authStore';
import type { Line } from '@/types/api';

export function SelectLineScreen() {
  const { t } = useTranslation();
  const user = useAuthStore((s) => s.user);
  const lines = user?.lines ?? [];

  return (
    <View style={styles.screen}>
      <OperatorTopBar variant="minimal" />
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.head}>
          <Text style={styles.h1}>{t('Select Production Line')}</Text>
          <Text style={styles.subtitle}>{t('Choose a production line and optionally a workstation')}</Text>
        </View>

        {lines.length === 0 ? (
          <EmptyState
            title={t('No lines assigned')}
            subtitle={t('You are not assigned to any production lines. Please contact your administrator.')}
          />
        ) : (
          <View style={styles.grid}>
            {lines.map((line) => (
              <LineCard key={line.id} line={line} />
            ))}
          </View>
        )}
      </ScrollView>
    </View>
  );
}

function LineCard({ line }: { line: Line }) {
  const { t } = useTranslation();
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const setActiveLineId = useAuthStore((s) => s.setActiveLineId);
  const setActiveWorkstationId = useAuthStore((s) => s.setActiveWorkstationId);

  const [workstationId, setWorkstationId] = useState('');
  const workstationsQuery = useWorkstations(line.id);
  const workstations = workstationsQuery.data ?? [];

  const select = () => {
    // setActiveLineId clears the workstation, so set the workstation second.
    setActiveLineId(line.id);
    setActiveWorkstationId(workstationId ? Number(workstationId) : null);
    const role = getRole(user);
    if (role === 'Operator') {
      router.replace('/operator/queue' as never);
    } else {
      router.replace('/(drawer)/orders/work-orders' as never);
    }
  };

  return (
    <View style={styles.card}>
      <View style={styles.cardHead}>
        <Text style={styles.cardName} numberOfLines={1}>
          {line.name}
        </Text>
        <StatusPill status="running" label={t('Active')} />
      </View>

      {line.description ? <Text style={styles.desc}>{line.description}</Text> : null}

      <View style={styles.wsBlock}>
        {workstations.length > 0 ? (
          <>
            <Mono size={9.5} upper color={colors.faint} letterSpacing={0.8} style={styles.wsLabel}>
              {t('Workstation')} <Text style={styles.wsOptional}>{t('(optional)')}</Text>
            </Mono>
            <Dropdown
              value={workstationId}
              onChange={(v) => setWorkstationId(v as string)}
              placeholder={t('All workstations')}
              options={[
                { value: '', label: t('All workstations') },
                ...workstations.map((ws) => ({
                  value: String(ws.id),
                  label: ws.code ? `${ws.name} (${ws.code})` : ws.name,
                })),
              ]}
            />
          </>
        ) : (
          <Text style={styles.noWs}>{t('No workstations')}</Text>
        )}
      </View>

      <Button variant="accent" size="lg" onPress={select} style={styles.selectBtn} rightIcon={<Text style={styles.chevron}>›</Text>}>
        {t('Select')}
      </Button>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 40, maxWidth: 900, width: '100%', alignSelf: 'center' },
  head: { marginBottom: 18 },
  h1: { fontSize: 26, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.5 },
  subtitle: { fontSize: 13, fontFamily: fonts.sans.native.regular, color: colors.muted, marginTop: 6 },
  grid: { gap: 14 },
  card: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 18,
  },
  cardHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginBottom: 12 },
  cardName: { flexShrink: 1, fontSize: 17, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.2 },
  desc: { fontSize: 13, fontFamily: fonts.sans.native.regular, color: colors.muted, marginBottom: 14 },
  wsBlock: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2, paddingTop: 14, marginBottom: 14 },
  wsLabel: { marginBottom: 8 },
  wsOptional: { color: colors.faintest, textTransform: 'none', letterSpacing: 0 },
  noWs: { fontSize: 13, fontFamily: fonts.sans.native.regular, color: colors.faint },
  selectBtn: { width: '100%' },
  chevron: { color: '#fff', fontSize: 18, fontFamily: fonts.sans.native.semibold },
});
