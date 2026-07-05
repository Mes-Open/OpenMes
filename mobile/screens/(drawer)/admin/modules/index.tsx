/**
 * Modules — mirrors the web admin modules page: each installed module is a row
 * with its display name, package id + version, an error flag and an enable
 * toggle. Data via REST useModules / useToggleModule.
 */
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useModules, useToggleModule } from '@/hooks/queries/useSystem';

export function ModulesList() {
  const { t } = useTranslation();
  const query = useModules();
  const toggleMutation = useToggleModule();

  const items = query.data ?? [];

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Modules')}</Text>
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
          {items.length === 0 ? <Text style={styles.empty}>{t('No modules.')}</Text> : null}
          {items.map((item) => (
            <View key={item.name} style={styles.row}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <View style={styles.titleRow}>
                  <Text style={styles.name} numberOfLines={1}>{item.display_name}</Text>
                  {item.has_error ? (
                    <View style={styles.errPill}>
                      <Mono size={9} color={colors.blocked} letterSpacing={0.6}>{t('Error').toUpperCase()}</Mono>
                    </View>
                  ) : null}
                </View>
                <Mono size={10} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 2 }}>
                  {item.name}{item.version ? ` · v${item.version}` : ''}
                </Mono>
                {item.description ? (
                  <Text style={styles.description}>{item.description}</Text>
                ) : null}
              </View>
              <Switch
                value={item.enabled}
                onValueChange={(v) =>
                  toggleMutation.mutate(
                    { name: item.name, enabled: v },
                    { onError: (e: Error) => Alert.alert(t('Failed'), e.message) },
                  )
                }
              />
            </View>
          ))}
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 6,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line2,
  },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  errPill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: radius.sm, backgroundColor: colors.blockedBg },
  description: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: 8, lineHeight: 18 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
