import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import LotSequenceForm from './LotSequenceForm';
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
                initial={{
                    name: lotSequence.name ?? '',
                    product_type_id: lotSequence.product_type_id != null ? String(lotSequence.product_type_id) : '',
                    pattern: lotSequence.pattern ?? '',
                    prefix: lotSequence.prefix ?? '',
                    suffix: lotSequence.suffix ?? '',
                    pad_size: lotSequence.pad_size ?? 4,
                    year_prefix: !!lotSequence.year_prefix,
                    reset_period: lotSequence.reset_period ?? 'none',
                }}
                submitLabel={__('Save Changes')}
            />
        </div>
    );
}

LotSequenceEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
