import { Alert } from 'react-native';
import Animated, {
  runOnJS,
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { Gesture, GestureDetector } from 'react-native-gesture-handler';
import { addMinutes, format, parseISO } from 'date-fns';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import { ScheduleConflictError } from '@/api/schedule';
import { useResizeScheduleOrder } from '@/hooks/queries/useSchedule';
import type { MockGanttBlock } from '@/api/system';

interface Props {
  block: MockGanttBlock;
  hourColWidth: number;
  rowHeight: number;
  /** Open the edit modal — used on tap. */
  onTap?: (b: MockGanttBlock) => void;
  /** Per-status fg/bg for the bar. */
  color: { bg: string; fg: string };
  /** Whether the current user can write to the schedule. */
  canEdit: boolean;
  /** Optional rounded-rect style overrides. */
  borderRadius?: number;
}

const SNAP_MINUTES = 15;

/**
 * Gantt block that supports:
 *   • Tap → open the edit modal (full datetime control + line picker).
 *   • Long-press → enter drag mode (bar lifts slightly), pan horizontally
 *     to reschedule on the same line. Release snaps to a 15-min grid and
 *     posts to /api/v1/schedule/{wo}/resize keeping the same duration.
 *
 * Vertical / cross-line moves and edge-resize are not supported here —
 * use the modal for those for now.
 */
export function DraggableBlockBar({
  block,
  hourColWidth,
  rowHeight,
  onTap,
  color,
  canEdit,
  borderRadius = 6,
}: Props) {
  const { t } = useTranslation();
  const resize = useResizeScheduleOrder();

  // Animated x-offset applied while dragging — reset to 0 on release after
  // the mutation fires (the data refetch updates startHour for real).
  const dragX = useSharedValue(0);
  const lifted = useSharedValue(0);

  const editable =
    canEdit &&
    block.kind === 'work_order' &&
    block.workOrderId != null &&
    !!block.plannedStartAt &&
    !!block.plannedEndAt;

  const finishDrag = (deltaPx: number) => {
    if (!editable || !block.plannedStartAt || !block.plannedEndAt || !block.workOrderId) {
      dragX.value = withSpring(0);
      lifted.value = withTiming(0);
      return;
    }
    // Convert horizontal pixels → minutes, snap to 15-min grid.
    const minutes = Math.round((deltaPx / hourColWidth) * 60 / SNAP_MINUTES) * SNAP_MINUTES;
    if (minutes === 0) {
      dragX.value = withSpring(0);
      lifted.value = withTiming(0);
      return;
    }
    try {
      const start = parseISO(block.plannedStartAt);
      const end = parseISO(block.plannedEndAt);
      const newStart = addMinutes(start, minutes);
      const newEnd = addMinutes(end, minutes);
      // Reset visuals immediately — the spring would feel laggy while the
      // network request flies; the refetch will redraw at the new position.
      dragX.value = 0;
      lifted.value = withTiming(0);

      const submit = (force: boolean) =>
        resize.mutate(
          {
            id: block.workOrderId!,
            input: {
              planned_start_at: format(newStart, "yyyy-MM-dd'T'HH:mm:ss"),
              planned_end_at: format(newEnd, "yyyy-MM-dd'T'HH:mm:ss"),
              force_conflict: force,
            },
          },
          {
            onError: (e: Error) => {
              if (e instanceof ScheduleConflictError) {
                Alert.alert(t('Conflict'), e.message, [
                  { text: t('Cancel'), style: 'cancel' },
                  {
                    text: t('Reschedule anyway'),
                    style: 'destructive',
                    onPress: () => submit(true),
                  },
                ]);
                return;
              }
              Alert.alert(t('Could not move'), e.message);
            },
          },
        );
      submit(false);
    } catch (e) {
      dragX.value = withSpring(0);
      lifted.value = withTiming(0);
      Alert.alert(t('Drag failed'), (e as Error).message);
    }
  };

  // Long-press primes the drag (lifts the bar a bit). Pan only activates
  // after the long-press succeeds so it doesn't fight the parent
  // ScrollView's horizontal scroll.
  const longPress = Gesture.LongPress()
    .minDuration(220)
    .enabled(editable)
    .onStart(() => {
      lifted.value = withSpring(1);
    });

  const pan = Gesture.Pan()
    .enabled(editable)
    .activeOffsetX([-6, 6])
    .onUpdate((e) => {
      dragX.value = e.translationX;
    })
    .onEnd((e) => {
      runOnJS(finishDrag)(e.translationX);
    });

  const tap = Gesture.Tap()
    .maxDuration(200)
    .onEnd(() => {
      if (onTap) runOnJS(onTap)(block);
    });

  // Tap competes with long-press; long-press wins after 220ms. Pan is
  // chained after the long-press so it requires the lift to be active.
  const composed = Gesture.Race(tap, Gesture.Simultaneous(longPress, pan));

  const animStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: dragX.value },
      { translateY: lifted.value * -2 },
      { scale: 1 + lifted.value * 0.04 },
    ],
    boxShadow: `0px 2px 6px rgba(0, 0, 0, ${lifted.value * 0.25})`,
    elevation: lifted.value * 4,
    zIndex: lifted.value > 0 ? 10 : 1,
  }));

  return (
    <GestureDetector gesture={composed}>
      <Animated.View
        style={[
          {
            position: 'absolute',
            top: 3,
            bottom: 3,
            left: block.startHour * hourColWidth,
            width: Math.max(20, block.durationHours * hourColWidth),
            backgroundColor: color.bg,
            borderRadius,
            paddingHorizontal: 6,
            paddingVertical: 3,
            overflow: 'hidden',
            justifyContent: 'center',
          },
          animStyle,
        ]}>
        <Mono size={10.5} color={color.fg} weight="700" numberOfLines={1}>
          {block.title}
        </Mono>
      </Animated.View>
    </GestureDetector>
  );
}

// Suppress unused-rowHeight warning while we don't snap vertically.
void (null as unknown as Pick<Props, 'rowHeight'>);
