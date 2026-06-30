import { FontAwesome } from '@expo/vector-icons';
import { formatDistanceToNowStrict, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Card } from '@/components/ui/Card';
import { HubGrid, HubTile } from '@/components/ui/HubTile';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAuditLogs } from '@/hooks/queries/useAuditLogs';
import { useShifts } from '@/hooks/queries/useOps';
import { useProductTypes } from '@/hooks/queries/useProductTypes';

const RECENT_ENTITY_LABEL: Record<string, string> = {
  ProductType: 'Product type',
  ProcessTemplate: 'Process template',
  TemplateStep: 'Template step',
  Shift: 'Shift',
  LineStatus: 'Line status',
};

export function ProductionHub() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const productTypesQ = useProductTypes({ include_inactive: true });
  const shiftsQ = useShifts({ include_inactive: true });

  // Recent edits across the production catalog. The backend doesn't have a
  // dedicated "recently edited" endpoint, so we filter audit logs by entity
  // type. TODO(api/recent-edits): replace with a single per-resource endpoint
  // when available.
  const recentQ = useAuditLogs({ per_page: 10 });
  const recent = (recentQ.data?.data ?? []).filter((r) =>
    Object.keys(RECENT_ENTITY_LABEL).includes(r.entity_type),
  ).slice(0, 4);

  const productTypeCount = productTypesQ.data?.length ?? 0;
  const shiftCount = shiftsQ.data?.length ?? 0;
  const templateCount = (productTypesQ.data ?? []).reduce(
    (sum, pt) => sum + (pt.process_templates_count ?? 0),
    0,
  );

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title={t('Production')} subtitle={`${t('CATALOG').toUpperCase()} · ${t('TEMPLATES').toUpperCase()} · ${t('SCHEDULING').toUpperCase()}`} />
      <ScrollView contentContainerStyle={styles.scroll}>
        <HubGrid>
          <HubTile
            icon="cube"
            label={t('Product types')}
            sub={t('Categories')}
            count={productTypeCount}
            accent
            onPress={() => router.push('/(drawer)/production/product-types' as never)}
          />
          <HubTile
            icon="flask"
            label={t('Process templates')}
            sub={t('Versioned recipes')}
            count={templateCount || undefined}
            onPress={() => router.push('/(drawer)/production/product-types' as never)}
          />
          <HubTile
            icon="clock-o"
            label={t('Shifts')}
            sub={t('Work patterns')}
            count={shiftCount}
            onPress={() => router.push('/(drawer)/production/shifts' as never)}
          />
          <HubTile
            icon="columns"
            label={t('Line statuses')}
            sub={t('Kanban columns')}
            onPress={() => router.push('/(drawer)/structure' as never)}
          />
          <HubTile
            icon="exclamation-triangle"
            label={t('Downtime')}
            sub={t('History & reasons')}
            onPress={() => router.push('/(drawer)/production/downtime' as never)}
          />
        </HubGrid>

        <View>
          <SectionLabel
            right={
              <Mono size={11} color={palette.textFaint}>
                {recent.length === 0 ? '—' : `${t('LAST').toUpperCase()} ${recent.length}`}
              </Mono>
            }>
            {t('Recently edited')}
          </SectionLabel>
          {recent.length === 0 ? (
            <Card style={{ alignItems: 'center', padding: 24, gap: 6 }}>
              <FontAwesome name="history" size={20} color={palette.textFaint} />
              <Mono size={11} color={palette.textFaint}>{t('No recent edits').toUpperCase()}</Mono>
            </Card>
          ) : (
            <Card style={{ padding: 0, overflow: 'hidden' }}>
              {recent.map((r, i) => {
                const ago = (() => {
                  try {
                    return formatDistanceToNowStrict(parseISO(r.created_at), { addSuffix: false });
                  } catch {
                    return '—';
                  }
                })();
                return (
                  <View
                    key={r.id}
                    style={[
                      styles.recentRow,
                      i < recent.length - 1
                        ? {
                            borderBottomColor: palette.border,
                            borderBottomWidth: StyleSheet.hairlineWidth,
                          }
                        : null,
                    ]}>
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>
                        {(RECENT_ENTITY_LABEL[r.entity_type] ?? r.entity_type).toUpperCase()}
                      </Mono>
                      <Text
                        style={{ color: palette.text, fontSize: 13, fontWeight: '500', marginTop: 3 }}
                        numberOfLines={1}>
                        {r.entity_name ?? `#${r.entity_id}`}
                      </Text>
                    </View>
                    <View style={{ alignItems: 'flex-end' }}>
                      <Mono size={10.5} color={palette.textMuted}>
                        {(r.user?.name ?? r.user?.username ?? 'system').toUpperCase()}
                      </Mono>
                      <Mono size={10.5} color={palette.textFaint}>{ago}</Mono>
                    </View>
                    <FontAwesome name="chevron-right" size={11} color={palette.textFaint} />
                  </View>
                );
              })}
            </Card>
          )}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 18, gap: 18, paddingBottom: 32 },
  recentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
});
