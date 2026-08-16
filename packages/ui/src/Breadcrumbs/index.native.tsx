import React, { Fragment } from 'react';
import { Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { Icon } from '../Icon';
import { colors, fonts } from '../tokens';

/**
 * Breadcrumbs (native twin) — same trail, same item shape as index.web.jsx.
 *
 * React Native has no hrefs, so a navigable entry carries `onPress` instead;
 * the web twin's `href`/`linkAs` pair has no meaning here. The last entry is the
 * current page and is never pressable, matching the web behaviour.
 */
export interface BreadcrumbItem {
    label: string;
    /** Lucide icon name rendered before the label. */
    icon?: string;
    /** Navigate to this entry. Ignored on the last item (the current page). */
    onPress?: () => void;
    key?: string;
}

export interface BreadcrumbsProps {
    items?: BreadcrumbItem[];
    style?: StyleProp<ViewStyle>;
}

export function Breadcrumbs({ items = [], style }: BreadcrumbsProps) {
    const trail = items.filter(Boolean);

    if (trail.length === 0) {
        return null;
    }

    return (
        <View accessibilityRole="header" style={[styles.row, style]}>
            {trail.map((item, i) => {
                const isLast = i === trail.length - 1;

                return (
                    <Fragment key={item.key ?? `${item.label}-${i}`}>
                        {i > 0 && (
                            <Text style={styles.separator} accessibilityElementsHidden>
                                /
                            </Text>
                        )}
                        {item.onPress && !isLast ? (
                            <Pressable accessibilityRole="button" onPress={item.onPress} style={styles.entry}>
                                {item.icon && <Icon name={item.icon} size={14} color={colors.muted} />}
                                <Text style={styles.link}>{item.label}</Text>
                            </Pressable>
                        ) : (
                            <View style={styles.entry}>
                                {item.icon && (
                                    <Icon name={item.icon} size={14} color={isLast ? colors.ink : colors.muted} />
                                )}
                                <Text numberOfLines={1} style={isLast ? styles.current : styles.link}>
                                    {item.label}
                                </Text>
                            </View>
                        )}
                    </Fragment>
                );
            })}
        </View>
    );
}

const styles = StyleSheet.create({
    entry: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 6,
        flexShrink: 1,
    },
    row: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 8,
        flexShrink: 1,
    },
    separator: {
        color: colors.faintest,
        fontSize: 13,
    },
    link: {
        color: colors.muted,
        fontSize: 13,
        fontFamily: fonts.sans.native.regular,
    },
    current: {
        color: colors.ink,
        fontSize: 13,
        fontFamily: fonts.sans.native.semibold,
        flexShrink: 1,
    },
});

export default Breadcrumbs;
