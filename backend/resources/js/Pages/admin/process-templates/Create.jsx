import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

export default function ProcessTemplatesCreate() {
    const { productType } = usePage().props;

    const form = useForm({
        name: '',
        is_active: true,
    });

    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/product-types/${productType.id}/process-templates`);
    };

    return (
        <>
            <Head title="Create Process Template" />

            <div className="max-w-2xl mx-auto">
                <div className="mb-6">
                    <a
                        href={`/admin/product-types/${productType.id}/process-templates`}
                        className="text-om-accent hover:text-om-accent flex items-center gap-2 mb-4"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Templates
                    </a>
                    <h1 className="text-3xl font-bold text-om-ink">Create Process Template</h1>
                    <p className="text-sm text-om-muted mt-1">{productType.name}</p>
                </div>

                <div className="card">
                    <form onSubmit={submit}>
                        <div className="mb-6">
                            <label htmlFor="name" className="form-label">Template Name</label>
                            <input
                                type="text"
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={`form-input w-full${errors.name ? ' border-om-blocked' : ''}`}
                                placeholder="e.g., Standard Assembly Process, Quality Inspection v2"
                                required
                                autoFocus
                            />
                            <p className="text-sm text-om-muted mt-1">Descriptive name for this manufacturing process</p>
                            {errors.name && <p className="text-om-blocked text-sm mt-1">{errors.name}</p>}
                        </div>

                        <div className="mb-6 p-4 bg-om-chip border border-om-line rounded-om-sm">
                            <p className="text-sm text-om-accent">
                                <strong>Note:</strong> Version number will be assigned automatically. After creating the
                                template, you'll be able to add production steps.
                            </p>
                        </div>

                        <div className="mb-6">
                            <label className="flex items-center">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="h-5 w-5 text-om-accent rounded border-om-line focus:ring-om-accent"
                                />
                                <span className="ml-2 text-sm text-om-muted">
                                    Active (template is ready for use in work orders)
                                </span>
                            </label>
                        </div>

                        <div className="flex justify-end gap-3">
                            <a
                                href={`/admin/product-types/${productType.id}/process-templates`}
                                className="btn-touch btn-secondary"
                            >
                                Cancel
                            </a>
                            <button type="submit" disabled={processing} className="btn-touch btn-primary">
                                {processing ? 'Creating…' : 'Create Template'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

ProcessTemplatesCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
