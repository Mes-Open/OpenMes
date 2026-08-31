import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import LotSequenceForm, { lotSequenceInitial } from './LotSequenceForm';
import { __ } from '../../../lib/i18n';

export default function LotSequenceEdit() {
    const { lotSequence } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: lotSequence.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit LOT Sequence')}</h1>
            <LotSequenceForm
                action={`/admin/lot-sequences/${lotSequence.id}`}
                method="put"
                initial={lotSequenceInitial(lotSequence)}
                submitLabel={__('Save Changes')}
            />
        </div>
    );
}

LotSequenceEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
