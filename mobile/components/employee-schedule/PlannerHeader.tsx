// Shared header for the employee-schedule routes.
//
// Layout matches the tablet design header in
// /tmp/design-fetch/openmes-test-remix/project/om-screens-tacho.jsx:
//
//   ┌───────────────────────────────────────────────────────────────┐
//   │ ‹ EMPLOYEE DAY PLAN · TACHO VIEW       [DAY|WEEK|MONTH] [+]    │
//   │   Marcin Kowalski · TUE 24 MAY                                 │
//   └───────────────────────────────────────────────────────────────┘
//
// On phone the right cluster (tabs + add button) wraps below the title.

import { useRouter, useLocalSearchParams } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { Mono, Sans } from '@/components/ui/Mono';
import { TabletStatusStripLive } from '@/components/tablet/TabletStatusStripLive';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';

export type ViewMode = 'day' | 'week' | 'month';

interface Props {
  /** Big title — e.g. worker name on Day, "A-shift · Tuesday 24 May" on Team. */
  title: string;
  /** Eyebrow above title — "EMPLOYEE DAY PLAN · TACHO VIEW" etc. */
  eyebrow?: string;
  /** Highlights the active segment in the right cluster. */
  current: ViewMode;
  /** When set, an amber pill on the right fires this. Pass null/undefined to hide. */
  onAdd?: () => void;
  /** Pill label for the add button. Defaults to "Add activity". */
  addLabel?: string;
  /** Whether to show the "Team day" secondary button next to the tabs. */
  showTeamButton?: boolean;
  onTeamDay?: () => void;
  /** Hide the segmented Day/Week/Month tabs (used on the Team Day route). */
  hideTabs?: boolean;
}

export function PlannerHeader({
  title,
  eyebrow,
  current,
  onAdd,
  addLabel,
  showTeamButton,
  onTeamDay,
  hideTabs,
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const router = useRouter();
  const params = useLocalSearchParams();
  const { useTabletLayout } = useDeviceClass();
  const insets = useSafeAreaInsets();

  const goBack = () => {
    // Back navigation is scoped to the planner itself: from Week / Month /
    // Team-day land on the Day view (the canonical entry point); from Day
    // land on the admin hub. router.back() would otherwise drop the user
    // on whatever distinct route preceded the planner — e.g. /admin/schedule
    // — because setParams calls within the planner don't push history
    // entries on RN-Web.
    if (current !== 'day' || hideTabs) {
      router.replace('/admin/employee-schedule?view=day');
      return;
    }
    if (router.canGoBack()) {
      router.back();
    } else {
      router.replace('/admin');
    }
  };

  const setMode = (m: ViewMode) => {
    router.setParams({ ...params, view: m });
  };

  return (
    <View>
      {/* On tablet the status strip (clock + date + shift + MQTT) covers the
          notch area — match every other screen in the app. On phone the
          drawer/stack header already shows the time, so we only pad for the
          notch. */}
      {/* Pass the active scheme through so the strip follows dark mode —
          ScreenHeader does the same. Without this it stays warm-light
          while the rest of the page goes dark. */}
      {useTabletLayout ? <TabletStatusStripLive dark={scheme === 'dark'} /> : null}
      <View
      style={[
        styles.bar,
        {
          backgroundColor: palette.surface,
          borderColor: palette.border,
          paddingTop: useTabletLayout ? 12 : insets.top + 12,
        },
      ]}>
      {/* Left: back + title block.
          Back button hides on the canonical Day view when the permanent
          sidebar is up — there's no parent inside the planner to go back
          to, and the sidebar already covers section-level nav. It re-
          appears on Week / Month / Team-day (where it returns to Day) and
          on phone, where the sidebar is a slide-over drawer. */}
      <View style={styles.titleBlock}>
        {useTabletLayout && current === 'day' && !hideTabs ? null : (
          <Pressable
            onPress={goBack}
            hitSlop={12}
            style={[
              styles.backBtn,
              { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
            ]}>
            <FontAwesome name="chevron-left" size={12} color={palette.text} />
          </Pressable>
        )}
        <View style={{ flex: 1, minWidth: 0 }}>
          {eyebrow ? (
            <Mono
              size={10}
              weight="700"
              color={BRAND.amber}
              letterSpacing={0.7}
              upper>
              {eyebrow}
            </Mono>
          ) : null}
          <Sans
            size={useTabletLayout ? 22 : 18}
            weight="700"
            color={palette.text}
            letterSpacing={-0.4}
            style={{ marginTop: 2 }}
            numberOfLines={1}>
            {title}
          </Sans>
        </View>
      </View>

      {/* Right: view-mode segmented control + add button */}
      <View style={styles.rightCluster}>
        {showTeamButton && onTeamDay ? (
          <Pressable
            onPress={onTeamDay}
            style={[
              styles.teamBtn,
              { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
            ]}>
            <FontAwesome name="users" size={11} color={palette.text} />
            <Mono size={10.5} weight="700" color={palette.text} letterSpacing={0.5} upper>
              {t('Team day')}
            </Mono>
          </Pressable>
        ) : null}

        {!hideTabs ? (
        <View
          style={[
            styles.tabGroup,
            { backgroundColor: palette.surfaceAlt },
          ]}>
          {(['day', 'week', 'month'] as const).map((k) => {
            const on = current === k;
            const label = k === 'day' ? t('Day') : k === 'week' ? t('Week') : t('Month');
            return (
              <Pressable
                key={k}
                onPress={() => setMode(k)}
                style={[
                  styles.tab,
                  on ? { backgroundColor: BRAND.amber } : null,
                ]}>
                <Mono
                  size={11}
                  weight="700"
                  color={on ? '#1a1208' : palette.textMuted}
                  letterSpacing={0.5}
                  upper>
                  {label}
                </Mono>
              </Pressable>
            );
          })}
        </View>
        ) : null}

        {onAdd ? (
          <Pressable
            onPress={onAdd}
            style={[styles.addBtn, { backgroundColor: BRAND.amber }]}>
            <FontAwesome name="plus" size={12} color="#1a1208" />
            <Mono
              size={11}
              weight="700"
              color="#1a1208"
              letterSpacing={0.5}
              upper>
              {addLabel ?? t('Add activity')}
            </Mono>
          </Pressable>
        ) : null}
      </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    gap: 12,
  },
  titleBlock: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
    minWidth: 220,
  },
  backBtn: {
    width: 32,
    height: 32,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rightCluster: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  tabGroup: {
    flexDirection: 'row',
    padding: 3,
    borderRadius: 10,
    gap: 2,
  },
  tab: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 7,
  },
  addBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 10,
  },
  teamBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
});
