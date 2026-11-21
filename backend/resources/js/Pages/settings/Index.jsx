// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';
import { linkCards } from '../../lib/moduleCards';

function NavCard({ href, iconPath, title, description }) {
    return (
        <Link
            href={href}
            className="bg-om-card border border-om-line rounded-om p-6 hover:border-om-faint transition-colors cursor-pointer flex items-start gap-4"
        >
            <div className="bg-om-chip rounded-full p-3 flex-shrink-0">
                <svg className="w-6 h-6 text-om-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={iconPath} />
                </svg>
            </div>
            <div className="flex-1">
                <h3 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1">{title}</h3>
                <p className="text-om-muted text-[12.5px]">{description}</p>
            </div>
            <svg className="w-5 h-5 text-om-faint flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
            </svg>
        </Link>
    );
}

// A module's own settings pages, from display hook `display.settings.index.cards`.
// Plain card fields only (title, body, href), so a module installed from a ZIP can
// add one. A full page load (<a>, not <Link>): the page may not be an Inertia one.
const PUZZLE_ICON = 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z';

function ModuleCard({ href, title, body, external }) {
    return (
        <a
            href={href}
            {...(external ? { rel: 'noreferrer' } : {})}
            className="bg-om-card border border-om-line rounded-om p-6 hover:border-om-faint transition-colors cursor-pointer flex items-start gap-4"
        >
            <div className="bg-om-chip rounded-full p-3 flex-shrink-0">
                <svg className="w-6 h-6 text-om-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={PUZZLE_ICON} />
                </svg>
            </div>
            <div className="flex-1">
                <h3 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1">{title}</h3>
                {body && <p className="text-om-muted text-[12.5px]">{body}</p>}
            </div>
            <svg className="w-5 h-5 text-om-faint flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
            </svg>
        </a>
    );
}

export default function Index() {
    const { auth, pinLoginEnabled, hasPin, twoFactorEnabled, hooks } = usePage().props;
    const isAdmin = auth?.user?.roles?.includes('Admin');

    return (
        <div className="max-w-4xl mx-auto">
            <Head title={__('Settings')} />
            <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-6">{__('Settings')}</h1>

            {isAdmin && (
                <div className="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <NavCard
                        href="/settings/system"
                        iconPath="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        title={__('System Settings')}
                        description={__('Production period split, overproduction rules, step sequencing')}
                    />
                    <NavCard
                        href="/admin/dashboard-widgets"
                        iconPath="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"
                        title={__('Dashboard Setup')}
                        description={__('Enable, disable, and reorder dashboard widgets')}
                    />
                    <NavCard
                        href="/settings/api-tokens"
                        iconPath="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                        title={__('API Tokens')}
                        description={__('Manage tokens for external integrations')}
                    />
                    <NavCard
                        href="/admin/custom-fields"
                        iconPath="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"
                        title={__('Custom Fields')}
                        description={__('Define extra fields on records across the system')}
                    />
                    <NavCard
                        href="/admin/unit-aliases"
                        iconPath="M3 17l4-4m0 0l3 3m-3-3l3-3m0 0l3 3m-3-3l3-3m0 0l3 3m-3-3l4-4M3 17l4 4L21 7l-4-4"
                        title={__('Units of Measure')}
                        description={__('Map the unit names your ERP sends to OpenMES units')}
                    />
                    <NavCard
                        href="/settings/access"
                        iconPath="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                        title={__('Tab Access')}
                        description={__('Grant each role access to individual admin-panel tabs')}
                    />
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Link href="/settings/profile" className="bg-om-card border border-om-line rounded-om p-6 hover:border-om-faint transition-colors cursor-pointer">
                    <div className="flex items-start gap-4">
                        <div className="bg-om-chip rounded-full p-3">
                            <svg className="w-6 h-6 text-om-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div className="flex-1">
                            <h3 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1">{__('Profile')}</h3>
                            <p className="text-om-muted text-[12.5px]">{__('Update your profile and account info')}</p>
                        </div>
                        <svg className="w-5 h-5 text-om-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>

                <Link href="/settings/change-password" className="bg-om-card border border-om-line rounded-om p-6 hover:border-om-faint transition-colors cursor-pointer">
                    <div className="flex items-start gap-4">
                        <div className="bg-om-chip rounded-full p-3">
                            <svg className="w-6 h-6 text-om-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <div className="flex-1">
                            <h3 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1">{__('Change Password')}</h3>
                            <p className="text-om-muted text-[12.5px]">{__('Change your account password')}</p>
                        </div>
                        <svg className="w-5 h-5 text-om-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>

                {pinLoginEnabled && (
                    <Link href="/settings/pin" className="bg-om-card border border-om-line rounded-om p-6 hover:border-om-faint transition-colors cursor-pointer">
                        <div className="flex items-start gap-4">
                            <div className="bg-om-chip rounded-full p-3">
                                <svg className="w-6 h-6 text-om-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                </svg>
                            </div>
                            <div className="flex-1">
                                <h3 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1">{__('Quick PIN')}</h3>
                                <p className="text-om-muted text-[12.5px]">
                                    {hasPin ? __('PIN active — change or remove') : __('Set a 6-digit PIN for fast login')}
                                </p>
                            </div>
                            <svg className="w-5 h-5 text-om-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </Link>
                )}

                <Link href="/settings/two-factor/enable" className="bg-om-card border border-om-line rounded-om p-6 hover:border-om-faint transition-colors cursor-pointer">
                    <div className="flex items-start gap-4">
                        <div className="bg-om-chip rounded-full p-3">
                            <svg className="w-6 h-6 text-om-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div className="flex-1">
                            <h3 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-1">{__('Two-Factor Authentication')}</h3>
                            <p className="text-om-muted text-[12.5px]">
                                {twoFactorEnabled ? __('2FA is on — manage or disable') : __('Add a TOTP authenticator for extra security')}
                            </p>
                        </div>
                        <svg className="w-5 h-5 text-om-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>

                {linkCards(hooks, 'display.settings.index.cards').map((card, i) => (
                    <ModuleCard key={i} {...card} />
                ))}
            </div>
        </div>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
