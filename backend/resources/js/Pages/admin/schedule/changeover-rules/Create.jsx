import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import ResourceForm from '../../../../components/ResourceForm';
import PageTrail from '../../../../components/PageTrail';
import { changeoverRuleFields, changeoverRuleInitial, changeoverTrail } from './fields';
import { __ } from '../../../../lib/i18n';

export default function ChangeoverRuleCreate() {
    const props = usePage().props;

    return (
        <>
            <Head title={__('New Changeover Rule')} />
            <PageTrail append={[...changeoverTrail(), { label: __('New Changeover Rule') }]} />
            <ResourceForm
                title={__('New Changeover Rule')}
                action="/admin/schedule/changeover-rules"
                fields={changeoverRuleFields(props)}
                initial={changeoverRuleInitial()}
                cancelHref="/admin/schedule/changeover-rules"
            />
        </>
    );
}

ChangeoverRuleCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
