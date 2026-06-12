// 24-hour tachograph timeline — colored activity blocks across a horizontal
// bar, with optional NOW marker, hour grid, and highlight on a single segment.
// Used on the day plan summary band, team-day stacked rows, and month-cell
// mini strips.

import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View, type LayoutChangeEvent, type ViewStyle } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { Mono } from '@/components/ui/Mono';
import { iconForActivity } from '@/components/employee-schedule/activityIcons';
import type { ActivityType, DaySegment, TypeMetaMap } from '@/api/employeeActivities';
import { toMinutes } from '@/api/employeeActivities';

const TOTAL_MIN = 24 * 60;
const HOUR_LABELS = [0, 3, 6, 9, 12, 15, 18, 21, 24] as const;

interface Props {
  segments: DaySegment[];
  typeMeta: TypeMetaMap;
  height?: number;
  /** Show the 00/03/.../24 ruler below the bar. */
  showHours?: boolean;
  /** When provided, this segment id gets a white border + amber halo. */
  highlightSegmentId?: number | null;
  /** Minutes-since-midnight for the NOW pin. null hides it. */
  nowMinutes?: number | null;
  /** When true, the timeline draws on the dark operator surface regardless
   *  of the active color scheme — for use on dark cards. */
  forceDark?: boolean;
  style?: ViewStyle;
  /** Fired with the activity id when the user taps a colored block. Only
   *  real (non-gap) segments are clickable. */
  onSegmentPress?: (segmentId: number) => void;
}

function segmentColor(type: ActivityType, meta: TypeMetaMap, customCode?: string | null): string {
  const m = meta[type];
  if (m) return m.color;
  return '#94a3b8';
}

export function ActivityTimeline({
  segments,
  typeMeta,
  height = 56,
  showHours = true,
  highlightSegmentId,
  nowMinutes,
  forceDark,
  style,
  onSegmentPress,
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = forceDark ? Colors.dark : Colors[scheme];
  const { t } = useTranslation();
  const [hoveredId, setHoveredId] = useState<number | null>(null);
  const [barWidth, setBarWidth] = useState(0);

  const blocks = useMemo(() => {
    return segments.map((s, i) => {
      const from = toMinutes(s.from);
      const toStr = s.to === '24:00' ? '23:59' : s.to;
      const to = toMinutes(toStr);
      const leftPct = (from / TOTAL_MIN) * 100;
      const widthPct = s.to === '24:00'
        ? 100 - leftPct
        : ((to - from) / TOTAL_MIN) * 100;
      return {
        key: `${i}-${s.id ?? 'gap'}-${s.from}`,
        id: s.id,
        type: s.type,
        // User-typed s.label stays as-is; catalog label is translated.
        label: s.label ?? (typeMeta[s.type]?.label ? t(typeMeta[s.type].label) : s.type),
        leftPct,
        widthPct,
        color: segmentColor(s.type, typeMeta, s.custom_code),
        highlighted: highlightSegmentId != null && s.id === highlightSegmentId,
      };
    });
  }, [segments, typeMeta, highlightSegmentId]);

  // Icon sizing scales with bar height; only render if the block has room
  // for it without the icon overflowing left/right.
  const iconSize = Math.max(10, Math.min(20, Math.round(height * 0.4)));
  const ICON_MIN_PX = iconSize + 6; // icon + 3px horizontal padding each side
  // Show the label below the icon only if the bar is tall enough to stack
  // them AND the block is wide enough to fit a few characters.
  const labelSize = Math.max(8, Math.min(11, Math.round(height * 0.18)));
  const canStackLabel = height >= 48;
  const LABEL_MIN_PX = 36; // ~3–4 characters at labelSize

  const onBarLayout = (e: LayoutChangeEvent) => {
    const w = e.nativeEvent.layout.width;
    if (w !== barWidth) setBarWidth(w);
  };

  return (
    <View style={style}>
      <View
        onLayout={onBarLayout}
        style={[
          styles.bar,
          {
            height,
            backgroundColor: forceDark ? '#F6F5F1' : palette.surfaceAlt,
            borderColor: palette.border,
          },
        ]}>
        {/* Hour grid lines */}
        {Array.from({ length: 24 }).map((_, h) => (
          <View
            key={`grid-${h}`}
            style={[
              styles.gridLine,
              {
                left: `${(h / 24) * 100}%`,
                backgroundColor:
                  h % 6 === 0 ? palette.borderStrong : palette.border,
                opacity: h % 6 === 0 ? 0.6 : 0.35,
              },
            ]}
          />
        ))}

        {/* Activity blocks */}
        {blocks.map((b) => {
          const clickable = onSegmentPress != null && b.id != null;
          const isHovered = b.id != null && hoveredId === b.id;
          // Pixel width of this block (best-effort — barWidth is 0 on the
          // first paint, so the icon flashes in once we know the geometry).
          const blockPx = (barWidth * b.widthPct) / 100;
          const showIcon = b.type !== 'off' && blockPx >= ICON_MIN_PX;
          const showLabel =
            showIcon && canStackLabel && blockPx >= LABEL_MIN_PX;
          // Highlighted (selected) blocks fill the full bar height; hovered
          // blocks get a soft ring without taking space from neighbours.
          return (
            <Pressable
              key={b.key}
              onPress={
                clickable && b.id != null
                  ? () => onSegmentPress!(b.id as number)
                  : undefined
              }
              // RN-Web fires onHover via these handlers; on native they're no-ops.
              onHoverIn={clickable ? () => setHoveredId(b.id ?? null) : undefined}
              onHoverOut={clickable ? () => setHoveredId(null) : undefined}
              style={[
                styles.block,
                {
                  left: `${b.leftPct}%`,
                  width: `${b.widthPct}%`,
                  top: b.highlighted ? 0 : 2,
                  bottom: b.highlighted ? 0 : 2,
                  backgroundColor: b.color,
                  borderWidth: b.highlighted ? 2 : isHovered ? 1.5 : 0,
                  borderColor: b.highlighted ? '#fff' : isHovered ? '#fff' : 'transparent',
                  // Subtle hover glow to make the hit target obvious on web
                  ...(isHovered && !b.highlighted
                    ? { boxShadow: '0px 0px 4px rgba(255, 255, 255, 0.5)' }
                    : null),
                  cursor: clickable ? ('pointer' as any) : undefined,
                },
              ]}>
              {showIcon ? (
                <FontAwesome
                  name={iconForActivity(b.type)}
                  size={iconSize}
                  // White on saturated bands reads well across the whole
                  // palette — colored icons disappear into matching block.
                  color="rgba(255,255,255,0.92)"
                />
              ) : null}
              {showLabel ? (
                <Text
                  numberOfLines={1}
                  ellipsizeMode="clip"
                  style={{
                    marginTop: 2,
                    paddingHorizontal: 4,
                    fontSize: labelSize,
                    fontWeight: '700',
                    letterSpacing: 0.3,
                    color: 'rgba(255,255,255,0.95)',
                    maxWidth: '100%',
                  }}>
                  {b.label}
                </Text>
              ) : null}
            </Pressable>
          );
        })}

        {/* NOW indicator */}
        {nowMinutes != null && nowMinutes >= 0 && nowMinutes <= TOTAL_MIN ? (
          <View
            style={[
              styles.nowLine,
              { left: `${(nowMinutes / TOTAL_MIN) * 100}%`, pointerEvents: 'none' },
            ]}>
            <View style={styles.nowDot} />
          </View>
        ) : null}
      </View>

      {showHours ? (
        <View style={styles.hoursRow}>
          {HOUR_LABELS.map((h) => (
            <Mono
              key={`hl-${h}`}
              size={8.5}
              color={palette.textFaint}
              letterSpacing={0.3}>
              {String(h).padStart(2, '0')}
            </Mono>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    position: 'relative',
    overflow: 'hidden',
    borderRadius: 10,
    borderWidth: 1,
  },
  gridLine: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    width: 1,
  },
  block: {
    position: 'absolute',
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
  },
  nowLine: {
    position: 'absolute',
    top: -3,
    bottom: -3,
    width: 2,
    backgroundColor: '#ffffff',
    zIndex: 5,
  },
  nowDot: {
    position: 'absolute',
    top: -5,
    left: -4,
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#ffffff',
    borderWidth: 2,
    borderColor: BRAND.amberAccent,
  },
  hoursRow: {
    marginTop: 4,
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
});
