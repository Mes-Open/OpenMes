import { StyleSheet, Text, View, type ViewStyle } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import { TabletStatusStripLive } from '@/components/tablet/TabletStatusStripLive';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  /** Force dark surface — operator/wall screens use the dark palette. */
  dark?: boolean;
  /** Header line above the title, uppercase mono. */
  eyebrow?: string;
  /** Title, regular weight. */
  title: string;
  /** Optional right-aligned chrome — status pills, "+ NEW" button, OEE block, etc. */
  right?: React.ReactNode;
  bodyStyle?: ViewStyle;
  children: React.ReactNode;
}

/**
 * Common landscape chrome for tablet screens: a header bar with eyebrow + title
 * + right slot above a flex body that hosts the multi-pane grid.
 *
 * Pairs with the permanent TabletSidebar drawer — this component renders only
 * the right-hand content area.
 */
export function TabletShell({ dark, eyebrow, title, right, bodyStyle, children }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = dark ? Colors.dark : Colors[scheme];
  // Auto-translate eyebrow + title — call sites can pass either English keys
  // or pre-composed strings (e.g. "L-01 · AIR FILTER"); t() just passes
  // unknown keys through unchanged.
  const { t } = useTranslation();

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <TabletStatusStripLive dark={dark} />
      <View style={[styles.headerBar, { borderBottomColor: palette.border }]}>
        <View style={{ flex: 1, minWidth: 0 }}>
          {eyebrow ? (
            <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
              {t(eyebrow).toUpperCase()}
            </Mono>
          ) : null}
          <Text
            style={[styles.title, { color: palette.text }]}
            numberOfLines={1}>
            {t(title)}
          </Text>
        </View>
        {right ? <View style={styles.rightWrap}>{right}</View> : null}
      </View>
      <View style={[styles.body, bodyStyle]}>{children}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  headerBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
    paddingHorizontal: 24,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  title: { fontSize: 22, fontWeight: '600', letterSpacing: -0.3, marginTop: 2 },
  rightWrap: { flexDirection: 'row', alignItems: 'center', gap: 16 },
  body: { flex: 1, padding: 16 },
});
