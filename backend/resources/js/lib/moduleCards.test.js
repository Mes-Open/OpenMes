import { describe, expect, it } from 'vitest';
import { linkCards } from './moduleCards';

const POINT = 'display.settings.index.cards';

describe('linkCards', () => {
    it('is empty when no module contributes', () => {
        expect(linkCards(undefined, POINT)).toEqual([]);
        expect(linkCards({}, POINT)).toEqual([]);
    });

    it('keeps cards with a title and a path or http(s) link', () => {
        const cards = [
            { title: 'Integrations', body: 'Keys', href: '/settings/integrations' },
            { title: 'Docs', href: 'https://example.com/docs' },
        ];
        expect(linkCards({ [POINT]: cards }, POINT)).toEqual(cards);
    });

    it('drops cards that cannot be shown as a safe link', () => {
        const cards = [
            { title: 'Script', href: 'javascript:alert(1)' },
            { title: 'Other host', href: '//evil.example/x' },
            { title: 'No link' },
            { href: '/settings/untitled' },
        ];
        expect(linkCards({ [POINT]: cards }, POINT)).toEqual([]);
    });

    it('reads only the point it is asked for', () => {
        expect(linkCards({ 'display.other': [{ title: 'X', href: '/x' }] }, POINT)).toEqual([]);
    });
});
