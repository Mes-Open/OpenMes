import { StyleSheet, View } from 'react-native';

import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  data: number[];
  /** Index of the bar to highlight in amber. Defaults to none. */
  highlightIndex?: number;
  /** Bars after this index are rendered in the dark "now/ahead" tone. */
  darkAfter?: number;
  height?: number;
}

/** Vertical bar chart built with plain Views — no SVG dependency. */
export function BarChart({ data, highlightIndex, darkAfter, height = 110 }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const max = Math.max(1, ...data);

  return (
    <View style={[styles.row, { height }]}>
      {data.map((v, i) => {
        const h = Math.max(2, Math.round((v / max) * height));
        const isHighlight = highlightIndex === i;
        const isDark = darkAfter != null && i > darkAfter;
        const bg = isHighlight
          ? BRAND.amber
          : isDark
          ? palette.surfaceInverse
          : palette.borderStrong;
        return (
          <View key={i} style={styles.col}>
            <View style={[styles.bar, { height: h, backgroundColor: bg }]} />
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'flex-end', gap: 3 },
  col: { flex: 1, alignItems: 'center', justifyContent: 'flex-end' },
  bar: { width: '100%', borderRadius: 1 },
});
