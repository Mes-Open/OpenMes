import { useCallback, useState } from 'react';
import { ConfirmDialog } from '@openmes/ui';
import { __ } from '../lib/i18n';

/**
 * Replacement for the browser's `confirm()` — the design's confirm modal
 * (§09 Overlays) instead of an OS dialog that ignores the app's language and
 * design language, and that blocks the whole tab while it is open.
 *
 * Usage:
 *
 *   const { confirm, dialog } = useConfirm();
 *   …
 *   onClick={() => confirm(
 *       { title: __('Delete :name?', { name }), body: __('Cannot be undone.') },
 *       () => router.delete(url),
 *   )}
 *   …
 *   return <>{page}{dialog}</>;
 *
 * `confirm(opts, onConfirm)` takes a plain string as shorthand for `{ title }`.
 * One dialog is rendered per hook instance and reused, so a list with 50 rows
 * doesn't mount 50 dialogs.
 */
export default function useConfirm() {
    const [pending, setPending] = useState(null);

    const confirm = useCallback((opts, onConfirm) => {
        setPending({ opts: typeof opts === 'string' ? { title: opts } : opts, onConfirm });
    }, []);

    const close = useCallback(() => setPending(null), []);

    const dialog = (
        <ConfirmDialog
            open={!!pending}
            onClose={close}
            onConfirm={() => {
                // Close first: the action usually navigates or posts, and a dialog
                // still mounted over the response is jarring.
                const run = pending?.onConfirm;
                close();
                run?.();
            }}
            title={pending?.opts.title ?? ''}
            confirmLabel={pending?.opts.confirmLabel ?? __('Confirm')}
            cancelLabel={pending?.opts.cancelLabel ?? __('Cancel')}
            destructive={pending?.opts.destructive ?? true}
        >
            {pending?.opts.body}
        </ConfirmDialog>
    );

    return { confirm, dialog };
}
