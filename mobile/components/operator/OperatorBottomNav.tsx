/**
 * Operator bottom navigation — phone companion to the OperatorTopBar. On
 * handsets the top bar's Queue/Workstation pills and "Switch Line" button
 * don't fit, so they live here instead (the top bar keeps logo, line name,
 * online dot and the user/logout row). Tablet keeps everything in the top bar
 * and never renders this.
 */
import { usePathname, useRouter } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { HeroIcon, type HeroIconKey } from '@/components/ui/HeroIcon';

interface Item {
  key: string;
  label: string;
  icon: HeroIconKey;
  route: string;
  match: string;
}

const ITEMS: Item[] = [
  { key: 'queue', label: 'Queue', icon: 'clipboard', route: '/operator/queue', match: '/operator/queue' },
  { key: 'workstation', label: 'Workstation', icon: 'cog', route: '/operator/workstation', match: '/operator/workstation' },
  { key: 'switch', label: 'Switch Line', icon: 'switchLine', route: '/select-line', match: '/select-line' },
];

export function OperatorBottomNav() {
  const { t } = useTranslation();
  const router = useRouter();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.bar, { paddingBottom: Math.max(insets.bottom, 8) }]}>
      {ITEMS.map((item) => {
        const active = pathname.startsWith(item.match);
        const tint = active ? colors.ink : colors.faint;
        return (
          <Pressable
            key={item.key}
            onPress={() => router.push(item.route as never)}
            style={({ pressed }) => [styles.item, pressed && { opacity: 0.6 }]}>
            <HeroIcon name={item.icon} size={22} color={tint} />
            <Text style={[styles.label, { color: tint }]}>{t(item.label)}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    backgroundColor: colors.card,
    borderTopWidth: 1,
    borderTopColor: colors.line,
    paddingTop: 8,
  },
  item: { flex: 1, alignItems: 'center', gap: 3 },
  label: { fontSize: 10.5, fontFamily: fonts.sans.native.medium },
});
