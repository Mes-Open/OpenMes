import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import PalletForm, { palletInitial } from './PalletForm';
import LabelPrintMenu from '../../../components/LabelPrintMenu';
import { __ } from '../../../lib/i18n';

export default function PalletEdit() {
    const { pallet, labelTemplates = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`${__('Edit')} ${pallet.pallet_no}`} />
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-3xl font-bold text-om-ink">
                    {__('Edit Pallet')} <span className="font-mono text-2xl text-om-muted">{pallet.pallet_no}</span>
                </h1>
                <LabelPrintMenu kind="pallet" id={pallet.id} templates={labelTemplates} label={__('Print label')} />
            </div>
            <PalletForm
                action={`/admin/pallets/${pallet.id}`}
                method="put"
                initial={palletInitial(pallet)}
                submitLabel={__('Save Changes')}
            />
        </div>
    );
}

PalletEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
