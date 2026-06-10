import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { skillFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function SkillCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Skill')} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">{__('New Skill')}</h1>
            <ResourceForm
                action="/admin/skills"
                method="post"
                fields={skillFields()}
                initial={{ code: '', name: '', description: '' }}
                submitLabel={__('Create')}
                cancelHref="/admin/skills"
            />
        </div>
    );
}

SkillCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
