import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { issueTypeFields } from './fields';

export default function IssueTypeEdit({ issueType }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${issueType.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Issue Type</h1>
            <ResourceForm
                action={`/admin/issue-types/${issueType.id}`}
                method="put"
                fields={issueTypeFields()}
                initial={{
                    code: issueType.code ?? '',
                    name: issueType.name ?? '',
                    severity: issueType.severity ?? 'MEDIUM',
                    is_blocking: !!issueType.is_blocking,
                    is_active: !!issueType.is_active,
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/issue-types"
            />
        </div>
    );
}

IssueTypeEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
