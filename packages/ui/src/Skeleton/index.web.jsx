/**
 * Skeleton — Geist White system (design ref: OpenMES Components.dc.html, the
 * faint placeholder blocks used throughout the native specimens).
 *
 * A single pulsing placeholder block on the `om-pulse` keyframe (the same
 * animation as the live RUNNING dot). Pass `width`/`height` (number = px, or any
 * CSS length), `radius`, or `circle` for an avatar/dot placeholder. Compose
 * several to skeleton a card or row. API matches the native twin.
 */
export function Skeleton({
    width,
    height = 12,
    radius = 6,
    circle = false,
    className = '',
    style = {},
    ...props
}) {
    const resolvedWidth = circle ? height : (width ?? '100%');
    return (
        <span
            aria-hidden
            className={`block animate-om-pulse bg-om-line2 ${className}`}
            style={{
                width: resolvedWidth,
                height,
                borderRadius: circle ? 9999 : radius,
                ...style,
            }}
            {...props}
        />
    );
}
