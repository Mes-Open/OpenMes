import { useRouter } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';
import { FontAwesome } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { EmptyState } from '@/components/ui/StateViews';
import { Mono } from '@/components/ui/Mono';
import { BrandLogo } from '@/components/ui/Brand';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAuthStore } from '@/stores/authStore';

export function SelectLineScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const user = useAuthStore((s) => s.user);
  const setActiveLineId = useAuthStore((s) => s.setActiveLineId);
  const { t } = useTranslation();

  const lines = user?.lines ?? [];

  if (lines.length === 0) {
    return (
      <EmptyState
        title={t('No lines assigned')}
        subtitle={t('Ask an admin to assign you to a production line.')}
      />
    );
  }

  return (
    <View style={[styles.container, { backgroundColor: palette.background, paddingTop: insets.top + 16 }]}>
      <View style={styles.header}>
        <BrandLogo size={16} color={palette.text} />
        <Mono size={10} color={palette.textFaint} letterSpacing={0.8}>
          {t('Step')} 2 / 2
        </Mono>
      </View>

      <View style={styles.heroBlock}>
        <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
          {(user?.username ?? '').toUpperCase()} · {lines.length} {t(lines.length === 1 ? 'LINE' : 'LINES')}
        </Mono>
        <Text style={[styles.heading, { color: palette.text }]}>{t('Choose your line')}</Text>
        <Text style={[styles.subheading, { color: palette.textMuted }]}>
          {t("You'll only see work orders for the line you select. You can switch later from the menu.")}
        </Text>
      </View>

      <LegendList
        data={lines}
        keyExtractor={(line) => String(line.id)}
        contentContainerStyle={{ gap: 10, padding: 18, paddingTop: 8 }}
        renderItem={({ item }) => (
          <Pressable
            onPress={() => {
              setActiveLineId(item.id);
              router.replace('/(drawer)/(tabs)' as never);
            }}
            style={({ pressed }) => [
              styles.lineRow,
              {
                backgroundColor: palette.surface,
                borderColor: palette.border,
                opacity: pressed ? 0.85 : 1,
              },
            ]}>
            <View style={[styles.lineBadge, { backgroundColor: palette.surfaceAlt }]}>
              <Mono size={11} color={palette.text} weight="700">
                {(item.code ?? `L${item.id}`).slice(0, 4).toUpperCase()}
              </Mono>
            </View>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={[styles.lineName, { color: palette.text }]} numberOfLines={1}>
                {item.name}
              </Text>
              {item.code ? (
                <Mono size={11} color={palette.textFaint}>{item.code}</Mono>
              ) : null}
            </View>
            <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
          </Pressable>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 18,
    paddingBottom: 8,
  },
  heroBlock: { paddingHorizontal: 18, paddingTop: 14, paddingBottom: 6, gap: 6 },
  heading: { fontSize: 28, fontWeight: '600', letterSpacing: -0.6, marginTop: 4 },
  subheading: { fontSize: 13, lineHeight: 19, marginTop: 2 },
  lineRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  lineBadge: {
    width: 44,
    height: 44,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  lineName: { fontSize: 15, fontWeight: '600', letterSpacing: -0.2 },
});
