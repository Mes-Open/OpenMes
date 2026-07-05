# Vendored Geist Mono (patched plain zero)

These `GeistMono_*.ttf` files are **locally modified** copies of
[Geist Mono](https://github.com/vercel/geist-font) (the same builds shipped by
`@expo-google-fonts/geist-mono`).

## Why they're patched

Geist Mono's default `0` glyph is **slashed**. The plain round zero lives in the
font as the `zero.ss09` glyph, reachable on the web via
`font-feature-settings: 'ss09' 1`. React Native, however, has no reliable
per-call way to toggle that feature across **both** iOS and Android, and the app
sets the mono font in ~15 places (not all through the `Mono` component), so a
style-level fix would be fragile and easy to miss.

Instead we patch the font once: the default `zero` glyph's outline is replaced
with the plain `zero.ss09` outline (the glyph **name** is kept so the GSUB
fraction features `numr`/`dnom`/`frac` still resolve). Every mono digit then
renders with a round zero regardless of how the font is referenced.

`app/_layout.tsx` loads these files instead of the ones in `node_modules`.

## Recipe (reproduce after a font upgrade)

```python
# pip install fonttools
import copy
from fontTools.ttLib import TTFont

for w in ["400Regular", "500Medium", "600SemiBold"]:
    src = f"node_modules/@expo-google-fonts/geist-mono/{w}/GeistMono_{w}.ttf"
    f = TTFont(src)
    f["glyf"]["zero"] = copy.deepcopy(f["glyf"]["zero.ss09"])  # plain outline
    f["hmtx"]["zero"] = f["hmtx"]["zero.ss09"]                 # (monospace: same advance)
    f.save(f"assets/fonts/GeistMono_{w}.ttf")
```

## License

Geist Mono is licensed under the SIL Open Font License 1.1 (see `OFL.txt`). The
license permits modification; the Geist OFL declares **no Reserved Font Name**,
so the modified files keep the `Geist Mono` family name. Copyright 2024 The Geist
Project Authors.
