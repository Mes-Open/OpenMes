#!/usr/bin/env python3
"""Resolve a merge conflict in backend/lang/*.json.

Works on git's three stages rather than on the <<<<<<< markers in the working
tree. Each stage is a complete, valid JSON file, so the merge can be done per
key instead of per line — which is the only way to get the two cases that a
line-level union gets wrong:

  * a key deleted on our side and still present on theirs stays deleted
    (a text union silently resurrects it),
  * a key added on both sides appears once, not twice.

A key whose value both sides changed differently is never guessed at: it is
reported and left for a human.

Usage:
    python3 resolve-lang-conflict.py [--check] [paths...]

    no paths   every conflicted backend/lang/*.json
    --check    report what would happen, write nothing
"""

from __future__ import annotations

import json
import subprocess
import sys
from collections import Counter, OrderedDict

STAGES = {"base": 1, "ours": 2, "theirs": 3}


def git(*args: str) -> str:
    return subprocess.run(
        ["git", *args], capture_output=True, text=True, check=True
    ).stdout


def conflicted_lang_files() -> list[str]:
    out = git("diff", "--name-only", "--diff-filter=U")
    return [
        p
        for p in out.splitlines()
        if p.startswith("backend/lang/") and p.endswith(".json")
    ]


def stage(path: str, n: int, label: str) -> "OrderedDict[str, str]":
    """One side of the conflict, as an ordered key -> value map.

    Duplicates are caught here, because this is the only point they are still
    visible: json.loads keeps the last occurrence and drops the rest silently,
    so a file with a doubled key parses fine and quietly loses a translation.
    """
    raw = git("show", f":{n}:{path}")
    pairs: list[tuple[str, str]] = json.loads(raw, object_pairs_hook=list)

    dupes = [k for k, c in Counter(k for k, _ in pairs).items() if c > 1]
    if dupes:
        print(f"  !! {path} ({label}) has duplicate key(s): {dupes[:5]}")

    return OrderedDict(pairs)


def merge(base, ours, theirs) -> tuple["OrderedDict[str, str]", list[str], dict]:
    deleted_by_us = set(base) - set(ours)
    deleted_by_them = set(base) - set(theirs)

    merged: "OrderedDict[str, str]" = OrderedDict()
    conflicts: list[str] = []

    def value_for(k: str):
        in_ours, in_theirs = k in ours, k in theirs

        if in_ours and in_theirs:
            if ours[k] == theirs[k]:
                return ours[k]
            # Both sides changed the same translation. Prefer the side that
            # actually changed it relative to base; if both did, ask a human.
            if base.get(k) == theirs[k]:
                return ours[k]
            if base.get(k) == ours[k]:
                return theirs[k]
            conflicts.append(k)
            return ours[k]

        return ours[k] if in_ours else theirs[k]

    # Our order first, so an existing file keeps its shape and the diff stays
    # readable; then whatever the other side added, in their order.
    for k in ours:
        if k in deleted_by_them:
            continue
        merged[k] = value_for(k)

    for k in theirs:
        if k in merged or k in deleted_by_us:
            continue
        merged[k] = value_for(k)

    stats = {
        "kept_deleted_by_us": sorted(deleted_by_us & set(theirs)),
        "kept_deleted_by_them": sorted(deleted_by_them & set(ours)),
        "added_by_them": [k for k in theirs if k not in ours and k not in deleted_by_us],
    }
    return merged, conflicts, stats


def dump(mapping) -> str:
    """Match the formatting Laravel's lang files already use: 4 spaces, unescaped
    unicode, no trailing comma (JSON forbids one — that is why these conflicts
    cannot be avoided by punctuation)."""
    return json.dumps(mapping, ensure_ascii=False, indent=4) + "\n"


def main() -> int:
    argv = [a for a in sys.argv[1:] if a != "--check"]
    check_only = "--check" in sys.argv

    paths = argv or conflicted_lang_files()
    if not paths:
        print("No conflicted backend/lang/*.json files.")
        return 0

    failed = False
    written: dict[str, "OrderedDict[str, str]"] = {}

    for path in paths:
        try:
            base = stage(path, STAGES["base"], "base")
            ours = stage(path, STAGES["ours"], "ours")
            theirs = stage(path, STAGES["theirs"], "theirs")
        except subprocess.CalledProcessError:
            print(f"{path}: not a conflicted file (no merge stages) — skipped")
            continue

        merged, conflicts, stats = merge(base, ours, theirs)

        print(f"\n{path}")
        print(f"  base {len(base)} | ours {len(ours)} | theirs {len(theirs)} -> {len(merged)}")
        if stats["added_by_them"]:
            print(f"  + {len(stats['added_by_them'])} new key(s) from the other side")
        for k in stats["kept_deleted_by_us"]:
            print(f"  - stayed deleted (we removed it): {k[:70]}")
        for k in stats["kept_deleted_by_them"]:
            print(f"  - stayed deleted (they removed it): {k[:70]}")

        if conflicts:
            failed = True
            print(f"  !! {len(conflicts)} key(s) changed differently on both sides — resolve by hand:")
            for k in conflicts:
                print(f"     {k[:70]}")
                print(f"       ours:   {ours[k][:70]}")
                print(f"       theirs: {theirs[k][:70]}")

        written[path] = merged
        if not check_only and not conflicts:
            with open(path, "w", encoding="utf-8") as fh:
                fh.write(dump(merged))

    # en/pl must carry the same key set (CLAUDE.md hard rule 2).
    en = next((m for p, m in written.items() if p.endswith("en.json")), None)
    pl = next((m for p, m in written.items() if p.endswith("pl.json")), None)
    if en is not None and pl is not None:
        only_en, only_pl = sorted(set(en) - set(pl)), sorted(set(pl) - set(en))
        print(f"\nparity: en {len(en)} | pl {len(pl)}")
        for k in only_en:
            print(f"  missing from pl.json: {k[:70]}")
        for k in only_pl:
            print(f"  missing from en.json: {k[:70]}")
        if only_en or only_pl:
            failed = True

    if check_only:
        print("\n--check: nothing written.")
    elif not failed:
        print("\nWritten. Stage them with: git add backend/lang/*.json")

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
