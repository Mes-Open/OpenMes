import { FontAwesome } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { LegendList } from '@legendapp/list';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

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
  emptyTitle = 'Nothing to show',
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const router = useRouter();
  const navigation = useNavigation();

  // Auto-detect back: if not explicitly menu and the parent Stack can pop,
  // the chrome should show a back arrow. Most list screens are sub-routes.
  const showBack = back ?? navigation.canGoBack();

  const handleNew =
    onNew ?? (newRoute ? () => router.push(newRoute as never) : undefined);

  const headerRight =
    rightSlot ??
    (handleNew ? (
      <Pressable
        onPress={handleNew}
        hitSlop={6}
        style={({ pressed }) => [
          styles.newBtn,
          { backgroundColor: BRAND.amber, opacity: pressed ? 0.85 : 1 },
        ]}>
        <FontAwesome name="plus" size={14} color="#1a1208" />
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
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        {headerBar}
        <LoadingState />
      </View>
    );
  }
  if (isError) {
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        {headerBar}
        <ErrorState error={error} onRetry={onRefresh} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      {headerBar}
      <LegendList
        style={{ backgroundColor: palette.background }}
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
                        onPress={() => onFilterChange?.(f.id)}
                        style={[
                          styles.chip,
                          {
                            backgroundColor: active ? palette.surfaceInverse : palette.surface,
                            borderColor: active ? palette.surfaceInverse : palette.border,
                          },
                        ]}>
                        <Text
                          style={{
                            fontFamily: 'GeistMono_500Medium',
                            fontSize: 11,
                            fontWeight: '600',
                            letterSpacing: 0.4,
                            color: active
                              ? scheme === 'dark'
                                ? '#171715'
                                : '#fff'
                              : palette.text,
                          }}>
                          {f.label}
                        </Text>
                        {f.count != null ? (
                          <Mono
                            size={10}
                            color={active ? (scheme === 'dark' ? '#171715' : '#fff') : palette.textFaint}>
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
        ListEmptyComponent={<EmptyState title={emptyTitle} subtitle={emptySubtitle} />}
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
  list: { padding: 18 },
  headerBlock: { gap: 12, marginBottom: 14 },
  newBtn: { width: 38, height: 38, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  chipsRow: { flexDirection: 'row', gap: 6 },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
});
