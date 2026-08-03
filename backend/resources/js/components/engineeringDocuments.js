// Pure, framework-free helpers for the engineering-documents UI (#179).
// Kept separate from the React component so the logic is unit-testable in the
// plain `node` Vitest environment (no jsdom). Mirrors the PHP enums
// App\Enums\EngineeringPackageType and EngineeringDocumentLifecycle — keep the
// keys/values in sync with them.

/** Package type → display metadata. `label` is a translation key resolved via __(). */
export const PACKAGE_META = {
    native_cad: { label: 'Native CAD', badge: 'bg-blue-100 text-blue-800', interactive: false, inline: false },
    neutral_cad: { label: 'Neutral CAD', badge: 'bg-sky-100 text-sky-800', interactive: false, inline: false },
    edrawings_native: { label: 'eDrawings', badge: 'bg-indigo-100 text-indigo-800', interactive: false, inline: false },
    interactive_html: { label: 'Interactive viewer', badge: 'bg-purple-100 text-purple-800', interactive: true, inline: false },
    pdf: { label: 'Drawing (PDF)', badge: 'bg-rose-100 text-rose-800', interactive: false, inline: true },
    image: { label: 'Image', badge: 'bg-teal-100 text-teal-800', interactive: false, inline: true },
};

/** Lifecycle → display metadata. Matches EngineeringDocumentLifecycle::badgeColor(). */
export const LIFECYCLE_META = {
    draft: { label: 'Draft', badge: 'bg-gray-200 text-gray-700' },
    released: { label: 'Released', badge: 'bg-green-100 text-green-800' },
    obsolete: { label: 'Obsolete', badge: 'bg-amber-100 text-amber-800' },
};

export function packageMeta(type) {
    return PACKAGE_META[type] ?? { label: type ?? '—', badge: 'bg-gray-100 text-gray-600', interactive: false, inline: false };
}

export function lifecycleMeta(status) {
    return LIFECYCLE_META[status] ?? { label: status ?? '—', badge: 'bg-gray-100 text-gray-600' };
}

/** Only interactive-HTML packages open in the sandboxed viewer. */
export function isInteractive(type) {
    return packageMeta(type).interactive;
}

/** A released document is immutable — no delete/re-upload, only obsolete. */
export function isImmutable(status) {
    return status === 'released';
}

/** Human-readable byte size (matches the compact style used elsewhere in the app). */
export function formatBytes(bytes) {
    const n = Number(bytes);
    if (!Number.isFinite(n) || n <= 0) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = n;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i += 1;
    }
    const rounded = value >= 10 || i === 0 ? Math.round(value) : Math.round(value * 10) / 10;
    return `${rounded} ${units[i]}`;
}

/**
 * Which lifecycle transitions are offered for a document, given the caller can
 * manage documents. Released is terminal-ish (only → obsolete); obsolete is final.
 */
export function availableActions(status, canManage) {
    if (!canManage) return { canRelease: false, canObsolete: false, canDelete: false };
    return {
        canRelease: status === 'draft',
        canObsolete: status === 'draft' || status === 'released',
        canDelete: status === 'draft', // released/obsolete kept for traceability
    };
}
