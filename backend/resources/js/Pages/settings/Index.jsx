import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

function NavCard({ href, borderColor, bgColor, iconColor, iconPath, title, description }) {
    return (
        <Link
            href={href}
            className={`card hover:shadow-lg transition-shadow cursor-pointer flex items-start gap-4 border-l-4 ${borderColor}`}
        >
            <div className={`${bgColor} rounded-full p-3 flex-shrink-0`}>
                <svg className={`w-6 h-6 ${iconColor}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={iconPath} />
                </svg>
            </div>
            <div className="flex-1">
                <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{title}</h3>
                <p className="text-gray-600 dark:text-gray-300 text-sm">{description}</p>
            </div>
            <svg className="w-5 h-5 text-gray-400 dark:text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
            </svg>
        </Link>
    );
}

export default function Index() {
    const { auth, pinLoginEnabled, hasPin, twoFactorEnabled } = usePage().props;
    const isAdmin = auth?.user?.roles?.includes('Admin');

    return (
        <div className="max-w-4xl mx-auto">
            <Head title={__('Settings')} />
            <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100 mb-6">{__('Settings')}</h1>

            {isAdmin && (
                <div className="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <NavCard
                        href="/settings/system"
                        borderColor="border-blue-400"
                        bgColor="bg-blue-100 dark:bg-blue-900/30"
                        iconColor="text-blue-600 dark:text-blue-300"
                        iconPath="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        title={__('System Settings')}
                        description={__('Production period split, overproduction rules, step sequencing')}
                    />
                    <NavCard
                        href="/admin/dashboard-widgets"
                        borderColor="border-amber-400"
                        bgColor="bg-amber-100 dark:bg-amber-900/30"
                        iconColor="text-amber-600 dark:text-amber-300"
                        iconPath="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"
                        title={__('Dashboard Setup')}
                        description={__('Enable, disable, and reorder dashboard widgets')}
                    />
                    <NavCard
                        href="/onboarding"
                        borderColor="border-green-400"
                        bgColor="bg-green-100 dark:bg-green-900/30"
                        iconColor="text-green-600 dark:text-green-300"
                        iconPath="M13 10V3L4 14h7v7l9-11h-7z"
                        title={__('Setup Wizard')}
                        description={__('Re-launch the onboarding wizard')}
                    />
                    <NavCard
                        href="/settings/api-tokens"
                        borderColor="border-purple-400"
                        bgColor="bg-purple-100 dark:bg-purple-900/30"
                        iconColor="text-purple-600 dark:text-purple-300"
                        iconPath="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                        title={__('API Tokens')}
                        description={__('Manage tokens for external integrations')}
                    />
                    <NavCard
                        href="/admin/custom-fields"
                        borderColor="border-indigo-400"
                        bgColor="bg-indigo-100 dark:bg-indigo-900/30"
                        iconColor="text-indigo-600 dark:text-indigo-300"
                        iconPath="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"
                        title={__('Custom Fields')}
                        description={__('Define extra fields on records across the system')}
                    />
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Link href="/settings/profile" className="card hover:shadow-lg transition-shadow cursor-pointer">
                    <div className="flex items-start gap-4">
                        <div className="bg-blue-100 dark:bg-blue-900/30 rounded-full p-3">
                            <svg className="w-6 h-6 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div className="flex-1">
                            <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Profile')}</h3>
                            <p className="text-gray-600 dark:text-gray-300 text-sm">{__('Update your profile and account info')}</p>
                        </div>
                        <svg className="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>

                <Link href="/settings/change-password" className="card hover:shadow-lg transition-shadow cursor-pointer">
                    <div className="flex items-start gap-4">
                        <div className="bg-green-100 dark:bg-green-900/30 rounded-full p-3">
                            <svg className="w-6 h-6 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <div className="flex-1">
                            <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Change Password')}</h3>
                            <p className="text-gray-600 dark:text-gray-300 text-sm">{__('Change your account password')}</p>
                        </div>
                        <svg className="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>

                {pinLoginEnabled && (
                    <Link href="/settings/pin" className="card hover:shadow-lg transition-shadow cursor-pointer">
                        <div className="flex items-start gap-4">
                            <div className="bg-amber-100 dark:bg-amber-900/30 rounded-full p-3">
                                <svg className="w-6 h-6 text-amber-600 dark:text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                </svg>
                            </div>
                            <div className="flex-1">
                                <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Quick PIN')}</h3>
                                <p className="text-gray-600 dark:text-gray-300 text-sm">
                                    {hasPin ? __('PIN active — change or remove') : __('Set a 4–6 digit PIN for fast login')}
                                </p>
                            </div>
                            <svg className="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </Link>
                )}

                <Link href="/settings/two-factor/enable" className="card hover:shadow-lg transition-shadow cursor-pointer">
                    <div className="flex items-start gap-4">
                        <div className="bg-green-100 dark:bg-green-900/30 rounded-full p-3">
                            <svg className="w-6 h-6 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div className="flex-1">
                            <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{__('Two-Factor Authentication')}</h3>
                            <p className="text-gray-600 dark:text-gray-300 text-sm">
                                {twoFactorEnabled ? __('2FA is on — manage or disable') : __('Add a TOTP authenticator for extra security')}
                            </p>
                        </div>
                        <svg className="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>
            </div>
        </div>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
