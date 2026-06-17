/**
 * @openmes/ui/table — web-only DataTable (TanStack Table v8).
 * Separate subpath so the universal barrel stays resolvable under Metro
 * (DataTable has no native twin). Root-level shim, same reason as native.ts.
 */
export * from './src/DataTable';
