import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ConfirmDialog } from '@openmes/ui';
import { Button, TextField } from '@openmes/ui';
import { apiCall, apiGet } from '../lib/http';
import EngineeringViewerModal from './EngineeringViewerModal';
import { __, formatDateTime } from '../lib/i18n';
import {
    availableActions,
    formatBytes,
    isInteractive,
    lifecycleMeta,
    packageMeta,
} from './engineeringDocuments';

const BASE = '/api/v1/engineering-documents';

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

// Multipart POST (upload) — apiCall JSON-stringifies, so we send FormData here
// and let the browser set the multipart boundary; CSRF header is still required.
async function postForm(url, formData) {
    return fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
    });
}

/**
 * Engineering documents (#179) panel — embedded on entity Show pages (material,
 * product type, …). Lists the CAD documents attached to one entity, and — for
 * users with `manage engineering documents` — uploads new ones and drives their
 * Draft → Released → Obsolete lifecycle. Interactive-HTML packages open in a
 * sandboxed <iframe> fed by a short-lived signed URL (never inline in the app).
 */
/**
 * `variant="band"` is the compact design-1b look (title · count · "+ Attach",
 * documents as chip cards); the default keeps the full card + table used on
 * detail pages.
 */
export default function EngineeringDocuments({ entityType, entityId, defaultRevision = '', variant = 'card' }) {
    const band = variant === 'band';
    const [uploadOpen, setUploadOpen] = useState(false);
    const [docs, setDocs] = useState([]);
    const [canManage, setCanManage] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Upload form
    const fileRef = useRef(null);
    const [file, setFile] = useState(null);
    const [revision, setRevision] = useState(defaultRevision);
    const [documentType, setDocumentType] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});
    const [uploading, setUploading] = useState(false);

    // Viewer + delete dialogs
    const [viewer, setViewer] = useState(null); // { url, title }
    const [toDelete, setToDelete] = useState(null);

    const query = useMemo(
        () => `?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}`,
        [entityType, entityId],
    );

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await apiGet(`${BASE}${query}`);
            if (!res.ok) throw new Error('load');
            const json = await res.json();
            setDocs(json.data?.data ?? []);
            setCanManage(Boolean(json.can_manage));
        } catch {
            setError(__('Could not load engineering documents.'));
        } finally {
            setLoading(false);
        }
    }, [query]);

    useEffect(() => {
        load();
    }, [load]);

    async function submitUpload(e) {
        e.preventDefault();
        if (!file || uploading) return;
        setUploading(true);
        setFieldErrors({});
        const fd = new FormData();
        fd.append('file', file);
        fd.append('entity_type', entityType);
        fd.append('entity_id', entityId);
        fd.append('revision', revision);
        if (documentType) fd.append('document_type', documentType);
        // The server derives the interactive entry point (zip extraction /
        // single-html), so the panel never sends one.
        try {
            const res = await postForm(BASE, fd);
            if (res.status === 422) {
                const body = await res.json();
                setFieldErrors(body.errors ?? {});
                return;
            }
            if (!res.ok) throw new Error('upload');
            // Reset and refresh.
            setFile(null);
            setDocumentType('');
            if (fileRef.current) fileRef.current.value = '';
            await load();
        } catch {
            setFieldErrors({ file: [__('Upload failed. Please try again.')] });
        } finally {
            setUploading(false);
        }
    }

    async function lifecycle(doc, action) {
        setError(null);
        try {
            const res = await apiCall(`${BASE}/${doc.id}/${action}`, 'POST');
            if (!res.ok) {
                const body = await res.json().catch(() => ({}));
                setError(body.message ?? __('The action could not be completed.'));
                return;
            }
            await load();
        } catch {
            setError(__('The action could not be completed.'));
        }
    }

    async function confirmDelete() {
        if (!toDelete) return;
        setError(null);
        try {
            const res = await apiCall(`${BASE}/${toDelete.id}`, 'DELETE');
            setToDelete(null);
            if (!res.ok) {
                const body = await res.json().catch(() => ({}));
                setError(body.message ?? __('The document could not be deleted.'));
                return;
            }
            await load();
        } catch {
            setToDelete(null);
            setError(__('The document could not be deleted.'));
        }
    }

    async function openViewer(doc) {
        setError(null);
        try {
            const res = await apiGet(`${BASE}/${doc.id}/viewer-url`);
            if (!res.ok) throw new Error('viewer');
            const json = await res.json();
            if (!json.data?.url) throw new Error('viewer');
            setViewer({ url: json.data.url, title: doc.original_filename });
        } catch {
            setError(__('The interactive viewer could not be opened.'));
        }
    }

    return (
        <section className={band ? '' : 'card mt-6'}>
            <div className={band ? 'flex items-baseline gap-2 mb-2.5' : 'flex items-center justify-between mb-4'}>
                <h2 className={band ? 'text-sm font-bold text-om-ink' : 'text-lg font-semibold text-om-ink'}>{__('Engineering documents')}</h2>
                <span className={band ? 'font-mono text-[10px] text-om-faint' : 'text-sm text-om-muted'}>{docs.length}</span>
                {band && canManage && (
                    <button type="button" onClick={() => setUploadOpen((o) => !o)} className="ml-auto text-[11.5px] font-semibold text-om-accent hover:underline">
                        + {__('Attach')}
                    </button>
                )}
            </div>

            {error && <p className="text-sm text-om-blocked mb-3">{error}</p>}

            {canManage && (!band || uploadOpen) && (
                <form onSubmit={submitUpload} className="mb-5 rounded-om-sm border border-om-line bg-om-chip p-4">
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div className="md:col-span-2">
                            <div className="block text-sm text-om-muted mb-1">{__('File')}</div>
                            <input
                                aria-label={__('File')}
                                ref={fileRef}
                                type="file"
                                onChange={(ev) => setFile(ev.target.files?.[0] ?? null)}
                                className="block w-full text-sm"
                            />
                            {fieldErrors.file && <p className="text-xs text-om-blocked mt-1">{fieldErrors.file[0]}</p>}
                        </div>
                        <div>
                            <TextField
                                label={__('Revision')}
                                mono
                                value={revision}
                                onChange={(v) => setRevision(v)}
                                placeholder={__('e.g. A')}
                                error={fieldErrors.revision?.[0]}
                            />
                        </div>
                        <div>
                            <TextField
                                label={__('Document type')}
                                value={documentType}
                                onChange={(v) => setDocumentType(v)}
                                placeholder={__('optional')}
                            />
                        </div>
                    </div>
                    <div className="mt-3 flex items-center gap-3">
                        <Button type="submit" disabled={!file} loading={uploading}>
                            {uploading ? __('Uploading…') : __('Upload')}
                        </Button>
                        <span className="text-xs text-om-muted">
                            {__('For an interactive HTML package, upload a .zip or a single .html.')}
                        </span>
                    </div>
                </form>
            )}

            {loading ? (
                <p className="text-sm text-om-muted">{__('Loading…')}</p>
            ) : docs.length === 0 ? (
                <p className="text-sm text-om-muted">{__('No engineering documents yet.')}</p>
            ) : band ? (
                <div className="grid gap-[7px] sm:grid-cols-2">
                    {docs.map((doc) => {
                        const pkg = packageMeta(doc.package_type);
                        const acts = availableActions(doc.lifecycle_status, canManage);
                        return (
                            <div key={doc.id} className="group flex items-center gap-2.5 bg-om-card border border-om-line2 rounded-om-sm px-2.5 py-2">
                                <span className={`shrink-0 font-mono text-[8.5px] font-semibold border rounded px-1.5 py-0.5 ${pkg.badge}`}>{__(pkg.label)}</span>
                                {isInteractive(doc.package_type) && doc.entry_point ? (
                                    <button type="button" onClick={() => openViewer(doc)} className="flex-1 min-w-0 text-left text-xs text-om-ink truncate hover:text-om-accent" title={doc.original_filename}>
                                        {doc.original_filename}
                                    </button>
                                ) : (
                                    <a href={`${BASE}/${doc.id}/download`} target={pkg.inline ? '_blank' : undefined} rel="noopener noreferrer" className="flex-1 min-w-0 text-xs text-om-ink truncate hover:text-om-accent" title={doc.original_filename}>
                                        {doc.original_filename}
                                    </a>
                                )}
                                {doc.revision && <span className="shrink-0 font-mono text-[9px] text-om-faint">REV {doc.revision}</span>}
                                <span className="shrink-0 flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href={`${BASE}/${doc.id}/download`} className="text-[11px] text-om-muted hover:text-om-ink" title={__('Download')}>↓</a>
                                    {acts.canRelease && (
                                        <button type="button" onClick={() => lifecycle(doc, 'release')} className="text-[10px] text-om-running hover:underline" title={__('Release')}>{__('Release')}</button>
                                    )}
                                    {acts.canObsolete && (
                                        <button type="button" onClick={() => lifecycle(doc, 'obsolete')} className="text-[10px] text-om-muted hover:underline" title={__('Obsolete')}>{__('Obsolete')}</button>
                                    )}
                                    {acts.canDelete && (
                                        <button type="button" onClick={() => setToDelete(doc)} className="text-[11px] text-om-blocked" title={__('Delete')}>×</button>
                                    )}
                                </span>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-om-muted border-b border-om-line">
                                <th className="py-2 pr-3">{__('File')}</th>
                                <th className="py-2 pr-3">{__('Type')}</th>
                                <th className="py-2 pr-3">{__('Revision')}</th>
                                <th className="py-2 pr-3">{__('Status')}</th>
                                <th className="py-2 pr-3">{__('Size')}</th>
                                <th className="py-2 pr-3">{__('Uploaded')}</th>
                                <th className="py-2 pr-3 text-right">{__('Actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {docs.map((doc) => {
                                const pkg = packageMeta(doc.package_type);
                                const life = lifecycleMeta(doc.lifecycle_status);
                                const acts = availableActions(doc.lifecycle_status, canManage);
                                return (
                                    <tr key={doc.id} className="border-b border-om-line/60 align-top">
                                        <td className="py-2 pr-3 font-medium text-om-ink break-all">{doc.original_filename}</td>
                                        <td className="py-2 pr-3">
                                            <span className={`inline-block rounded px-2 py-0.5 text-xs ${pkg.badge}`}>{__(pkg.label)}</span>
                                        </td>
                                        <td className="py-2 pr-3 font-mono">{doc.revision || '—'}</td>
                                        <td className="py-2 pr-3">
                                            <span className={`inline-block rounded px-2 py-0.5 text-xs ${life.badge}`}>{__(life.label)}</span>
                                        </td>
                                        <td className="py-2 pr-3 whitespace-nowrap">{formatBytes(doc.file_size)}</td>
                                        <td className="py-2 pr-3 whitespace-nowrap text-om-muted">
                                            {doc.created_at ? formatDateTime(doc.created_at) : '—'}
                                        </td>
                                        <td className="py-2 pr-3">
                                            <div className="flex flex-wrap justify-end gap-2">
                                                {isInteractive(doc.package_type) && doc.entry_point && (
                                                    <button type="button" className="btn btn-sm" onClick={() => openViewer(doc)}>
                                                        {__('View')}
                                                    </button>
                                                )}
                                                <a
                                                    className="btn btn-sm"
                                                    href={`${BASE}/${doc.id}/download`}
                                                    target={pkg.inline ? '_blank' : undefined}
                                                    rel="noopener noreferrer"
                                                >
                                                    {__('Download')}
                                                </a>
                                                {acts.canRelease && (
                                                    <button type="button" className="btn btn-sm" onClick={() => lifecycle(doc, 'release')}>
                                                        {__('Release')}
                                                    </button>
                                                )}
                                                {acts.canObsolete && (
                                                    <button type="button" className="btn btn-sm" onClick={() => lifecycle(doc, 'obsolete')}>
                                                        {__('Obsolete')}
                                                    </button>
                                                )}
                                                {acts.canDelete && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm btn-danger"
                                                        onClick={() => setToDelete(doc)}
                                                    >
                                                        {__('Delete')}
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            <EngineeringViewerModal viewer={viewer} onClose={() => setViewer(null)} />

            <ConfirmDialog
                open={toDelete !== null}
                onClose={() => setToDelete(null)}
                onConfirm={confirmDelete}
                title={
                    toDelete
                        ? __('Delete engineering document ":name"?', { name: toDelete.original_filename })
                        : ''
                }
                confirmLabel={__('Delete')}
                cancelLabel={__('Cancel')}
                destructive
            />
        </section>
    );
}
