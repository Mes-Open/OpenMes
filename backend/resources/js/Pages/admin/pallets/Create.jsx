import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import PalletForm, { palletInitial } from './PalletForm';

export default function PalletCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Pallet" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Pallet</h1>
            <PalletForm
                action="/admin/pallets"
                method="post"
                initial={palletInitial(null)}
                submitLabel="Create"
            />
        </div>
    );
}

PalletCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
