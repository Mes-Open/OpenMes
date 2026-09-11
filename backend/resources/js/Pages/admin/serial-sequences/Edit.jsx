import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import SerialSequenceForm, { serialSequenceInitial } from './SerialSequenceForm';
import { __ } from '../../../lib/i18n';

export default function SerialSequenceEdit() {
    const { serialSequence } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: serialSequence.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Serial Sequence')}</h1>
            <SerialSequenceForm
                action={`/admin/serial-sequences/${serialSequence.id}`}
                method="put"
                initial={serialSequenceInitial(serialSequence)}
                submitLabel={__('Save Changes')}
            />
        </div>
    );
}

SerialSequenceEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
