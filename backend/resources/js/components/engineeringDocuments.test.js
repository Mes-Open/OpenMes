import { describe, expect, it } from 'vitest';
import {
    availableActions,
    formatBytes,
    isImmutable,
    isInteractive,
    lifecycleMeta,
    packageMeta,
} from './engineeringDocuments';

describe('packageMeta / lifecycleMeta', () => {
    it('resolves known package types', () => {
        expect(packageMeta('interactive_html').interactive).toBe(true);
        expect(packageMeta('pdf').inline).toBe(true);
        expect(packageMeta('neutral_cad').interactive).toBe(false);
        expect(packageMeta('neutral_cad').inline).toBe(false);
    });

    it('falls back gracefully for unknown types', () => {
        const meta = packageMeta('mystery');
        expect(meta.label).toBe('mystery');
        expect(meta.interactive).toBe(false);
        expect(lifecycleMeta(undefined).label).toBe('—');
    });

    it('mirrors lifecycle badges', () => {
        expect(lifecycleMeta('released').badge).toContain('green');
        expect(lifecycleMeta('draft').badge).toContain('gray');
        expect(lifecycleMeta('obsolete').badge).toContain('amber');
    });
});

describe('isInteractive / isImmutable', () => {
    it('only interactive_html is interactive', () => {
        expect(isInteractive('interactive_html')).toBe(true);
        expect(isInteractive('pdf')).toBe(false);
    });

    it('only released is immutable', () => {
        expect(isImmutable('released')).toBe(true);
        expect(isImmutable('draft')).toBe(false);
        expect(isImmutable('obsolete')).toBe(false);
    });
});

describe('formatBytes', () => {
    it('handles empty / invalid input', () => {
        expect(formatBytes(0)).toBe('—');
        expect(formatBytes(null)).toBe('—');
        expect(formatBytes('nope')).toBe('—');
    });

    it('scales units', () => {
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(1024)).toBe('1 KB');
        expect(formatBytes(1536)).toBe('1.5 KB');
        expect(formatBytes(5 * 1024 * 1024)).toBe('5 MB');
    });
});

describe('availableActions', () => {
    it('offers nothing without manage permission', () => {
        expect(availableActions('draft', false)).toEqual({ canRelease: false, canObsolete: false, canDelete: false });
    });

    it('draft can be released, obsoleted or deleted', () => {
        expect(availableActions('draft', true)).toEqual({ canRelease: true, canObsolete: true, canDelete: true });
    });

    it('released can only be obsoleted (immutable, kept for traceability)', () => {
        expect(availableActions('released', true)).toEqual({ canRelease: false, canObsolete: true, canDelete: false });
    });

    it('obsolete is terminal', () => {
        expect(availableActions('obsolete', true)).toEqual({ canRelease: false, canObsolete: false, canDelete: false });
    });
});
