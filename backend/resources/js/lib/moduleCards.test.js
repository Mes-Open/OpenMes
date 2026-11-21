import { describe, expect, it } from 'vitest';
import { isSafeHref, linkCards } from './moduleCards';

const POINT = 'display.settings.index.cards';

describe('isSafeHref', () => {
    it.each(['/settings/integrations', '/', 'https://example.com/docs', 'http://plant.local:8000/x', 'HTTPS://EXAMPLE.COM'])(
        'allows %s',
        (href) => expect(isSafeHref(href)).toBe(true),
    );

    it.each([
        'javascript:alert(1)',
        'JavaScript:alert(1)',
        'data:text/html,<script>alert(1)</script>',
        '//evil.example/x',
        '/\\evil.example/login',
        '/\t/evil.example',
        '/\n/evil.example',
        ' /settings',
        'settings/relative',
        'https://',
        '',
        null,
        42,
    ])('refuses %j', (href) => expect(isSafeHref(href)).toBe(false));
});

describe('linkCards', () => {
    it('is empty when no module contributes', () => {
        expect(linkCards(undefined, POINT)).toEqual([]);
        expect(linkCards({}, POINT)).toEqual([]);
        expect(linkCards({ [POINT]: 'not a list' }, POINT)).toEqual([]);
    });

    it('keeps cards with a title and a safe link', () => {
        expect(linkCards({ [POINT]: [
            { title: 'Integrations', body: 'Keys', href: '/settings/integrations' },
            { title: 'Docs', href: 'https://example.com/docs', external: true },
        ] }, POINT)).toEqual([
            { title: 'Integrations', body: 'Keys', href: '/settings/integrations', external: false },
            { title: 'Docs', href: 'https://example.com/docs', external: true },
        ]);
    });

    it('drops cards without a title or a safe link', () => {
        expect(linkCards({ [POINT]: [
            { title: 'Script', href: 'javascript:alert(1)' },
            { title: 'Other host', href: '/\\evil.example' },
            { title: 'No link' },
            { href: '/settings/untitled' },
            null,
        ] }, POINT)).toEqual([]);
    });

    it('keeps a card but not a body that is not text', () => {
        expect(linkCards({ [POINT]: [{ title: 'X', href: '/x', body: ['a', 'b'] }] }, POINT))
            .toEqual([{ title: 'X', href: '/x', external: false }]);
    });

    it('reads only the point it is asked for', () => {
        expect(linkCards({ 'display.other': [{ title: 'X', href: '/x' }] }, POINT)).toEqual([]);
    });
});
