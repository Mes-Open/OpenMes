import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { scrapReasonFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function ScrapReasonEdit({ scrapReason }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Scrap Reason')} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">{__('Edit Scrap Reason')}</h1>
            <ResourceForm
                action={`/admin/scrap-reasons/${scrapReason.id}`}
                method="put"
                fields={scrapReasonFields()}
                initial={{
                    code: scrapReason.code ?? '',
                    name: scrapReason.name ?? '',
                    category: scrapReason.category ?? '',
                    description: scrapReason.description ?? '',
                    sort_order: scrapReason.sort_order ?? 0,
                    is_active: !!scrapReason.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/scrap-reasons"
            />
        </div>
    );
}

ScrapReasonEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
