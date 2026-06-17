import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { ISSUE_TYPE_FIELDS } from './fields';

export default function IssueTypeCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Issue Type" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Issue Type</h1>
            <ResourceForm
                action="/admin/issue-types"
                method="post"
                fields={ISSUE_TYPE_FIELDS}
                initial={{ code: '', name: '', severity: 'MEDIUM', is_blocking: false, is_active: true }}
                submitLabel="Create"
                cancelHref="/admin/issue-types"
            />
        </div>
    );
}

IssueTypeCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
