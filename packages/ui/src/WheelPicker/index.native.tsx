/**
 * WheelPicker — Geist White system (design ref: OpenMES Components.dc.html §10⑥).
 * Native-only (no web twin): 84px snap-scrolling wheel — selected row between
 * center hairline guides, faded neighbors above/below.
 */
import React from 'react';
import {
    ScrollView,
    StyleSheet,
    Text,
    View,
    type AccessibilityActionEvent,
    type NativeScrollEvent,
    type NativeSyntheticEvent,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts } from '../tokens';

const CONTAINER_HEIGHT = 84;
const ITEM_HEIGHT = 22;
const PAD = (CONTAINER_HEIGHT - ITEM_HEIGHT) / 2; // 31 — matches the design's guide offset

export interface WheelPickerOption {
    value: string | number;
    label: string;
}

export interface WheelPickerProps {
    options: WheelPickerOption[];
    value: string | number;
    onChange: (value: string | number) => void;
    style?: StyleProp<ViewStyle>;
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

export function WheelPicker({ options, value, onChange, style }: WheelPickerProps) {
    const selectedIndex = Math.max(
        0,
        options.findIndex((option) => option.value === value),
    );
    const scrollRef = React.useRef<ScrollView>(null);
    const centerRef = React.useRef(selectedIndex);
    const [centerIndex, setCenterIndex] = React.useState(selectedIndex);

    const indexFromOffset = (offsetY: number) =>
        clamp(Math.round(offsetY / ITEM_HEIGHT), 0, options.length - 1);

    const handleScroll = (event: NativeSyntheticEvent<NativeScrollEvent>) => {
        const index = indexFromOffset(event.nativeEvent.contentOffset.y);
        if (index !== centerRef.current) {
            centerRef.current = index;
            setCenterIndex(index);
        }
    };

    const handleMomentumEnd = (event: NativeSyntheticEvent<NativeScrollEvent>) => {
        const option = options[indexFromOffset(event.nativeEvent.contentOffset.y)];
        if (option && option.value !== value) onChange(option.value);
    };

    // Keep the wheel in sync when `value` changes from outside.
    React.useEffect(() => {
        if (selectedIndex !== centerRef.current) {
            scrollRef.current?.scrollTo({ y: selectedIndex * ITEM_HEIGHT, animated: true });
        }
    }, [selectedIndex]);

    const handleAccessibilityAction = (event: AccessibilityActionEvent) => {
        const direction = event.nativeEvent.actionName === 'increment' ? 1 : -1;
        const option = options[clamp(centerRef.current + direction, 0, options.length - 1)];
        if (option && option.value !== value) onChange(option.value);
    };

    return (
        <View
            accessible
            accessibilityRole="adjustable"
            accessibilityValue={{ text: options[centerIndex]?.label }}
            accessibilityActions={[{ name: 'increment' }, { name: 'decrement' }]}
            onAccessibilityAction={handleAccessibilityAction}
            style={[styles.container, style]}
        >
            <ScrollView
                ref={scrollRef}
                showsVerticalScrollIndicator={false}
                snapToInterval={ITEM_HEIGHT}
                decelerationRate="fast"
                scrollEventThrottle={16}
                onScroll={handleScroll}
                onMomentumScrollEnd={handleMomentumEnd}
                onLayout={() => {
                    scrollRef.current?.scrollTo({ y: selectedIndex * ITEM_HEIGHT, animated: false });
                }}
                contentContainerStyle={styles.content}
            >
                {options.map((option, index) => (
                    <View key={String(option.value)} style={styles.item}>
                        <Text style={index === centerIndex ? styles.selectedLabel : styles.neighborLabel}>
                            {option.label}
                        </Text>
                    </View>
                ))}
            </ScrollView>
            <View pointerEvents="none" style={styles.guides} />
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        height: CONTAINER_HEIGHT,
        backgroundColor: colors.card,
        borderWidth: 1,
        borderColor: colors.line2,
        borderRadius: 11,
        overflow: 'hidden',
    },
    content: {
        paddingVertical: PAD,
    },
    item: {
        height: ITEM_HEIGHT,
        alignItems: 'center',
        justifyContent: 'center',
    },
    selectedLabel: {
        fontSize: 15,
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
    },
    neighborLabel: {
        fontSize: 12,
        fontFamily: fonts.sans.native.regular,
        color: colors.faintest,
    },
    guides: {
        position: 'absolute',
        left: 10,
        right: 10,
        top: PAD,
        height: ITEM_HEIGHT,
        borderTopWidth: 1,
        borderBottomWidth: 1,
        borderColor: colors.line,
    },
});
