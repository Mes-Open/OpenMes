/**
 * Update check — read-only version/update status (GET /api/v1/system/update-check).
 * The server applies updates via the download-based updater; this surfaces the
 * installed vs latest version and whether an update is available.
 */
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useUpdateCheck } from '@/hooks/queries/useSystem';

export default function UpdateCheckPage() {
  const { t } = useTranslation();
  const q = useUpdateCheck();
  const d = q.data;
  const updateAvailable = !!d?.update_available;

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Update check" subtitle="ADMIN · SYSTEM" />
      {q.isLoading ? (
        <LoadingState />
      ) : q.isError ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView contentContainerStyle={styles.list}>
          <Card style={styles.hero}>
            <View
              style={[
                styles.badge,
                { backgroundColor: updateAvailable ? colors.downtimeBg : colors.runningBg },
              ]}>
              <Mono size={10} color={updateAvailable ? colors.downtime : colors.running} letterSpacing={0.8}>
                {updateAvailable ? t('UPDATE AVAILABLE') : t('UP TO DATE')}
              </Mono>
            </View>
            <Text style={styles.version}>{d?.current_version ?? '—'}</Text>
            <Mono size={10} color={colors.faint} letterSpacing={0.8}>
              {t('INSTALLED VERSION')}
            </Mono>
          </Card>

          <Card style={{ gap: 10 }}>
            <Row label={t('Installed')} value={d?.current_version ?? '—'} />
            <Row label={t('Latest')} value={d?.latest_version ?? '—'} />
            <Row label={t('Status')} value={updateAvailable ? t('Update available') : t('Up to date')} />
          </Card>

          <Mono size={10} color={colors.faint} style={styles.note}>
            {t('Updates are applied on the server via the download-based updater; this panel is read-only.')}
          </Mono>
        </ScrollView>
      )}
    </View>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Mono size={13} color={colors.ink}>
        {value}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  list: { padding: 18, gap: 14, maxWidth: 680, width: '100%', alignSelf: 'center' },
  hero: { alignItems: 'center', gap: 8, paddingVertical: 24 },
  badge: { paddingVertical: 4, paddingHorizontal: 10, borderRadius: 20 },
  version: { fontSize: 30, fontFamily: fonts.mono.native.medium, color: colors.ink },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.line2,
    paddingTop: 8,
  },
  rowLabel: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  note: { marginTop: 4, paddingHorizontal: 4, lineHeight: 15 },
});
