---
name: i18n-lang-files
description: Editing or merging backend/lang/en.json and pl.json. Use when adding UI strings, when a merge conflicts in the lang files, or when deciding where a translation belongs. Covers why these files conflict on every merge and how to resolve one without silently resurrecting a deleted key or breaking en/pl parity.
---

# Translation files

`backend/lang/en.json` and `backend/lang/pl.json` are the app's UI strings, keyed by
the English sentence. CLAUDE.md hard rule 2 applies: **both files always carry the
same key set**, and in `en.json` the value equals the key.

## Trailing commas are not an option

The first instinct on seeing these conflicts is "make every line end with a comma so
the last line is never special". **That cannot work.** These are strict `.json` files
read by `json_decode`; a trailing comma before `}` is a parse error, and Laravel would
fall back to returning every key as its own literal — the entire Polish UI would
silently revert to English. There is no punctuation fix. Do not add one, and do not
convert these to `.jsonc`, `.php` arrays or anything else without a deliberate
migration.

## Why every merge conflicts

New keys are appended to the end of the file. Every branch that adds a string touches
the same final line — the one whose comma has to change — so any two such branches
conflict by construction. At the last count, eight open branches were appending to
`en.json`.

Two ways to reduce it, in order of cost:

1. **Append in one block, at the end, in one commit.** Cheap and immediate. It does
   not prevent the conflict but keeps it to a single contiguous hunk that the script
   below resolves in one shot.
2. **Sort the files alphabetically, once.** This is the real fix: insertions scatter
   through the file instead of piling onto the last line, so most branches stop
   overlapping at all. The cost is a one-time full-file rewrite that conflicts with
   every branch currently in flight, so it needs to be scheduled when the branch queue
   is short — it is a team decision, not something to do mid-feature.

## Resolving a conflict

Never resolve these by hand-editing the `<<<<<<<` markers, and never take a plain
"union of both sides". A text-level union gets two cases wrong:

- a key **deliberately deleted** on one side is silently resurrected by the other side
  still having it (this really happened: PR #239 removed two `PAWS` keys that a naive
  union would have put straight back),
- a key **added on both sides** ends up duplicated, and `json_decode` keeps only the
  last one — so a translation disappears without any error.

Use the script instead. It merges git's three stages per key rather than per line:

```bash
# from the repository root, while the merge is conflicted
python3 .claude/skills/i18n-lang-files/resolve-lang-conflict.py --check   # dry run
python3 .claude/skills/i18n-lang-files/resolve-lang-conflict.py           # write
git add backend/lang/*.json
```

It reports what it did, keeps intentional deletions deleted, checks en/pl parity and
duplicate keys, and **refuses to guess** when both sides changed the same
translation's value differently — those it prints for a human to settle.

## Adding a string

- Add the key to **both** files in the same commit. `en.json`: value = key.
- Check it does not already exist — `grep -n '"Your string"' backend/lang/en.json`
  before adding, since a near-duplicate wording is worse than a reused key.
- Placeholders are `:name`, e.g. `"Cancel work order :order?"`.
- Strings belonging to an optional module do **not** go here. A module ships its own
  namespaced translations (`lang/{en,pl}/messages.php` + `loadTranslationsFrom`,
  referenced as `mymodule::messages.key`), so core carries no vendor vocabulary. See
  the Pantheon connector for the pattern.

## Verifying

```bash
python3 - <<'PY'
import json
en = json.load(open('backend/lang/en.json', encoding='utf-8'))
pl = json.load(open('backend/lang/pl.json', encoding='utf-8'))
print('en', len(en), 'pl', len(pl))
print('missing from pl:', sorted(set(en) - set(pl))[:10])
print('missing from en:', sorted(set(pl) - set(en))[:10])
print('untranslated:', [k for k, v in pl.items() if v == k][:10])
PY
```

Both files must parse, the key sets must match, and a `pl.json` value equal to its key
means the string was added but never translated.
