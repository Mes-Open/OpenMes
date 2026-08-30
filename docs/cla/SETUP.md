# CLA — one-time setup (maintainer)

The CLA gate is a GitHub Action (`.github/workflows/cla.yml`, "CLA Assistant"). It runs on every pull
request, asks unsigned contributors to sign by commenting, records signatures in a **private** repo, and
blocks merge until all commit authors have signed. Owner accounts, org members and bots are allow-listed.

Do these steps once (GitHub web UI + a token). Order matters.

## 1. Create the signatures repo

Create a **private** repo **`Mes-Open/cla-signatures`** with an empty `README.md`. Signatures are stored
here as `signatures/version1/cla.json` (created automatically on the first signature).

## 2. Create a token and add it as a secret

- Create a **fine-grained personal access token** scoped to **only** `Mes-Open/cla-signatures`, with
  **Repository permissions → Contents: Read and write**.
- In **`Mes-Open/OpenMes` → Settings → Secrets and variables → Actions**, add a secret named
  **`CLA_SIGNATURES_PAT`** with that token.

## 3. Allow the workflow to write

**`Mes-Open/OpenMes` → Settings → Actions → General → Workflow permissions →** enable
**"Read and write permissions"** (the action posts PR comments and updates the commit status).

## 4. Require the check on the protected branch

In branch protection for **`main`** (and, if PRs land there, **`develop`**), require the status check
**`CLA Assistant`** to pass before merging.

## 5. Publish CLA.md where the link points

`cla.yml` points contributors to `https://github.com/Mes-Open/OpenMes/blob/main/CLA.md`. Make sure `CLA.md`
is present on `main` (it ships to `main` at the next release). Until then, either merge `CLA.md` to `main`
first or temporarily point `path-to-document` at the `develop` blob.

## 6. Test it

Open a test PR from an account **not** on the allowlist. Confirm: the bot comments asking for a signature →
comment the sign phrase → the check turns green → a row appears in `Mes-Open/cla-signatures`
(`signatures/version1/cla.json`).

Also confirm the **CodeRabbit gate**: before signing, the PR has **no `cla-signed` label** and CodeRabbit
does **not** auto-review; after signing, the workflow adds `cla-signed` and CodeRabbit reviews. (The label
is created automatically the first time it is applied; you may pre-create a `cla-signed` label with a
distinctive colour if you like.)

## CodeRabbit ordering (sign first, then review)

`.coderabbit.yaml` sets `reviews.auto_review.enabled: false` with `labels: [cla-signed]`, so CodeRabbit
only auto-reviews PRs that carry the **`cla-signed`** label. `cla.yml` adds that label once the CLA step
passes (all commit authors signed or allow-listed) and removes it if a later unsigned commit is pushed.
Net effect: **contributors sign first, then CodeRabbit reviews** — no review budget spent on unsigned PRs.
The hard merge block remains the required **`CLA Assistant`** status check (step 4); the label is only a
review-timing convenience.

---

## Tool note (accepted risk + alternative)

`cla.yml` uses **`contributor-assistant/github-action`** (CLA Assistant Lite), **pinned to the exact commit
of `v2.6.1`** (`ca4a40a…`). That repo was **archived in March 2026** — it is frozen, not broken; pinning to
a SHA removes the moving-tag supply-chain risk, it runs only in CI, and it never ships in the product. Its
advantage is that the **signature ledger stays inside `Mes-Open`** (your own private repo).

If you prefer an actively-maintained tool, the alternative is the hosted **[cla-assistant.io](https://cla-assistant.io)**
(SAP): a GitHub App, no workflow file or PAT to manage — but the signature data then lives on SAP's servers,
not in your organization. Choose based on whether keeping the ledger in `Mes-Open` is a hard requirement.

## Allowlist maintenance

The allowlist in `cla.yml` covers only the owner's own accounts and bots:
`jakub-przepiora`, `jakubprzepiora-cyber`, `dependabot[bot]`, `github-actions[bot]`, `renovate[bot]`.

Org members (`Svannte` / Mateusz Łuczyński, `JanKolo04` / Jan Kołodziej, `ElNinio978`) are **intentionally
not allow-listed** — they sign via the bot as well; their ICLA then operates on behalf of and with the
company's consent (CLA §C4). Add or remove logins here as the team changes.
