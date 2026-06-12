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
import { useProcessSegments } from '@/hooks/queries/useProcessSegments';

export function ProcessSegmentsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const query = useProcessSegments({ per_page: 100 });
  const segments = query.data?.data ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title={t('Process segments')}
        subtitle={`ISA-95 · ${segments.length} ${t('segments').toUpperCase()}`}
      />
      {query.isLoading ? (
        <LoadingState />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <View style={styles.container}>
          <View style={[styles.helpBlock, { backgroundColor: palette.surfaceAlt }]}>
            <Mono size={11} color={palette.textMuted} letterSpacing={0.3}>
              ⓘ {t('Segments are reusable building blocks for process templates. One segment = one capability.')}
            </Mono>
          </View>

          {segments.length === 0 ? (
            <EmptyState title={t('No process segments')} />
          ) : (
            <Card style={{ padding: 0 }}>
              {segments.map((s, i, arr) => (
                <Pressable
                  key={s.id}
                  onPress={() => router.push(`/production/process-segments/${s.id}/edit` as never)}
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
                    <FontAwesome name="cog" size={20} color={palette.textMuted} />
                  </View>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text
                      style={[styles.rowTitle, { color: palette.text }]}
                      numberOfLines={1}>
                      {s.name}
                    </Text>
                    <Mono
                      size={10.5}
                      color={palette.textFaint}
                      letterSpacing={0.4}
                      style={{ marginTop: 4 }}>
                      {s.code}
                      {s.workstationType?.name ? ` · ${s.workstationType.name}` : ''}
                      {s.estimated_duration_minutes != null
                        ? ` · ${s.estimated_duration_minutes}m`
                        : ''}
                    </Mono>
                  </View>
                  <View style={{ alignItems: 'flex-end' }}>
                    <Mono size={9} color={palette.textFaint} letterSpacing={0.5}>
                      {t('USED BY').toUpperCase()}
                    </Mono>
                    <Mono size={14} color={palette.text} weight="700" style={{ marginTop: 2 }}>
                      {s.template_steps_count ?? s.templateSteps_count ?? 0}
                      <Mono size={9} color={palette.textFaint}> tmpl</Mono>
                    </Mono>
                  </View>
                </Pressable>
              ))}
            </Card>
          )}

          <Pressable
            onPress={() => router.push('/production/process-segments/new' as never)}
            style={({ pressed }) => [
              styles.addBtn,
              { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
            ]}>
            <FontAwesome name="plus" size={12} color={palette.text} />
            <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
              {t('NEW SEGMENT')}
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
});
