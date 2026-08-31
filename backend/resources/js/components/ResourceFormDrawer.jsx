import { useCallback, useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Modal, Skeleton } from '@openmes/ui';
import ResourceForm from './ResourceForm';
import { customFieldValues } from '../lib/customFieldForm';
import { __ } from '../lib/i18n';

/**
 * Create and edit from the list page, in a right-edge drawer.
 *
 * The list keeps its search, filters, paging and scroll while you fill the
 * form, and the saved row live-syncs back into the table underneath — no
 * navigation, so nothing about the page you were looking at is lost. The
 * standalone `/create` and `/{id}/edit` routes stay exactly as they are; this is
 * a second door onto the same controller actions, not a replacement for them.
 *
 * Three pieces have to line up:
 *   - the page pairs `useResourceDrawer()` with `ResourceTable`'s `onCreate`
 *     and an `onClick` Edit row action,
 *   - the controller uses the `StaysOnList` concern, so the `stay` flag posted
 *     from here is answered with `back()` instead of a redirect,
 *   - `index()` serves the form's option lists (see `ensure` below).
 *
 * Editing reads the record straight out of the synced row the table already
 * holds, so the drawer opens filled in with no round-trip. Where a form needs
 * something the collection doesn't carry — a pivot like `line_ids`, or a secret
 * that must never be broadcast — the page fetches that itself and merges it in
 * through `initial`.
 *
 * One thing the synced row does not give you for free: JSON columns arrive as
 * strings, because the collection snapshot is a `DB::table()` query with no
 * Eloquent casts behind it. `custom_fields` is handled here for every resource;
 * a form binding any *other* json column has to parse it in its own `initial`.
 */
export default function ResourceFormDrawer({
    /** From `useResourceDrawer()` — spread it. */
    open,
    mode,
    record,
    onClose,
    /** Base endpoint: create posts here, edit puts to `${action}/${record.id}`. */
    action,
    /**
     * Field descriptors, same array the standalone Create/Edit pages pass — or a
     * `(mode) => descriptors` function where the two differ, as they do wherever
     * a field is write-once (a webhook's secret is generated on create and left
     * blank on edit, because the stored one is never sent to the browser).
     */
    fields,
    /** (record | null) => form values. Shared with Create.jsx and Edit.jsx. */
    initial,
    customFields,
    /** { create, edit } — both already translated. */
    title,
    subtitle,
    submitLabel,
    width = 520,
    /**
     * Inertia props the form needs that `index()` declares `Inertia::optional()`
     * — option lists, custom-field config. They cost a query each and most
     * visitors never open the drawer, so they're fetched on first open rather
     * than with every list render. Pair with `ready`.
     */
    ensure,
    /**
     * Escape hatch for resources whose form cannot be expressed as a `fields`
     * config (a pattern builder, a skills matrix, dependent dropdowns): a
     * `({ editing, record, finish }) => JSX` render prop that mounts the page's
     * own form inside the drawer chrome. The form is expected to post with
     * `stay: 1` in its values and call `finish` when done; keying and
     * ensure/ready behave exactly as they do for the config-driven path.
     */
    render,
    /**
     * Whether the props named in `ensure` have arrived. Until they have, the
     * drawer opens onto a skeleton rather than a form with empty dropdowns —
     * an empty <select> and a still-loading one look identical, and one of them
     * silently submits the wrong thing.
     */
    ready = true,
}) {
    const editing = mode === 'edit';

    // Bumped when the form is done with — see the key below.
    const [run, setRun] = useState(0);
    const finish = useCallback(() => {
        setRun((n) => n + 1);
        onClose?.();
    }, [onClose]);

    /**
     * Fetch the optional props once per opening — not once per page.
     *
     * Saving takes the `stay` path, and `back()` is a full visit, not a partial
     * one: Inertia's `mergeProps` bails out for those, so `page.props` is
     * replaced wholesale by what `index()` returns, which is exactly the set
     * that leaves the `Inertia::optional` props out. `ready` therefore goes true
     * → false after every save, while the component survives (the visit
     * preserves state), so a latch that only ever armed once would leave every
     * later opening stuck on the skeleton with nothing to click. Re-arming on
     * the closed → open edge asks again, and only again, when it has to.
     */
    const asked = useRef(false);
    const wasOpen = useRef(false);
    // Once the form has rendered ready during this opening, never fall back to
    // the skeleton: a failed submit is a full visit whose props omit the
    // `Inertia::optional` lists, so `ready` regresses — swapping to the
    // skeleton then would unmount the form, destroying the typed values and
    // the validation errors the 422 just delivered.
    const [everReady, setEverReady] = useState(false);
    useEffect(() => {
        if (open && ready) setEverReady(true);
        if (!open) setEverReady(false);
    }, [open, ready]);
    // Depend on the names, not the array: call sites pass a literal, so a new
    // identity every render would re-run this on every keystroke in the form.
    const wanted = ensure?.join(',') ?? '';
    useEffect(() => {
        const opening = open && !wasOpen.current;
        wasOpen.current = open;
        if (opening) asked.current = false;
        // `ready` regressing while open (the failed-submit visit above) re-arms
        // the fetch so the option lists come back underneath the mounted form.
        if (ready) asked.current = false;
        if (!open || ready || asked.current || !wanted) return;
        asked.current = true;
        router.reload({
            only: wanted.split(','),
            // A visit that never delivers — an expired session redirecting to
            // login, a dropped connection, an interrupting navigation — would
            // otherwise leave the drawer on its skeleton for the life of the
            // page, with nothing to click. Clearing the latch lets closing and
            // reopening try again.
            onError: () => { asked.current = false; },
            onCancel: () => { asked.current = false; },
        });
    }, [open, ready, wanted]);

    return (
        <Modal
            open={open}
            onClose={onClose}
            side="right"
            width={width}
            title={editing ? title?.edit : title?.create}
            subtitle={subtitle}
            closeLabel={__('Close')}
            // A misclick on the scrim shouldn't cost a half-filled form. Safe
            // here despite the warning on the prop: the form is keyed by record
            // below, so what's retained belongs to the record it was typed for.
            keepMounted
        >
            {(ready || everReady) ? (render ? (
                <div key={`${mode}:${record?.id ?? 'new'}:${run}`}>
                    {render({ editing, record, finish })}
                </div>
            ) : (
                <ResourceForm
                    // One instance serves create and every row's edit, so the key
                    // has to change with the record — otherwise the retained state
                    // `keepMounted` exists for would carry one row's typed values
                    // onto the next one you open. `run` covers the create form,
                    // which has no id to change: it's bumped once the form is
                    // finished with, so the next "New …" opens empty rather than
                    // showing what was just submitted.
                    key={`${mode}:${record?.id ?? 'new'}:${run}`}
                    bare
                    action={editing ? `${action}/${record.id}` : action}
                    method={editing ? 'put' : 'post'}
                    fields={typeof fields === 'function' ? fields(mode) : fields}
                    initial={drawerInitial(initial, record)}
                    customFields={customFields}
                    submitLabel={editing ? (submitLabel?.edit ?? __('Save Changes')) : (submitLabel?.create ?? __('Create'))}
                    // Both of these are deliberate ends to the form, unlike the
                    // stray scrim click `keepMounted` exists for — so both reset it.
                    onCancel={finish}
                    onSuccess={finish}
                />
            )) : (
                <div className="space-y-5">
                    {[...Array(4)].map((_, i) => (
                        <div key={i} className="space-y-[7px]">
                            <Skeleton width={90} height={9} />
                            <Skeleton height={40} />
                        </div>
                    ))}
                </div>
            )}
        </Modal>
    );
}

/**
 * The record as form values, with the two corrections a drawer needs on top of
 * whatever the resource's own `initial` builder returns.
 */
function drawerInitial(initial, record) {
    const values = initial(record ?? null);
    return {
        ...values,
        // Present on the standalone pages as an object, on a synced row as a
        // JSON string — normalise before the form binds it either way.
        ...('custom_fields' in values ? { custom_fields: customFieldValues(values.custom_fields) } : {}),
        // What the controller's StaysOnList concern reads to answer with back()
        // instead of a redirect that would remount the list underneath us.
        stay: 1,
    };
}

/**
 * The open/closed half of the drawer, kept apart from the render so a page can
 * hand `create` to `ResourceTable`'s `onCreate` and `edit` to a row action
 * without threading state through either.
 */
export function useResourceDrawer() {
    const [state, setState] = useState({ open: false, mode: 'create', record: null });

    const create = useCallback(() => setState({ open: true, mode: 'create', record: null }), []);
    const edit = useCallback((record) => setState({ open: true, mode: 'edit', record }), []);
    // The mode and record are left in place while the panel slides out — clearing
    // them would blank the header and the fields mid-animation.
    const close = useCallback(() => setState((s) => ({ ...s, open: false })), []);

    return { create, edit, close, props: { ...state, onClose: close } };
}
