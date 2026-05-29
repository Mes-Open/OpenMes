import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { lotSequenceFields } from './fields';

export default function LotSequenceCreate() {
    const { productTypes = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New LOT Sequence" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New LOT Sequence</h1>
            <ResourceForm
                action="/admin/lot-sequences"
                method="post"
                fields={lotSequenceFields(productTypes)}
                initial={{ name: '', product_type_id: '', prefix: '', suffix: '', pad_size: 4, year_prefix: false }}
                submitLabel="Create"
                cancelHref="/admin/lot-sequences"
            />
        </div>
    );
}

LotSequenceCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
