import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const router = useRouter();
  const { t } = useTranslation();

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        title={t(title)}
        subtitle={`${t(groupLabel ?? title).toUpperCase()} · ${items.length} ${t(items.length === 1 ? 'ITEM' : 'ITEMS')}`}
      />
      <ScrollView
        style={{ backgroundColor: palette.background }}
        contentContainerStyle={styles.container}>
        <Text style={[styles.subtitle, { color: palette.textMuted }]}>{t(subtitle)}</Text>

        <View style={styles.list}>
          {items.map((item) => {
            const enabled = item.available && !!item.route;
            return (
              <Pressable
                key={item.key}
                disabled={!enabled}
                onPress={() => enabled && router.push(item.route as never)}
                style={({ pressed }) => [
                  styles.row,
                  {
                    backgroundColor: palette.surface,
                    borderColor: palette.border,
                    opacity: !enabled ? 0.55 : pressed ? 0.85 : 1,
                  },
                ]}>
                <View
                  style={[
                    styles.iconWrap,
                    { backgroundColor: enabled ? '#fbe9c8' : palette.surfaceAlt },
                  ]}>
                  <FontAwesome
                    name={item.icon}
                    size={16}
                    color={enabled ? BRAND.amber : palette.textFaint}
                  />
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <View style={styles.titleRow}>
                    <Text style={[styles.itemTitle, { color: palette.text }]} numberOfLines={1}>
                      {t(item.label)}
                    </Text>
                    {!item.available ? (
                      <View style={[styles.soonPill, { backgroundColor: palette.surfaceAlt }]}>
                        <Mono size={9} color={palette.textFaint} letterSpacing={0.6}>{t('SOON')}</Mono>
                      </View>
                    ) : null}
                  </View>
                  <Text style={[styles.itemDesc, { color: palette.textMuted }]} numberOfLines={2}>
                    {t(item.description)}
                  </Text>
                </View>
                <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
              </Pressable>
            );
          })}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { padding: 18, gap: 14 },
  subtitle: { fontSize: 14, lineHeight: 20 },
  list: { gap: 8 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  iconWrap: { width: 40, height: 40, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  itemTitle: { fontSize: 15, fontWeight: '600', letterSpacing: -0.2 },
  soonPill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 4 },
  itemDesc: { fontSize: 12, marginTop: 4, lineHeight: 17 },
});
