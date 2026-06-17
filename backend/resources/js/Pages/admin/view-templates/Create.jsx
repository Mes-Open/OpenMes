import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ViewTemplateForm from './Form';

export default function ViewTemplateCreate() {
    const form = useForm({ name: '', description: '', columns: [{ label: '', key: '', source: 'field' }] });
    const submit = (e) => { e.preventDefault(); form.post('/admin/view-templates'); };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New View Template" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New View Template</h1>
            <ViewTemplateForm form={form} submitLabel="Create" onSubmit={submit} />
        </div>
    );
}

ViewTemplateCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
