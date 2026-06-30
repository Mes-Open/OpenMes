// Light-only v1: Colors[scheme] switching dropped — Geist White tokens.
import { StyleSheet, View } from 'react-native';

import { colors } from '@openmes/ui';

interface Props {
  data: number[];
  /** Index of the bar to highlight in the accent orange. Defaults to none. */
  highlightIndex?: number;
  /** Bars after this index are rendered in the dark "now/ahead" tone. */
  darkAfter?: number;
  height?: number;
}

/** Vertical bar chart built with plain Views — no SVG dependency. */
export function BarChart({ data, highlightIndex, darkAfter, height = 110 }: Props) {
  const max = Math.max(1, ...data);

  return (
    <View style={[styles.row, { height }]}>
      {data.map((v, i) => {
        const h = Math.max(2, Math.round((v / max) * height));
        const isHighlight = highlightIndex === i;
        const isDark = darkAfter != null && i > darkAfter;
        const bg = isHighlight ? colors.accent : isDark ? colors.ink : colors.faintest;
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
