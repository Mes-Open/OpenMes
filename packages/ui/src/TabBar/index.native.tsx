/**
 * Tab bar options — Geist White system (design ref: OpenMES Components.dc.html §10④).
 * Native-only (no web twin). Not a component: a helper returning style objects to
 * spread into expo-router / React Navigation tab `screenOptions`:
 *
 *   <Tabs screenOptions={{ ...omTabBarOptions() }} />
 */
import { type TextStyle, type ViewStyle } from 'react-native';

import { colors, fonts } from '../tokens';

export interface OmTabBarOptions {
    tabBarStyle: ViewStyle;
    tabBarActiveTintColor: string;
    tabBarInactiveTintColor: string;
    tabBarLabelStyle: TextStyle;
}

/** 64px card-bg bar with a top line2 hairline; ink active tint, faint inactive. */
export function omTabBarOptions(): OmTabBarOptions {
    return {
        tabBarStyle: {
            height: 64,
            backgroundColor: colors.card,
            borderTopWidth: 1,
            borderTopColor: colors.line2,
        },
        tabBarActiveTintColor: colors.ink,
        tabBarInactiveTintColor: colors.faint,
        tabBarLabelStyle: {
            fontSize: 9.5,
            fontFamily: fonts.sans.native.semibold,
        },
    };
}
