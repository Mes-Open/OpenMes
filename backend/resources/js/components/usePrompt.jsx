import { useCallback, useEffect, useRef, useState } from 'react';
import { Button, Modal, TextField } from '@openmes/ui';
import { __ } from '../lib/i18n';

/**
 * Replacement for the browser's `prompt()` — the design's form modal (§09) with
 * a real labelled field (§04), instead of an OS dialog that renders in the
 * browser's language, ignores the design system and blocks the tab.
 *
 * Usage:
 *
 *   const { prompt, dialog } = usePrompt();
 *   …
 *   onClick={() => prompt(
 *       { title: __('Complete order'), label: __('Produced quantity'),
 *         defaultValue: row.planned_qty, type: 'number', min: 0 },
 *       (qty) => post(row.id, 'complete', { produced_qty: qty }),
 *   )}
 *   …
 *   return <>{page}{dialog}</>;
 *
 * The callback receives the entered string and only runs on submit — cancelling
 * or closing does nothing. `required` (default true) blocks submitting an empty
 * value; pass `required: false` for optional notes, which hands back ''.
 */
export default function usePrompt() {
    const [pending, setPending] = useState(null);
    const [value, setValue] = useState('');
    const inputRef = useRef(null);

    const prompt = useCallback((opts, onSubmit) => {
        setValue(opts.defaultValue == null ? '' : String(opts.defaultValue));
        setPending({ opts, onSubmit });
    }, []);

    const close = useCallback(() => setPending(null), []);

    // Focus (and select) the field so it behaves like the native prompt: type
    // straight away, or overwrite the prefilled default in one keystroke.
    useEffect(() => {
        if (!pending) return;
        const id = setTimeout(() => {
            inputRef.current?.focus();
            inputRef.current?.select?.();
        }, 0);
        return () => clearTimeout(id);
    }, [pending]);

    const opts = pending?.opts ?? {};
    const required = opts.required ?? true;
    const trimmed = value.trim();
    const invalid = required && trimmed === '';

    const submit = () => {
        if (invalid) return;
        const run = pending?.onSubmit;
        close();
        run?.(trimmed);
    };

    const dialog = (
        <Modal
            open={!!pending}
            onClose={close}
            title={opts.title ?? ''}
            subtitle={opts.subtitle}
            closeLabel={__('Close')}
            footer={
                <>
                    <Button variant="secondary" onClick={close}>
                        {opts.cancelLabel ?? __('Cancel')}
                    </Button>
                    <Button variant="primary" onClick={submit} disabled={invalid}>
                        {opts.confirmLabel ?? __('Confirm')}
                    </Button>
                </>
            }
        >
            <TextField
                ref={inputRef}
                label={opts.label}
                value={value}
                onChange={setValue}
                placeholder={opts.placeholder}
                type={opts.type}
                min={opts.min}
                step={opts.step}
                mono={opts.type === 'number'}
                hint={opts.hint}
                required={required}
                // Enter submits, matching the native prompt.
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && !opts.multiline) {
                        e.preventDefault();
                        submit();
                    }
                }}
                multiline={opts.multiline}
            />
        </Modal>
    );

    return { prompt, dialog };
}
