import * as lucide from 'lucide-react-native';

/**
 * Icon (native twin) — same API as index.web.jsx, backed by
 * `lucide-react-native` (which renders through react-native-svg).
 *
 * Keep the prop list identical to the web twin: a screen shared between the two
 * must be able to render `<Icon name="trash-2" />` without branching.
 *
 * `color` replaces the web's `currentColor` inheritance — React Native has no
 * cascade, so a caller passes the token it wants.
 */

export interface IconProps {
    /** kebab-case Lucide name ('trash-2', 'pencil', 'circle-check'). */
    name: string;
    size?: number;
    stroke?: number;
    color?: string;
    [key: string]: unknown;
}

/** 'trash-2' → 'Trash2', matching Lucide's exported component names. */
function componentFor(name: string) {
    const pascal = String(name)
        .split(/[-_]/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');

    return (lucide as Record<string, unknown>)[pascal] as React.ComponentType<Record<string, unknown>> | undefined;
}

export function Icon({ name, size = 20, stroke = 2, color = 'currentColor', ...props }: IconProps) {
    const Glyph = componentFor(name);

    if (!Glyph) {
        if (__DEV__) {
            console.warn(`[Icon] unknown icon "${name}" — see https://lucide.dev/icons`);
        }

        return null;
    }

    return <Glyph size={size} strokeWidth={stroke} color={color} {...props} />;
}

export default Icon;
