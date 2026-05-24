# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 0.x (latest) | Yes |
| < latest | No |

> OpenMES is in **beta**. Security patches are applied to the latest release only. We recommend always running the most recent version.

## Reporting a Vulnerability

**Please do NOT report security vulnerabilities through public GitHub issues.**

Instead, report them privately via:

- **Email**: [contact@getopenmes.com](mailto:contact@getopenmes.com)
- **Founder**: [jakub.przepiora@nice-code.com](mailto:jakub.przepiora@nice-code.com)

### What to include

The most valuable reports are ones that **point to specific code in the repository** and provide a **clear way to reproduce** the issue. Ideally:

- **File and line reference** — e.g. `app/Http/Controllers/FooController.php:42` where the vulnerability exists
- **Reproduction steps** — step-by-step instructions or a proof-of-concept script that demonstrates the issue
- **Description** — what the vulnerability is and why it is exploitable
- **Affected version(s)** — commit hash or release tag
- **Impact assessment** — what an attacker could achieve (data leak, privilege escalation, RCE, etc.)
- **Suggested fix** — if you have one, even a rough idea helps

Reports that include code references and reproduction steps are prioritised and resolved significantly faster.

### What to expect

| Step | Timeframe |
|------|-----------|
| Acknowledgement | Within 48 hours |
| Initial assessment | Within 5 business days |
| Fix released | Within 14 days (critical), 30 days (other) |
| Public disclosure | After fix is released and deployed |

We will keep you informed of the progress and may ask for additional information.

## Scope

### In scope

- Authentication and authorization bypass
- SQL injection, XSS, CSRF
- Remote code execution
- Sensitive data exposure (credentials, PII leaks)
- Privilege escalation between roles (Operator, Supervisor, Admin)
- Tenant isolation bypass (cross-tenant data access)
- Insecure direct object references (IDOR)

### Out of scope

- Denial of service (DoS/DDoS) attacks
- Social engineering (phishing, pretexting)
- Vulnerabilities in third-party dependencies (report to the upstream project instead, and let us know)
- Issues on the public demo instance (demo.getopenmes.com) that don't affect self-hosted deployments
- Missing security headers on non-production environments
- Clickjacking on pages with no sensitive actions

## Responsible Disclosure

We kindly ask that you:

1. **Do not** publicly disclose the vulnerability before we have released a fix
2. **Do not** access or modify data belonging to other users
3. **Do not** perform testing on the public demo that degrades the service for others
4. **Do** give us reasonable time to investigate and address the issue

## Recognition

We appreciate the security research community. Contributors who report valid vulnerabilities will be:

- Credited in the release notes (unless they prefer to remain anonymous)
- Listed in the project's security acknowledgements

## Security Best Practices for Deployment

If you are deploying OpenMES in production, please ensure:

- [ ] `APP_DEBUG=false` in `.env`
- [ ] `APP_ENV=production` in `.env`
- [ ] Strong, unique `APP_KEY` (generated automatically on first boot)
- [ ] HTTPS enabled (Caddy handles this automatically)
- [ ] Database credentials are not default values
- [ ] `.env` file is not accessible from the web
- [ ] Regular updates to the latest version
- [ ] `composer audit` and `npm audit` run periodically
