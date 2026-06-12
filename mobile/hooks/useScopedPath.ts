import { usePathname } from 'expo-router';

/**
 * Returns a path for a shared resource that stays in the current role's URL
 * space. E.g. an admin browsing `/admin/orders` who opens a work order should
 * land on `/admin/work-orders/123` (not `/work-orders/123`), so back nav
 * returns to the admin orders list and the URL keeps showing the role.
 *
 * Detection is based on the current URL: if it starts with `/admin/` we
 * prefix with `admin`, if `/operator/` we prefix with `operator`, otherwise
 * we return the bare path. Role-prefixed wrappers live under
 * `app/(drawer)/{admin,operator}/<resource>/...` and re-export the shared
 * screen.
 */
export function useRoleScopedPath() {
  const pathname = usePathname();
  return (sharedPath: string): string => {
    const clean = sharedPath.startsWith('/') ? sharedPath : `/${sharedPath}`;
    if (pathname.startsWith('/admin/') || pathname === '/admin')
      return `/admin${clean}`;
    if (pathname.startsWith('/operator/') || pathname === '/operator')
      return `/operator${clean}`;
    return clean;
  };
}
