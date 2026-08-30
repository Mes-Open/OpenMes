# OpenMES Licensing

OpenMES uses a **layered / dual-licensing** model. This file explains which
license applies to what. It is a summary; the license texts themselves govern.

## 1. Core — AGPL-3.0

The OpenMES **core** (everything except the `modules/` layer) is licensed under
the **GNU Affero General Public License v3.0 (AGPL-3.0)**. See [`LICENSE`](LICENSE).

Running a modified version over a network requires making the corresponding
source available under the same license (AGPL §13).

## 2. Modules — AFL-3.0

Code under **`modules/`** is licensed under the **Academic Free License 3.0
(AFL-3.0)** — a permissive license — so a module may be distributed under
terms of the author's choosing, including **closed / proprietary**. See
[`LICENSE-AFL-3.0.txt`](LICENSE-AFL-3.0.txt).

A file is under AFL-3.0 only if it lives in `modules/` **and** carries, adjacent
to its copyright notice, the notice:

```
Licensed under the Academic Free License version 3.0
```

Everything else in the repository is AGPL-3.0.

> Note: `modules/` is currently deprecated in favor of core (see `.gitignore`);
> most module code ships from separate repositories. This layer's AFL-3.0 terms
> apply to such module code wherever it is distributed as an OpenMES module.

## 3. Commercial licensing

OpenMES is **also** available under separate **commercial licenses** (for
partners who cannot accept AGPL obligations, OEM/white-label, and hosted/SaaS
offerings). The Project Owner can offer these because contributors grant the
necessary rights through the Contributor License Agreement (see below). Contact
the Project Owner for commercial terms.

## 4. Contributor License Agreement

Contributions are accepted under the **OpenMES CLA** ([`CLA.md`](CLA.md)): you keep
your copyright and grant the Project Owner a broad, irrevocable, sublicensable
license that makes the dual-licensing above possible. See
[`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md).

## 5. Module / core boundary

AFL-3.0 is not GPL-compatible (per the FSF). For the module layer to be
distributable under AFL/proprietary terms, a module must be a **separate work**,
not a derivative of the AGPL core — i.e. the core/module boundary must be a
genuine arm's-length interface, not tight linking that would make the module a
derivative of AGPL code.

---

*Summary: **core = AGPL-3.0**, **`modules/` = AFL-3.0**, **commercial licenses available**,
**contributions under the CLA**. The `LICENSE` and `LICENSE-AFL-3.0.txt` texts are authoritative.*
