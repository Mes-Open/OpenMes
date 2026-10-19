// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, InlineAlert, TextField } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

const HELP_CLASS = 'text-[11.5px] text-om-faint mt-1';

/**
 * Admin → Settings → Single Sign-On (OpenID Connect).
 *
 * Props (from SsoSettingsController::show):
 *   settings     — stored configuration; the client secret itself never comes
 *                  back, only `has_client_secret`
 *   callbackUrl  — the redirect URI to register at the identity provider
 *   roles        — roles a new account may be given (never Admin)
 *
 * POST /settings/sso → SsoSettingsController::update (redirects back + flash)
 */
export default function Sso({ settings, callbackUrl, roles = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        enabled: !!settings.enabled,
        provider_name: settings.provider_name ?? '',
        issuer: settings.issuer ?? '',
        client_id: settings.client_id ?? '',
        client_secret: '',
        clear_client_secret: false,
        scopes: settings.scopes ?? 'openid profile email',
        username_claim: settings.username_claim ?? 'preferred_username',
        auto_provision: !!settings.auto_provision,
        default_role: settings.default_role ?? 'Operator',
        link_by_email: settings.link_by_email !== false,
        allowed_domains: settings.allowed_domains ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/settings/sso', { preserveScroll: true, onSuccess: () => setData('client_secret', '') });
    };

    return (
        <div className="max-w-2xl mx-auto">
            <Head title={__('Single Sign-On')} />

            <div className="flex items-center gap-3 mb-6">
                <Link href="/settings" className="text-om-muted hover:text-om-ink transition-colors" aria-label={__('Back')}>
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.02em] text-om-ink">{__('Single Sign-On')}</h1>
                    <p className="text-om-muted text-[12.5px] mt-0.5">
                        {__('Let people sign in with your company identity provider (OpenID Connect): Keycloak, Microsoft Entra ID, ADFS, Google Workspace, Authentik, Okta…')}
                    </p>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-6">
                <section className="bg-om-card border border-om-line rounded-om p-6 space-y-4">
                    <h2 className="text-[15px] font-semibold text-om-ink">{__('Identity provider')}</h2>

                    <Checkbox
                        id="sso_enabled"
                        checked={data.enabled}
                        onChange={(v) => setData('enabled', v)}
                        label={__('Enable sign-in with single sign-on')}
                    />

                    <div>
                        <p className="block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">
                            {__('Redirect URI')}
                        </p>
                        <code data-testid="sso-callback-url" className="block w-full break-all text-[12.5px] font-mono text-om-ink bg-om-chip rounded-om-sm px-3 py-2.5">
                            {callbackUrl}
                        </code>
                        <p className={HELP_CLASS}>{__('Register this address as an allowed redirect URI of the OpenMES client at your identity provider.')}</p>
                    </div>

                    <TextField
                        id="sso_provider_name"
                        label={__('Button label')}
                        value={data.provider_name}
                        onChange={(v) => setData('provider_name', v)}
                        error={errors.provider_name}
                        hint={__('Shown on the login screen as "Sign in with …".')}
                        required
                    />

                    <TextField
                        id="sso_issuer"
                        label={__('Issuer URL')}
                        value={data.issuer}
                        onChange={(v) => setData('issuer', v)}
                        error={errors.issuer}
                        placeholder="https://keycloak.company.local/realms/factory"
                        hint={__('OpenMES reads the rest from {issuer}/.well-known/openid-configuration.')}
                        mono
                    />

                    <TextField
                        id="sso_client_id"
                        label={__('Client ID')}
                        value={data.client_id}
                        onChange={(v) => setData('client_id', v)}
                        error={errors.client_id}
                        mono
                    />

                    <TextField
                        id="sso_client_secret"
                        type="password"
                        label={__('Client secret')}
                        value={data.client_secret}
                        onChange={(v) => setData('client_secret', v)}
                        error={errors.client_secret}
                        autoComplete="new-password"
                        hint={settings.has_client_secret
                            ? __('A secret is stored. Leave empty to keep it.')
                            : __('Leave empty for a public client (PKCE only).')}
                    />
                    {settings.has_client_secret && (
                        <Checkbox
                            id="sso_clear_secret"
                            checked={data.clear_client_secret}
                            onChange={(v) => setData('clear_client_secret', v)}
                            label={__('Remove the stored client secret')}
                        />
                    )}

                    <TextField
                        id="sso_scopes"
                        label={__('Scopes')}
                        value={data.scopes}
                        onChange={(v) => setData('scopes', v)}
                        error={errors.scopes}
                        mono
                        required
                    />
                </section>

                <section className="bg-om-card border border-om-line rounded-om p-6 space-y-4">
                    <h2 className="text-[15px] font-semibold text-om-ink">{__('Accounts')}</h2>

                    <div>
                        <Checkbox
                            id="sso_link_by_email"
                            checked={data.link_by_email}
                            onChange={(v) => setData('link_by_email', v)}
                            label={__('Link existing accounts by email address')}
                        />
                        <p className={HELP_CLASS}>{__('The first SSO sign-in binds an existing OpenMES account with the same verified email to that identity.')}</p>
                    </div>

                    <div>
                        <Checkbox
                            id="sso_auto_provision"
                            checked={data.auto_provision}
                            onChange={(v) => setData('auto_provision', v)}
                            label={__('Create an account on first sign-in')}
                        />
                        <p className={HELP_CLASS}>{__('Otherwise only people who already have an OpenMES account can sign in.')}</p>
                    </div>

                    {data.auto_provision && (
                        <div>
                            <p className="block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">
                                {__('Role for new accounts')}
                            </p>
                            <Dropdown
                                aria-label={__('Role for new accounts')}
                                options={roles.map((r) => ({ value: r, label: __(r) }))}
                                value={data.default_role}
                                onChange={(v) => setData('default_role', v)}
                                className="w-full max-w-xs"
                            />
                            {errors.default_role && <p className="mt-[5px] text-[11.5px] text-om-blocked">{errors.default_role}</p>}
                        </div>
                    )}

                    <TextField
                        id="sso_username_claim"
                        label={__('Username claim')}
                        value={data.username_claim}
                        onChange={(v) => setData('username_claim', v)}
                        error={errors.username_claim}
                        hint={__('Which claim becomes the username of a new account, e.g. preferred_username or upn.')}
                        mono
                        required
                    />

                    <TextField
                        id="sso_allowed_domains"
                        label={__('Allowed email domains')}
                        value={data.allowed_domains}
                        onChange={(v) => setData('allowed_domains', v)}
                        error={errors.allowed_domains}
                        placeholder="company.com, plant.company.com"
                        hint={__('Comma-separated. Leave empty to allow everyone the identity provider signs in.')}
                    />
                </section>

                <InlineAlert severity="info">
                    {__('Password sign-in stays available, so an administrator can always get in if the identity provider is unreachable.')}
                </InlineAlert>

                <div className="flex justify-end">
                    <Button type="submit" variant="accent" loading={processing} data-testid="sso-save">
                        {__('Save')}
                    </Button>
                </div>
            </form>
        </div>
    );
}

Sso.layout = (page) => <AppLayout>{page}</AppLayout>;
