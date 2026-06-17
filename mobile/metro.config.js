const path = require('path');
const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);

// @openmes/ui is a file: symlink to ../packages/ui (shared with the web app).
// watchFolders makes Metro watch + serve files outside mobile/; nodeModulesPaths
// makes the package's own react/react-native imports resolve from
// mobile/node_modules (its real path sits outside, where the upward
// node_modules walk would miss them) — guaranteeing a single React copy.
config.watchFolders = [
  ...(config.watchFolders ?? []),
  path.resolve(__dirname, '../packages/ui'),
];
config.resolver.nodeModulesPaths = [
  path.resolve(__dirname, 'node_modules'),
  ...(config.resolver.nodeModulesPaths ?? []),
];

const originalResolveRequest = config.resolver.resolveRequest;
config.resolver.resolveRequest = (context, moduleName, platform) => {
  const next =
    originalResolveRequest ??
    ((ctx, name, plat) => ctx.resolveRequest(ctx, name, plat));

  // zustand's ESM build (./esm/*.mjs) uses `import.meta.env`, which the
  // browser refuses to parse outside a module script and Metro does not
  // transform. On web there's no `react-native` exports condition to fall
  // back to, so Metro picks the ESM build and the bundle blows up with
  // "Cannot use 'import.meta' outside a module". Per-call
  // unstable_conditionNames doesn't actually override conditions in this
  // version of Metro, so map zustand subpaths to the CJS files directly.
  if (
    platform === 'web' &&
    (moduleName === 'zustand' || moduleName.startsWith('zustand/'))
  ) {
    const subpath = moduleName === 'zustand' ? 'index' : moduleName.slice('zustand/'.length);
    return {
      type: 'sourceFile',
      filePath: path.join(__dirname, 'node_modules', 'zustand', `${subpath}.js`),
    };
  }

  return next(context, moduleName, platform);
};

module.exports = config;
