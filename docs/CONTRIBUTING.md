# Contributing to OpenMES

Thank you for your interest in contributing to OpenMES! This document outlines how to get started and what we expect from contributions.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Licensing](#licensing)
- [Contributor License Agreement](#contributor-license-agreement)
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

## Licensing

OpenMES is open-source software:

- the **core** is distributed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**;
- the **module layer** (`modules/`) is distributed under the **Academic Free License 3.0 (AFL-3.0)**, so modules may be closed/proprietary.

OpenMES may **also** be offered under separate **commercial** licensing terms. This dual-licensing model helps fund continued development while keeping an AGPL-licensed version available to the open-source community.

---

## Contributor License Agreement

> **You keep your copyright. OpenMES stays open source. The CLA allows us to offer OpenMES under additional licenses, including commercial licenses.**

Before we can merge your **first** contribution, please sign the OpenMES Contributor License Agreement ([CLA.md](../CLA.md)).

**What the CLA means**
- You **keep the copyright** to your work — the CLA does **not** transfer ownership of your contribution.
- You grant the Project Owner the rights needed to use your contribution in OpenMES and to license OpenMES under both open-source and commercial models.
- You remain free to use and license your own contribution elsewhere.
- You confirm you have the right to contribute the code you submit.

**Why we require it** — without a contributor agreement each contributor would hold copyright in their own contributions, which makes a consistent dual-licensing model impossible as the project grows. The CLA lets OpenMES keep shipping as open source, offer commercial/SaaS options that fund development, and transfer stewardship to a future company without asking everyone to re-sign.

**How to sign** — open your pull request; a bot will comment if a signature is needed. Reply with the exact phrase it asks for:

```
I have read the CLA Document and I hereby sign the CLA
```

Your signature covers all your past and future contributions. It is recorded once; later PRs won't ask again (until the CLA version changes).

**Contributions made for an employer** — if your employer or another organization owns the copyright in your work, do **not** accept the Individual CLA on its behalf unless you are authorized to. Contact the maintainers first — a Corporate CLA ([`docs/cla/CCLA-template.md`](cla/CCLA-template.md)) or other authorization may be required, and the PR won't be merged until it is in place.

**Third-party code** — do not submit code copied from another project unless its license is compatible with OpenMES; identify its source and license in your PR.

**AI-generated code** — if AI tools were materially used, disclose it in the PR where appropriate. You ran the tool and remain responsible for the result; to the best of your knowledge it must not incorporate third-party code under an incompatible license. Commits carry a **human author** (the AI credited at most as `Co-authored-by`).

**Trivial changes** — a change of **≤ 20 lines with no new logic** (typo, formatting, an obvious fix) does not require a CLA; a maintainer may label the PR `cla: trivial` and merge it.

Accepting the CLA records your GitHub username, the date, and the document version. See the privacy note in [CLA.md](../CLA.md#personal-data-gdpr).

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
