/**
 * Links that installed modules contribute through display hooks.
 *
 * The server already drops an unsafe href (HookRegistry::isSafeHref); this is
 * the same rule again in the browser, so a page never puts one in an <a>.
 */

/**
 * A path on this site ('/settings/x') or an http(s) URL with a host. Not
 * `javascript:` or another scheme, and nothing a browser reads as another host:
 * '//host', or '/\host' and '/<tab>/host' (a backslash counts as a slash, tabs
 * and newlines are dropped).
 */
export function isSafeHref(href) {
    if (typeof href !== 'string' || href === '' || /[\u0000- \u007f\\]/.test(href)) {
        return false;
    }

    return (href.startsWith('/') && ! href.startsWith('//')) || /^https?:\/\/[^/?#]+/i.test(href);
}

/**
 * The link cards contributed to one hook point that can be shown: a title and a
 * safe href, with `body` kept only when it is text (anything else would break
 * the whole page when React renders it).
 *
 * @param {Record<string, Array<object>>|undefined} hooks
 * @param {string} point the hook point, e.g. 'display.settings.index.cards'
 * @returns {Array<{title: string, href: string, body?: string, external: boolean}>}
 */
export function linkCards(hooks, point) {
    const cards = hooks?.[point];

    return (Array.isArray(cards) ? cards : [])
        .filter((card) => card && typeof card.title === 'string' && card.title !== '' && isSafeHref(card.href))
        .map((card) => ({
            title: card.title,
            href: card.href,
            ...(typeof card.body === 'string' && card.body !== '' ? { body: card.body } : {}),
            external: card.external === true,
        }));
}
