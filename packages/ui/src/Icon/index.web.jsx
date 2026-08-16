import * as lucide from 'lucide-react';

/**
 * Icon — one name, both platforms (design ref: OpenMES Components.dc.html §01).
 *
 * Wraps Lucide so call sites name an icon instead of pasting SVG path data. The
 * app previously hand-inlined `<svg>` in ~70 files with `d` strings copied out of
 * the legacy Blade tables, which meant no shared vocabulary between web and
 * native and no way to restyle a glyph in one place.
 *
 * The native twin (index.native.tsx) imports the same names from
 * `lucide-react-native`, so `<Icon name="trash-2" />` renders on both.
 *
 *   name   — kebab-case Lucide name ('trash-2', 'pencil', 'circle-check')
 *   size   — px, default 20 (the size the table action rail uses)
 *   stroke — stroke width, default 2 (matches the icons this replaces)
 *
 * Colour comes from `currentColor`, so the parent's text colour drives it and
 * dark mode needs no per-icon branching. Unknown names render nothing rather
 * than throwing — a missing glyph must never blank a page.
 */

/** 'trash-2' → 'Trash2', matching Lucide's exported component names. */
function componentFor(name) {
    const pascal = String(name)
        .split(/[-_]/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');

    return lucide[pascal] ?? null;
}

export function Icon({ name, size = 20, stroke = 2, className = '', ...props }) {
    const Glyph = componentFor(name);

    if (!Glyph) {
        if (import.meta.env?.DEV) {
            console.warn(`[Icon] unknown icon "${name}" — see https://lucide.dev/icons`);
        }

        return null;
    }

    return (
        <Glyph
            size={size}
            strokeWidth={stroke}
            className={className}
            // Decorative by default: an icon-only control still needs its own
            // aria-label (see Tooltip's docblock), and a labelled one must not
            // have the glyph read out twice.
            aria-hidden={props['aria-label'] ? undefined : true}
            {...props}
        />
    );
}

export default Icon;
