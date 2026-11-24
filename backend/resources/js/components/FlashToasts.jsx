import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { useToast } from '@openmes/ui';
import { __ } from '../lib/i18n';

// The four kinds HandleInertiaRequests shares, in the order they stack.
const FLASH_KINDS = ['error', 'warning', 'info', 'success'];

/**
 * The default "that worked" for a write that redirects: any controller ending in
 * `->with('success'|'error'|'warning'|'info', …)` lands here and is shown as a
 * floating toast, so the page itself needs no code and nothing on it moves.
 *
 * Must sit inside the layout's `ToastProvider`. Renders nothing itself.
 *
 * Keyed on the flash object: every server response brings a new one, so saving
 * twice with the same message shows it the second time too (the provider folds
 * the repeat into the toast already on screen). A partial reload that does not
 * ask for `flash` keeps the old object, so it does not show the message again.
 */
export default function FlashToasts() {
    const { flash } = usePage().props;
    const toast = useToast();
    const shown = useRef(null);

    useEffect(() => {
        if (!flash || shown.current === flash) return;
        shown.current = flash;
        FLASH_KINDS.forEach((kind) => {
            if (flash[kind]) toast({ severity: kind, title: __(flash[kind]) });
        });
    }, [flash, toast]);

    return null;
}
