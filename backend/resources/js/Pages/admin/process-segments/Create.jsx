import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ProcessSegmentForm from './Form';

export default function ProcessSegmentCreate() {
    const { workstationTypes = [], skills = [], segmentTypes = [] } = usePage().props;
    const form = useForm({
        code: '',
        name: '',
        description: '',
        segment_type: 'production',
        workstation_type_id: '',
        estimated_duration_minutes: '',
        required_operators: 1,
        standard_instruction: '',
        required_skill_ids: [],
        parameters_raw: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/process-segments');
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Process Segment" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Process Segment</h1>
            <ProcessSegmentForm
                form={form}
                workstationTypes={workstationTypes}
                skills={skills}
                segmentTypes={segmentTypes}
                submitLabel="Create"
                onSubmit={submit}
            />
        </div>
    );
}

ProcessSegmentCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
