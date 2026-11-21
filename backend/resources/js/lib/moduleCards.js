/**
 * The cards installed modules add to a page through a display hook, keeping
 * only the ones that can be shown as a link: a title, and an href that is a
 * path on this site or an http(s) URL. Anything else (a `javascript:` URL, a
 * protocol-relative `//host`) is dropped rather than rendered.
 *
 * @param {Record<string, Array<{title?: string, body?: string, href?: string}>>|undefined} hooks
 * @param {string} point the hook point, e.g. 'display.settings.index.cards'
 */
export function linkCards(hooks, point) {
    return (hooks?.[point] ?? []).filter(
        (card) => typeof card.title === 'string' && card.title !== ''
            && typeof card.href === 'string' && /^(\/(?!\/)|https?:\/\/)/i.test(card.href),
    );
}
