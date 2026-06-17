import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { usePersonnelClasses } from '@/hooks/queries/usePersonnel';

export function PersonnelClassesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const query = usePersonnelClasses({ per_page: 100 });
  const classes = query.data?.data ?? [];
  const totalWorkers = classes.reduce((sum, c) => sum + (c.workers_count ?? 0), 0);

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title={t('Personnel classes')}
        subtitle={`ISA-95 · ${classes.length} ${t('classes').toUpperCase()} · ${totalWorkers} ${t('workers').toUpperCase()}`}
      />
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <View style={styles.container}>
          <View style={[styles.helpBlock, { backgroundColor: palette.surfaceAlt }]}>
            <Mono size={11} color={palette.textMuted} letterSpacing={0.3}>
              ⓘ {t('ISA-95 personnel classes group workers by role/skill. Cert expiry surfaces on worker detail.')}
            </Mono>
          </View>

          {classes.length === 0 ? (
            <EmptyState title={t('No personnel classes')} />
          ) : (
            <Card style={{ padding: 0 }}>
              {classes.map((c, i, arr) => (
                <Pressable
                  key={c.id}
                  onPress={() => router.push(`/hr/personnel-classes/${c.id}/edit` as never)}
                  style={({ pressed }) => [
                    styles.row,
                    i === arr.length - 1
                      ? null
                      : {
                          borderBottomWidth: StyleSheet.hairlineWidth,
                          borderBottomColor: palette.border,
                        },
                    pressed ? { opacity: 0.7 } : null,
                  ]}>
                  <View
                    style={[styles.iconBadge, { backgroundColor: palette.surfaceAlt }]}>
                    <FontAwesome name="users" size={20} color={palette.textMuted} />
                  </View>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text
                      style={[styles.rowTitle, { color: palette.text }]}
                      numberOfLines={1}>
                      {c.name}
                    </Text>
                    <Mono
                      size={10.5}
                      color={palette.textFaint}
                      letterSpacing={0.4}
                      style={{ marginTop: 4 }}>
                      {c.code} · {(c.required_skill_ids?.length ?? 0)}{' '}
                      {t('REQUIRED SKILLS').toUpperCase()}
                    </Mono>
                  </View>
                  <View style={{ alignItems: 'flex-end' }}>
                    <Mono size={16} color={palette.text} weight="700">
                      {c.workers_count ?? 0}
                    </Mono>
                    <Mono size={9} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 2 }}>
                      {t('MEMBERS').toUpperCase()}
                    </Mono>
                  </View>
                </Pressable>
              ))}
            </Card>
          )}

          <Pressable
            onPress={() => router.push('/hr/personnel-classes/new' as never)}
            style={({ pressed }) => [
              styles.addBtn,
              { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
            ]}>
            <FontAwesome name="plus" size={12} color={palette.text} />
            <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
              {t('NEW CLASS')}
            </Mono>
          </Pressable>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { padding: 16, gap: 12 },
  helpBlock: { padding: 12, borderRadius: 10 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 14,
    paddingHorizontal: 14,
  },
  iconBadge: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rowTitle: { fontSize: 13, fontWeight: '700' },
  addBtn: {
    marginTop: 4,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
});
