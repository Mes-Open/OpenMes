import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Button, InlineAlert } from '@openmes/ui';
import { __ } from '../../../lib/i18n';

/**
 * Admin-managed list of private networks / hosts outgoing webhooks may reach
 * (e.g. an on-prem ERP in the same LAN). One entry per line. The server is the
 * authority (UpdateWebhookAllowlistRequest); the checks here only catch the
 * obvious typos before a round trip.
 */

/** Split the textarea into non-empty entries, remembering each one's line number. */
function parseLines(text) {
    return text
        .split('\n')
        .map((raw, i) => ({ value: raw.trim(), line: i + 1 }))
        .filter((l) => l.value !== '');
}

/** UX-only pre-check; the backend rule set is authoritative. */
function clientError(value) {
    if (/\s/.test(value) || value.includes('://') || /[*?#@]/.test(value)) {
        return __('Enter a valid IP, CIDR range or hostname.');
    }
    if (/\/0+$/.test(value)) {
        return __('Allowing every address (/0) is not permitted.');
    }
    return null;
}

export default function NetworkAllowlistCard({ entries = [], canEdit = false }) {
    const [text, setText] = useState(entries.join('\n'));
    const [errors, setErrors] = useState([]); // [{ line, value, message }]
    const [processing, setProcessing] = useState(false);

    // After a save the server sends back the normalized list (10.0.0.5 → 10.0.0.5/32).
    const serialized = entries.join('\n');
    useEffect(() => setText(serialized), [serialized]);

    function submit(e) {
        e.preventDefault();
        const lines = parseLines(text);

        const local = lines
            .map((l) => ({ ...l, message: clientError(l.value) }))
            .filter((l) => l.message);
        if (local.length > 0) {
            setErrors(local);
            return;
        }

        setProcessing(true);
        router.put(
            '/admin/webhook-allowlist',
            { entries: lines.map((l) => l.value) },
            {
                preserveScroll: true,
                onSuccess: () => setErrors([]),
                onError: (errs) => {
                    // entries.N → the textarea line it came from; a list-level
                    // error (e.g. too many entries) has no line.
                    setErrors(
                        Object.entries(errs).map(([key, message]) => {
                            const m = key.match(/^entries\.(\d+)$/);
                            const l = m ? lines[Number(m[1])] : null;
                            return { line: l?.line ?? null, value: l?.value ?? null, message };
                        }),
                    );
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <section className="bg-om-card border border-om-line rounded-om p-6 mt-6" aria-labelledby="webhook-allowlist-title">
            <h2 id="webhook-allowlist-title" className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1">
                {__('Local network receivers')}
            </h2>
            <p className="text-[12.5px] text-om-muted mb-4 max-w-3xl">
                {__('Webhooks can only reach public addresses unless the receiver is allowed here — for example an ERP server in your local network. An empty list blocks every private address. Loopback, link-local and cloud-metadata addresses are always blocked. A hostname entry trusts what your DNS returns for that name.')}
            </p>

            <form onSubmit={submit}>
                <label htmlFor="webhook-allowlist" className="block text-[12.5px] font-medium text-om-ink mb-1.5">
                    {__('Allowed hosts and networks (one per line)')}
                </label>
                <textarea
                    id="webhook-allowlist"
                    name="entries"
                    rows={5}
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    readOnly={!canEdit}
                    spellCheck={false}
                    placeholder={'192.168.1.0/24\n10.0.0.5\nodoo.lan'}
                    className="w-full max-w-xl px-3 py-2 text-[13px] font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent read-only:bg-om-chip"
                />

                {errors.length > 0 && (
                    <InlineAlert severity="error" title={__('The allowlist was not saved.')} className="mt-3 max-w-xl">
                        <ul className="list-none space-y-0.5">
                            {errors.map((err, i) => (
                                <li key={i}>
                                    {err.line !== null && (
                                        <span className="font-mono">
                                            {__('Line :line', { line: err.line })}: {err.value} —{' '}
                                        </span>
                                    )}
                                    {err.message}
                                </li>
                            ))}
                        </ul>
                    </InlineAlert>
                )}

                {canEdit ? (
                    <div className="mt-3">
                        <Button type="submit" variant="accent" loading={processing}>
                            {__('Save')}
                        </Button>
                    </div>
                ) : (
                    <p className="mt-3 text-[12px] text-om-muted">{__('Only administrators can change this list.')}</p>
                )}
            </form>
        </section>
    );
}
