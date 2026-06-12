import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { scrapReasonFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function ScrapReasonCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Scrap Reason')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Scrap Reason')}</h1>
            <ResourceForm
                action="/admin/scrap-reasons"
                method="post"
                fields={scrapReasonFields()}
                initial={{ code: '', name: '', category: '', description: '', sort_order: 0, is_active: true }}
                submitLabel={__('Create')}
                cancelHref="/admin/scrap-reasons"
            />
        </div>
    );
}

ScrapReasonCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
