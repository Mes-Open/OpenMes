// Type-legend pills row — one chip per activity-type that has duration on the
// day, in catalog order, with HH:mm total.

import FontAwesome from '@expo/vector-icons/FontAwesome';
import { ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { Mono, Sans } from '@/components/ui/Mono';
import type { ActivityType, TypeMetaMap } from '@/api/employeeActivities';
import { formatMinutes } from '@/api/employeeActivities';
import { iconForActivity } from '@/components/employee-schedule/activityIcons';

// Catalog order matches the design: productive types first, breaks/rest,
// support work, then off/custom (rendered separately).
const ORDER: ActivityType[] = [
  'work', 'break', 'rest', 'travel',
  'setup', 'meeting', 'maint', 'qc',
  'training', 'off', 'custom',
];

interface Props {
  summary: Partial<Record<ActivityType, number>>;
  typeMeta: TypeMetaMap;
  /** Hide 'off' totals (used on summary cards where off is meaningless). */
  hideOff?: boolean;
  forceDark?: boolean;
  scroll?: boolean;
}

export function ActivityLegendPills({
  summary,
  typeMeta,
  hideOff,
  forceDark,
  scroll,
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = forceDark ? Colors.dark : Colors[scheme];
  // `t` is taken below as a loop variable, so use `tr` for translations.
  const { t: tr } = useTranslation();

  const entries = ORDER
    .filter((t) => (summary[t] ?? 0) > 0)
    .filter((t) => (hideOff ? t !== 'off' : true));

  const inner = (
    <View style={[styles.row, scroll ? null : styles.wrap]}>
      {entries.map((t) => {
        const def = typeMeta[t];
        if (!def) return null;
        return (
          <View
            key={t}
            style={[
              styles.pill,
              {
                backgroundColor: palette.surface,
                borderColor: def.color + '66',
              },
            ]}>
            <FontAwesome name={iconForActivity(t)} size={11} color={def.color} />
            <Sans size={12} color={palette.text} weight="600">
              {tr(def.label)}
            </Sans>
            <Mono size={10} color={palette.textMuted} weight="600">
              {formatMinutes(summary[t] ?? 0)}
            </Mono>
          </View>
        );
      })}
    </View>
  );

  if (scroll) {
    return (
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ paddingRight: 16 }}>
        {inner}
      </ScrollView>
    );
  }
  return inner;
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: 6,
  },
  wrap: {
    flexWrap: 'wrap',
  },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 100,
    borderWidth: 1,
  },
});
