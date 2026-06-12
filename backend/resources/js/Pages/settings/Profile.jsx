// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Badge, Button, InlineAlert, TextField } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

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
    const role = user.roles?.[0] ?? __('User');

    return (
        <div className="max-w-2xl mx-auto">
            <Head title={__('Profile')} />

            <div className="mb-6">
                <Link href="/settings" className="text-[13px] text-om-muted hover:text-om-ink flex items-center gap-2 mb-4 transition-colors">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    {__('Back')}
                </Link>
                <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Profile')}</h1>
            </div>

            <div className="bg-om-card border border-om-line rounded-om p-6">
                <form onSubmit={handleSubmit}>
                    {/* Avatar */}
                    <div className="mb-6 flex items-center gap-4 border-b border-om-line pb-6">
                        <div className="flex-shrink-0 h-20 w-20 bg-om-chip rounded-full flex items-center justify-center">
                            <span className="text-om-ink font-semibold text-3xl">{initial}</span>
                        </div>
                        <div>
                            <h3 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">{user.username}</h3>
                            <p className="text-[12.5px] text-om-muted">{role}</p>
                        </div>
                    </div>

                    {/* Name */}
                    <TextField
                        className="mb-6"
                        label={__('Name')}
                        id="name"
                        value={data.name}
                        onChange={(v) => setData('name', v)}
                        error={errors.name}
                        required
                        autoFocus
                    />

                    {/* Email */}
                    <TextField
                        className="mb-6"
                        label={__('Email')}
                        type="email"
                        id="email"
                        value={data.email}
                        onChange={(v) => setData('email', v)}
                        error={errors.email}
                        required
                    />

                    {/* Read-only account info */}
                    <div className="bg-om-chip rounded-om-sm p-4 mb-6">
                        <h3 className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-3">{__('Account Information')}</h3>
                        <div className="space-y-2">
                            <div className="flex justify-between text-[12.5px]">
                                <span className="text-om-muted">{__('Username:')}</span>
                                <span className="font-medium text-om-ink">{user.username}</span>
                            </div>
                            <div className="flex justify-between items-center text-[12.5px]">
                                <span className="text-om-muted">{__('Role:')}</span>
                                <Badge variant="outline">{role}</Badge>
                            </div>
                        </div>
                    </div>

                    {/* Info note */}
                    <InlineAlert severity="info" title={__('Note:')} className="mb-6">
                        {__('To change your username or role, contact an administrator.')}
                    </InlineAlert>

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Link
                            href="/settings"
                            className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
                        >
                            {__('Cancel')}
                        </Link>
                        <Button type="submit" variant="accent" loading={processing}>
                            {__('Save')}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

Profile.layout = (page) => <AppLayout>{page}</AppLayout>;
