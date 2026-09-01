import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { COST_SOURCE_FIELDS, costSourceInitial } from './fields';

export default function CostSourceEdit({ costSource }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${costSource.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Cost Source</h1>
            <ResourceForm
                action={`/admin/cost-sources/${costSource.id}`}
                method="put"
                fields={COST_SOURCE_FIELDS}
                initial={costSourceInitial(costSource)}
                submitLabel="Save Changes"
                cancelHref="/admin/cost-sources"
            />
        </div>
    );
}

CostSourceEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
