import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

export default function Profile() {
    const { auth } = usePage().props;
    const user = auth?.user ?? {};

    const { data, setData, post, processing, errors } = useForm({
        name: user.name ?? '',
        email: user.email ?? '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post('/settings/profile');
    }

    const initial = user.name ? user.name.charAt(0).toUpperCase() : '?';
    const role = user.roles?.[0] ?? 'User';

    return (
        <div className="max-w-2xl mx-auto">
            <Head title="Profile" />

            <div className="mb-6">
                <Link href="/settings" className="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back
                </Link>
                <h1 className="text-3xl font-bold text-gray-800">Profile</h1>
            </div>

            <div className="card">
                <form onSubmit={handleSubmit}>
                    {/* Avatar */}
                    <div className="mb-6 flex items-center gap-4">
                        <div className="flex-shrink-0 h-20 w-20 bg-blue-100 rounded-full flex items-center justify-center">
                            <span className="text-blue-600 font-bold text-3xl">{initial}</span>
                        </div>
                        <div>
                            <h3 className="font-semibold text-gray-800">{user.username}</h3>
                            <p className="text-sm text-gray-500">{role}</p>
                        </div>
                    </div>

                    {/* Name */}
                    <div className="mb-6">
                        <label htmlFor="name" className="form-label">Name</label>
                        <input
                            type="text"
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={`form-input w-full${errors.name ? ' border-red-500' : ''}`}
                            required
                            autoFocus
                        />
                        {errors.name && <p className="text-red-600 text-sm mt-1">{errors.name}</p>}
                    </div>

                    {/* Email */}
                    <div className="mb-6">
                        <label htmlFor="email" className="form-label">Email</label>
                        <input
                            type="email"
                            id="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className={`form-input w-full${errors.email ? ' border-red-500' : ''}`}
                            required
                        />
                        {errors.email && <p className="text-red-600 text-sm mt-1">{errors.email}</p>}
                    </div>

                    {/* Read-only account info */}
                    <div className="bg-gray-50 rounded-lg p-4 mb-6">
                        <h3 className="text-sm font-semibold text-gray-700 mb-3">Account Information</h3>
                        <div className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-600">Username:</span>
                                <span className="font-medium text-gray-800">{user.username}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-600">Role:</span>
                                <span className="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">{role}</span>
                            </div>
                        </div>
                    </div>

                    {/* Info note */}
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div className="flex gap-3">
                            <svg className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div className="text-sm text-blue-800">
                                <p className="font-semibold mb-1">Note:</p>
                                <p>To change your username or role, contact an administrator.</p>
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Link href="/settings" className="btn-touch btn-secondary">Cancel</Link>
                        <button type="submit" disabled={processing} className="btn-touch btn-primary">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

Profile.layout = (page) => <AppLayout>{page}</AppLayout>;
