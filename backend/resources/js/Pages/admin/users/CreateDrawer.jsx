import { useRef } from 'react';
import { useForm } from '@inertiajs/react';
import UserForm from './UserForm';
export default function CreateUserForm({ options, finish, dismiss }) {
    const formOptions = useRef(options);
    if (options) formOptions.current = options;
    const form = useForm({
        account_type: 'user',
        name: '', username: '', email: '',
        password: '', password_confirmation: '',
        force_password_change: false,
        role: '', workstation_id: '',
        worker_code: '', worker_phone: '', worker_crew_id: '', worker_wage_group_id: '',
        skills: [],
    });

    return <UserForm form={form} {...formOptions.current} bare onCancel={dismiss} onSubmit={e => {
        e.preventDefault();
        form.post('/admin/users', { preserveScroll: true, onSuccess: finish });
    }} />;
}
