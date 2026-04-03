# Contributing to OpenMES

Thank you for your interest in contributing to OpenMES! This document outlines how to get started and what we expect from contributions.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Ways to Contribute](#ways-to-contribute)
- [Development Setup](#development-setup)
- [Submitting Changes](#submitting-changes)
- [Pull Request Guidelines](#pull-request-guidelines)
- [Commit Message Convention](#commit-message-convention)
- [Issue Reporting](#issue-reporting)

---

## Code of Conduct

We expect contributors to be respectful and constructive. We do not tolerate harassment or discrimination of any kind.

---

## Ways to Contribute

- **Bug reports** — open an issue with steps to reproduce
- **Feature requests** — open an issue describing the use case and expected behaviour
- **Documentation** — fix typos, improve explanations, add examples
- **Code** — fix a bug or implement a feature (see below)
- **Translations** — help translate the UI to other languages
- **Modules** — build and share modules that extend OpenMES

---

## Development Setup

See [development.md](development.md) for full setup instructions.

Quick summary:

```bash
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes
docker-compose up -d
# Complete the web installer at http://localhost
cd backend && composer install && npm install && npm run dev
```

---

## Submitting Changes

1. **Fork** the repository on GitHub
2. **Create a branch** from `main`:
   ```bash
   git checkout -b feature/my-feature
   # or
   git checkout -b fix/issue-description
   ```
3. **Make your changes** following the guidelines below
4. **Run tests** — all tests must pass:
   ```bash
   php artisan test
   ```
5. **Run the formatter**:
   ```bash
   ./vendor/bin/pint
   ```
6. **Commit** your changes (see [Commit Message Convention](#commit-message-convention))
7. **Push** and open a Pull Request against `main`

---

## Pull Request Guidelines

- **One feature per PR** — keep PRs focused and reviewable
- **Include tests** — new features and bug fixes must include test coverage
- **Update documentation** — if the change affects user-visible behaviour, update the relevant doc files
- **Describe the change** — explain what you changed and why in the PR description
- **Reference issues** — link to any related issues with `Closes #123` or `Fixes #456`

### PR Checklist

- [ ] Tests written and passing (`php artisan test`)
- [ ] Code formatted (`./vendor/bin/pint`)
- [ ] No raw SQL with user input
- [ ] Backend validation via Form Requests
- [ ] Authorization checks in place
- [ ] No new dependencies added without justification

---

## Commit Message Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>: <short description>
```

Types:
| Type | Use for |
|---|---|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `test` | Adding or fixing tests |
| `chore` | Build process, config, CI, version bumps |
| `style` | Formatting, whitespace (no logic change) |

Examples:
```
feat: add EAN barcode scanning to Packaging module
fix: prevent packed_qty from exceeding planned quantity
docs: add supervisor guide
chore: bump version to v0.3.7
```

---

## Issue Reporting

When opening an issue, please include:

- **Version** — which version of OpenMES you are running
- **Environment** — Docker, bare metal, OS, PHP version
- **Steps to reproduce** — exact steps that trigger the problem
- **Expected behaviour** — what should happen
- **Actual behaviour** — what actually happens
- **Screenshots or logs** — if relevant

For security vulnerabilities, please do **not** open a public issue. Contact the maintainers directly at **support@openmmes.com**.
