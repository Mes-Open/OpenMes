import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import ResourceForm from '../../../../components/ResourceForm';
import PageTrail from '../../../../components/PageTrail';
import { changeoverRuleFields, changeoverRuleInitial, changeoverTrail } from './fields';
import { __ } from '../../../../lib/i18n';

export default function ChangeoverRuleEdit() {
    const props = usePage().props;

    return (
        <>
            <Head title={__('Edit Changeover Rule')} />
            <PageTrail append={[...changeoverTrail(), { label: __('Edit Changeover Rule') }]} />
            <ResourceForm
                title={__('Edit Changeover Rule')}
                action={`/admin/schedule/changeover-rules/${props.rule.id}`}
                method="put"
                fields={changeoverRuleFields(props)}
                initial={changeoverRuleInitial(props.rule)}
                cancelHref="/admin/schedule/changeover-rules"
            />
        </>
    );
}

ChangeoverRuleEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
