// Light-only v1: Colors[scheme] switching dropped — Geist White tokens.
import { FontAwesome } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { LegendList } from '@legendapp/list';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';

export interface FilterChip {
  id: string;
  label: string;
  count?: number | string;
}

interface Props<T> {
  /** Title shown in the header bar (e.g. "Workers"). */
  title?: string;
  /** Subtitle / metric line shown in the header bar (e.g. "HR · 14 ITEMS"). */
  eyebrow?: string;
  /** Render the styled chrome (back arrow / hamburger) above the list. */
  chrome?: boolean;
  back?: boolean;
  onBack?: () => void;
  /** Filter chips shown below the chrome. */
  filters?: FilterChip[];
  activeFilter?: string;
  onFilterChange?: (id: string) => void;
  /** "+ New" button — shown in the chrome's right slot if no `rightSlot` is set. */
  newRoute?: string;
  onNew?: () => void;
  /** Custom right slot in the header bar. Overrides the auto "+" new button. */
  rightSlot?: React.ReactNode;
  emptyTitle?: string;
  emptySubtitle?: string;
  items: T[];
  keyExtractor: (item: T, index: number) => string;
  renderItem: (item: T, index: number) => React.ReactNode;
  isLoading?: boolean;
  isError?: boolean;
  error?: unknown;
  isFetching?: boolean;
  onRefresh?: () => void;
  /** Extra header content rendered below filters. */
  extraHeader?: React.ReactNode;
}

export function ListScreen<T>({
  title,
  eyebrow,
  chrome = true,
  back,
  onBack,
  filters,
  activeFilter,
  onFilterChange,
  newRoute,
  onNew,
  rightSlot,
  emptyTitle,
  emptySubtitle,
  items,
  keyExtractor,
  renderItem,
  isLoading,
  isError,
  error,
  isFetching,
  onRefresh,
  extraHeader,
}: Props<T>) {
  const router = useRouter();
  const navigation = useNavigation();
  const { t } = useTranslation();

  // Auto-detect back: if not explicitly menu and the parent Stack can pop,
  // the chrome should show a back arrow. Most list screens are sub-routes.
  const showBack = back ?? navigation.canGoBack();

  const handleNew =
    onNew ?? (newRoute ? () => router.push(newRoute as never) : undefined);

  const headerRight =
    rightSlot ??
    (handleNew ? (
      <Pressable
        accessibilityRole="button"
        onPress={handleNew}
        hitSlop={6}
        style={({ pressed }) => [styles.newBtn, { opacity: pressed ? 0.85 : 1 }]}>
        <FontAwesome name="plus" size={14} color="#FFFFFF" />
      </Pressable>
    ) : undefined);

  const headerBar = chrome ? (
    <ScreenHeader
      back={showBack}
      onBack={onBack}
      title={title}
      subtitle={eyebrow}
      rightSlot={headerRight}
    />
  ) : null;

  if (isLoading) {
    return (
      <View style={styles.screen}>
        {headerBar}
        <LoadingState />
      </View>
    );
  }
  if (isError) {
    return (
      <View style={styles.screen}>
        {headerBar}
        <ErrorState error={error} onRetry={onRefresh} />
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      {headerBar}
      <LegendList
        style={{ backgroundColor: colors.bg }}
        data={items}
        keyExtractor={keyExtractor}
        contentContainerStyle={styles.list}
        ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
        ListHeaderComponent={
          (filters && filters.length > 0) || extraHeader ? (
            <View style={styles.headerBlock}>
              {filters && filters.length > 0 ? (
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={styles.chipsRow}>
                  {filters.map((f) => {
                    const active = activeFilter === f.id;
                    return (
                      <Pressable
                        key={f.id}
                        accessibilityRole="button"
                        accessibilityState={{ selected: active }}
                        onPress={() => onFilterChange?.(f.id)}
                        style={[styles.chip, active && styles.chipActive]}>
                        <Text style={[styles.chipText, active && styles.chipTextActive]}>
                          {f.label}
                        </Text>
                        {f.count != null ? (
                          <Mono size={10} color={active ? '#FFFFFF' : colors.faint}>
                            {f.count}
                          </Mono>
                        ) : null}
                      </Pressable>
                    );
                  })}
                </ScrollView>
              ) : null}

              {extraHeader}
            </View>
          ) : null
        }
        ListEmptyComponent={<EmptyState title={emptyTitle ?? t('Nothing to show')} subtitle={emptySubtitle} />}
        renderItem={({ item, index }) => <>{renderItem(item, index)}</>}
        refreshControl={
          onRefresh ? (
            <RefreshControl refreshing={!!isFetching} onRefresh={onRefresh} />
          ) : undefined
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  list: { padding: 18 },
  headerBlock: { gap: 12, marginBottom: 14 },
  newBtn: {
    width: 38,
    height: 38,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.accent,
  },
  chipsRow: { flexDirection: 'row', gap: 8 },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.card,
  },
  chipActive: {
    backgroundColor: colors.ink,
    borderColor: colors.ink,
  },
  chipText: {
    fontSize: 12,
    fontFamily: fonts.sans.native.semibold,
    color: colors.muted,
  },
  chipTextActive: { color: '#FFFFFF' },
});
