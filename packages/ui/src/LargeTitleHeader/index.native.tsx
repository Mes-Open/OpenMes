/**
 * LargeTitleHeader — Geist White system (design ref: OpenMES Components.dc.html §11②).
 * Native-only (no web twin): mono context label + avatar row above a 27px large title.
 */
import React from 'react';
import {
    Pressable,
    StyleSheet,
    Text,
    View,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts } from '../tokens';

export interface LargeTitleHeaderProps {
    title: string;
    /** Mono uppercase context label, e.g. "A-SHIFT · 06:00". */
    contextLabel?: string;
    /** Initials in the 30px ink circle avatar, e.g. "AK". */
    avatarInitials?: string;
    onAvatarPress?: () => void;
    /** Custom right node, replaces the avatar. */
    right?: React.ReactNode;
    avatarAccessibilityLabel?: string;
    style?: StyleProp<ViewStyle>;
}

export function LargeTitleHeader({
    title,
    contextLabel,
    avatarInitials,
    onAvatarPress,
    right,
    avatarAccessibilityLabel = 'Account',
    style,
}: LargeTitleHeaderProps) {
    return (
        <View style={[styles.header, style]}>
            <View style={styles.topRow}>
                {contextLabel != null ? <Text style={styles.contextLabel}>{contextLabel}</Text> : <View />}
                {right != null
                    ? right
                    : avatarInitials != null && (
                          <Pressable
                              accessibilityRole="button"
                              accessibilityLabel={avatarAccessibilityLabel}
                              onPress={onAvatarPress}
                              style={({ pressed }) => [styles.avatar, pressed && styles.pressed]}
                          >
                              <Text style={styles.initials}>{avatarInitials}</Text>
                          </Pressable>
                      )}
            </View>
            <Text accessibilityRole="header" style={styles.title}>
                {title}
            </Text>
        </View>
    );
}

const styles = StyleSheet.create({
    header: {
        paddingTop: 18,
        paddingHorizontal: 16,
    },
    topRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 4,
    },
    contextLabel: {
        fontSize: 9.5,
        fontFamily: fonts.mono.native.regular,
        letterSpacing: 0.76,
        textTransform: 'uppercase',
        color: colors.faint,
    },
    avatar: {
        width: 30,
        height: 30,
        borderRadius: 15,
        backgroundColor: colors.ink,
        alignItems: 'center',
        justifyContent: 'center',
    },
    initials: {
        fontSize: 11,
        fontFamily: fonts.sans.native.semibold,
        color: '#FFFFFF',
    },
    title: {
        fontSize: 27,
        fontFamily: fonts.sans.native.semibold,
        letterSpacing: -0.675,
        color: colors.ink,
        marginBottom: 14,
    },
    pressed: {
        opacity: 0.85,
    },
});
