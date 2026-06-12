// Light-only v1: Colors[scheme] switching dropped — Geist White tokens.
import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';

export interface HubItem {
  key: string;
  label: string;
  description: string;
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  route?: string;
  available: boolean;
}

interface Props {
  title: string;
  subtitle: string;
  items: HubItem[];
  groupLabel?: string;
}

export function HubScreen({ title, subtitle, items, groupLabel }: Props) {
  const router = useRouter();
  const { t } = useTranslation();

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title={t(title)}
        subtitle={`${t(groupLabel ?? title).toUpperCase()} · ${items.length} ${t(items.length === 1 ? 'ITEM' : 'ITEMS')}`}
      />
      <ScrollView style={{ backgroundColor: colors.bg }} contentContainerStyle={styles.container}>
        <Text style={styles.subtitle}>{t(subtitle)}</Text>

        <View style={styles.list}>
          {items.map((item) => {
            const enabled = item.available && !!item.route;
            return (
              <Pressable
                key={item.key}
                accessibilityRole="button"
                disabled={!enabled}
                onPress={() => enabled && router.push(item.route as never)}
                style={({ pressed }) => [
                  styles.row,
                  { opacity: !enabled ? 0.55 : pressed ? 0.85 : 1 },
                ]}>
                <View
                  style={[
                    styles.iconWrap,
                    { backgroundColor: enabled ? `${colors.accent}1A` : colors.chip },
                  ]}>
                  <FontAwesome
                    name={item.icon}
                    size={16}
                    color={enabled ? colors.accent : colors.faint}
                  />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <View style={styles.titleRow}>
                    <Text style={styles.itemTitle} numberOfLines={1}>
                      {t(item.label)}
                    </Text>
                    {!item.available ? (
                      <View style={styles.soonPill}>
                        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('SOON')}</Mono>
                      </View>
                    ) : null}
                  </View>
                  <Text style={styles.itemDesc} numberOfLines={2}>
                    {t(item.description)}
                  </Text>
                </View>
                <FontAwesome name="chevron-right" size={12} color={colors.faintest} />
              </Pressable>
            );
          })}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  container: { padding: 18, gap: 14 },
  subtitle: { fontSize: 14, lineHeight: 20, color: colors.muted, fontFamily: fonts.sans.native.regular },
  list: { gap: 8 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    padding: 14,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.card,
  },
  iconWrap: { width: 40, height: 40, borderRadius: radius.sm, alignItems: 'center', justifyContent: 'center' },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  itemTitle: { fontSize: 15, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  soonPill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 4, backgroundColor: colors.chip },
  itemDesc: {
    fontSize: 12,
    marginTop: 4,
    lineHeight: 17,
    color: colors.muted,
    fontFamily: fonts.sans.native.regular,
  },
});
