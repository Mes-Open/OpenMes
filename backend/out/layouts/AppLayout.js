import { useEffect, useMemo, useRef, useState } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import { ICONS, ADMIN_LINKS, ADMIN_GROUPS } from "./adminNav";
import LiveAlertCount from "../components/LiveAlertCount";
import { LiveShapesProvider } from "../components/LiveShapesProvider";
import { __ } from "../lib/i18n";
function AppLayout({ children }) {
  const page = usePage();
  const { auth, nav, csrf_token, appVersion } = page.props;
  const path = (page.url || "").split("?")[0];
  const [collapsed, setCollapsed] = useState(
    () => typeof window !== "undefined" && localStorage.getItem("sb") === "1"
  );
  const [mobileOpen, setMobileOpen] = useState(false);
  const [dark, setDark] = useState(
    () => typeof document !== "undefined" && document.documentElement.classList.contains("dark")
  );
  const toggleCollapsed = () => {
    setCollapsed((c) => {
      const next = !c;
      localStorage.setItem("sb", next ? "1" : "0");
      return next;
    });
  };
  const toggleDark = () => {
    setDark((d) => {
      const next = !d;
      document.documentElement.classList.toggle("dark", next);
      localStorage.setItem("theme", next ? "dark" : "light");
      return next;
    });
  };
  const showLabels = !collapsed || mobileOpen;
  return /* @__PURE__ */ React.createElement(LiveShapesProvider, null, /* @__PURE__ */ React.createElement("div", { className: "flex h-screen overflow-hidden bg-om-bg" }, /* @__PURE__ */ React.createElement(LiveAlertCount, { fallback: nav?.alertCount ?? 0 }, (alertCount) => /* @__PURE__ */ React.createElement(
    Sidebar,
    {
      auth,
      alertCount,
      csrfToken: csrf_token,
      appVersion,
      path,
      collapsed,
      mobileOpen,
      showLabels,
      dark,
      onToggleCollapsed: toggleCollapsed,
      onToggleDark: toggleDark,
      onCloseMobile: () => setMobileOpen(false)
    }
  )), mobileOpen && /* @__PURE__ */ React.createElement(
    "div",
    {
      onClick: () => setMobileOpen(false),
      className: "fixed inset-0 bg-black/50 z-30 lg:hidden"
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col flex-1 min-w-0 overflow-hidden" }, /* @__PURE__ */ React.createElement("header", { className: "lg:hidden shrink-0 flex items-center gap-3 h-14 px-4 bg-om-card border-b border-om-line z-20" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => setMobileOpen(true),
      className: "p-2 rounded-om-sm text-om-muted hover:bg-om-chip hover:text-om-ink"
    },
    /* @__PURE__ */ React.createElement(Icon, { d: "M4 6h16M4 12h16M4 18h16", className: "w-6 h-6" })
  ), /* @__PURE__ */ React.createElement("span", { className: "flex items-center gap-2.5" }, /* @__PURE__ */ React.createElement("img", { src: "/logo_open_mes.png", alt: "OpenMES", className: "h-8 w-auto" }))), /* @__PURE__ */ React.createElement(DesktopClock, null), /* @__PURE__ */ React.createElement("main", { className: "flex-1 overflow-auto p-4 md:p-6 lg:p-8" }, /* @__PURE__ */ React.createElement(FlashMessages, null), children))));
}
function FlashMessages() {
  const { flash } = usePage().props;
  if (!flash?.success && !flash?.error) return null;
  return /* @__PURE__ */ React.createElement("div", { className: "mb-4 space-y-2" }, flash.success && /* @__PURE__ */ React.createElement("div", { className: "p-3 rounded-om-sm bg-om-running-bg border border-om-line text-om-running text-[13px]" }, flash.success), flash.error && /* @__PURE__ */ React.createElement("div", { className: "p-3 rounded-om-sm bg-om-blocked-bg border border-om-line text-om-blocked text-[13px]" }, flash.error));
}
function DesktopClock() {
  const { locale } = usePage().props;
  const fmt = () => {
    const now = /* @__PURE__ */ new Date();
    const tz = { timeZone: "Europe/Warsaw" };
    return {
      date: now.toLocaleDateString(locale || "en", { ...tz, weekday: "short", day: "numeric", month: "short", year: "numeric" }),
      time: now.toLocaleTimeString(locale || "en", { ...tz, hour: "2-digit", minute: "2-digit", second: "2-digit" })
    };
  };
  const [t, setT] = useState(fmt);
  useEffect(() => {
    const id = setInterval(() => setT(fmt()), 1e3);
    return () => clearInterval(id);
  }, [locale]);
  return /* @__PURE__ */ React.createElement("div", { className: "hidden lg:flex items-center justify-end px-4 py-1.5 shrink-0" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 text-[13px] text-om-faint" }, /* @__PURE__ */ React.createElement(Icon, { className: "w-4 h-4", d: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" }), /* @__PURE__ */ React.createElement("span", null, t.date), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, t.time)));
}
function flattenNavItems(showTab = () => true) {
  const items = ADMIN_LINKS.filter((link) => showTab(link.key)).map((link) => ({
    label: link.label,
    href: link.href,
    match: link.match,
    exact: link.exact,
    trail: []
  }));
  const walk = (nodes, trail) => {
    nodes.forEach((node) => {
      if (node.href && !node.disabled) {
        items.push({ label: node.label, href: node.href, match: node.match, exact: node.exact, trail });
      }
      if (node.children) {
        walk(node.children, [...trail, node.label]);
      }
    });
  };
  walk(ADMIN_GROUPS.filter((group) => showTab(group.tab ?? group.key)), []);
  items.push({ label: "Settings", href: "/settings", match: ["/settings"], trail: [] });
  return items;
}
function Sidebar({
  auth,
  alertCount,
  csrfToken,
  appVersion,
  path,
  collapsed,
  mobileOpen,
  showLabels,
  dark,
  onToggleCollapsed,
  onToggleDark,
  onCloseMobile
}) {
  const isAdmin = auth?.user?.roles?.includes("Admin");
  const widthClass = collapsed ? "lg:w-16" : "lg:w-64";
  const translate = mobileOpen ? "translate-x-0" : "-translate-x-full";
  const allowedTabs = auth?.user?.accessibleTabs;
  const showTab = (key) => !Array.isArray(allowedTabs) || !key || allowedTabs.includes(key);
  const [query, setQuery] = useState("");
  const searchItems = useMemo(() => flattenNavItems(showTab), [allowedTabs]);
  const q = query.trim().toLowerCase();
  const results = q ? searchItems.filter((item) => [item.label, __(item.label), ...item.trail.flatMap((t) => [t, __(t)])].join(" ").toLowerCase().includes(q)) : null;
  const clearSearch = () => setQuery("");
  const submitSearch = () => {
    if (results?.length) {
      router.visit(results[0].href);
      clearSearch();
    }
  };
  return /* @__PURE__ */ React.createElement(
    "aside",
    {
      className: `fixed inset-y-0 left-0 z-40 flex flex-col shrink-0 bg-om-panel text-om-ink w-64
                        border-r border-om-line
                        lg:relative lg:inset-auto lg:z-auto lg:translate-x-0 overflow-hidden
                        transition-[width,transform] duration-300 ease-in-out ${translate} ${widthClass}`
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center h-16 px-3 shrink-0 border-b border-om-line" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "flex items-center gap-2 min-w-0 overflow-hidden" }, showLabels ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("img", { src: "/logo_open_mes.png", alt: "OpenMES", className: "h-9 w-auto shrink-0" }), appVersion && /* @__PURE__ */ React.createElement("span", { className: "shrink-0 rounded border border-om-line px-[5px] py-px font-mono text-[9px] text-om-faint" }, appVersion)) : /* @__PURE__ */ React.createElement("span", { className: "block size-9 shrink-0 overflow-hidden" }, /* @__PURE__ */ React.createElement("img", { src: "/logo_open_mes.png", alt: "OpenMES", className: "h-9 max-w-none" }))), showLabels && isAdmin && /* @__PURE__ */ React.createElement(
      Link,
      {
        href: "/onboarding/step/1",
        prefetch: true,
        title: __("Setup Wizard"),
        className: "ml-auto p-1.5 rounded-full text-om-faint hover:text-om-ink hover:bg-om-chip shrink-0"
      },
      /* @__PURE__ */ React.createElement(Icon, { d: "M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z", className: "w-5 h-5" })
    ), /* @__PURE__ */ React.createElement(
      "button",
      {
        onClick: onCloseMobile,
        className: `lg:hidden ${showLabels && isAdmin ? "" : "ml-auto"} p-1.5 rounded-om-sm text-om-faint hover:text-om-ink hover:bg-om-chip shrink-0`
      },
      /* @__PURE__ */ React.createElement(Icon, { d: "M6 18L18 6M6 6l12 12", className: "w-5 h-5" })
    )),
    /* @__PURE__ */ React.createElement(
      NavSearch,
      {
        query,
        onChange: setQuery,
        onSubmit: submitSearch,
        collapsed,
        showLabels,
        onExpand: onToggleCollapsed
      }
    ),
    /* @__PURE__ */ React.createElement("nav", { className: "sidebar-scroll flex-1 overflow-y-auto overflow-x-hidden pb-3 space-y-0.5" }, results ? results.length ? results.map((item) => (
      // Group headers can share an href with their first child
      // (e.g. Orders and All Orders), so href alone isn't unique.
      /* @__PURE__ */ React.createElement(
        SearchResultLink,
        {
          key: `${item.trail.join("/")}>${item.label}`,
          item,
          path,
          onNavigate: clearSearch
        }
      )
    )) : /* @__PURE__ */ React.createElement("p", { className: "px-5 py-3 text-[13px] text-om-faint" }, __("No results")) : /* @__PURE__ */ React.createElement(React.Fragment, null, ADMIN_LINKS.filter((link) => showTab(link.key)).map((link) => /* @__PURE__ */ React.createElement(
      NavLink,
      {
        key: link.href,
        link,
        path,
        collapsed,
        showLabels,
        alertCount: link.alert ? alertCount : 0
      }
    )), showLabels && /* @__PURE__ */ React.createElement("div", { className: "mx-4 my-2 border-t border-om-line" }), ADMIN_GROUPS.filter((group) => showTab(group.tab ?? group.key)).map((group) => /* @__PURE__ */ React.createElement(
      NavGroup,
      {
        key: group.key,
        group,
        path,
        collapsed,
        showLabels
      }
    )))),
    /* @__PURE__ */ React.createElement("div", { className: "border-t border-om-line shrink-0" }, /* @__PURE__ */ React.createElement("div", { className: "px-2 pt-2" }, /* @__PURE__ */ React.createElement(
      "button",
      {
        onClick: onToggleDark,
        className: `flex items-center gap-3 w-full px-3 py-2.5 rounded-om-sm text-[13px] font-medium
                                    text-om-muted hover:bg-om-chip hover:text-om-ink transition-colors
                                    ${collapsed && !mobileOpen ? "justify-center !px-0" : ""}`
      },
      /* @__PURE__ */ React.createElement(
        Icon,
        {
          className: "w-5 h-5 shrink-0",
          d: dark ? "M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" : "M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
        }
      ),
      showLabels && /* @__PURE__ */ React.createElement("span", null, dark ? __("Light Mode") : __("Dark Mode"))
    )), /* @__PURE__ */ React.createElement("div", { className: "px-2 pt-2" }, /* @__PURE__ */ React.createElement(
      Link,
      {
        href: "/settings",
        prefetch: true,
        className: `flex items-center gap-3 px-3 py-2.5 rounded-om-sm text-[13px] font-medium transition-colors
                                    ${isActive(path, ["/settings"]) ? "bg-om-ink text-om-on-ink" : "text-om-muted hover:bg-om-chip hover:text-om-ink"}
                                    ${collapsed && !mobileOpen ? "justify-center !px-0" : ""}`
      },
      /* @__PURE__ */ React.createElement(Icon, { d: ICONS.settings, className: "w-5 h-5 shrink-0" }),
      showLabels && /* @__PURE__ */ React.createElement("span", null, __("Settings"))
    )), /* @__PURE__ */ React.createElement("div", { className: "px-2 py-2" }, /* @__PURE__ */ React.createElement("div", { className: `flex items-center gap-3 px-3 py-2 rounded-lg ${collapsed && !mobileOpen ? "justify-center" : ""}` }, /* @__PURE__ */ React.createElement(
      Link,
      {
        href: "/settings/profile",
        prefetch: true,
        title: __("Profile"),
        className: `flex items-center gap-3 min-w-0 rounded-om-sm hover:bg-om-chip transition-colors
                                        ${collapsed && !mobileOpen ? "" : "flex-1 -ml-1 pl-1 pr-2 py-0.5"}`
      },
      /* @__PURE__ */ React.createElement("div", { className: "w-8 h-8 rounded-full bg-om-ink flex items-center justify-center shrink-0 text-om-on-ink text-[12px] font-semibold" }, auth?.user?.initial ?? "?"),
      showLabels && /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0 text-left" }, /* @__PURE__ */ React.createElement("p", { className: "text-[13px] font-medium text-om-ink truncate" }, auth?.user?.name), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint truncate" }, auth?.user?.roles?.[0] ?? "User"))
    ), /* @__PURE__ */ React.createElement("form", { action: "/logout", method: "POST", className: "shrink-0" }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrfToken }), /* @__PURE__ */ React.createElement(
      "button",
      {
        type: "submit",
        title: __("Logout"),
        className: "p-1.5 rounded-om-sm text-om-faint hover:text-om-blocked hover:bg-om-chip transition-colors"
      },
      /* @__PURE__ */ React.createElement(Icon, { d: "M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1", className: "w-4 h-4" })
    )))), /* @__PURE__ */ React.createElement("div", { className: "hidden lg:flex border-t border-om-line px-2 py-2" }, /* @__PURE__ */ React.createElement(
      "button",
      {
        onClick: onToggleCollapsed,
        className: "flex items-center justify-center w-full py-2 rounded-om-sm text-om-faint hover:text-om-ink hover:bg-om-chip transition-colors",
        title: collapsed ? __("Expand sidebar") : __("Collapse sidebar")
      },
      /* @__PURE__ */ React.createElement(
        Icon,
        {
          className: `w-5 h-5 transition-transform ${collapsed ? "rotate-180" : ""}`,
          d: "M11 19l-7-7 7-7m8 14l-7-7 7-7"
        }
      ),
      !collapsed && /* @__PURE__ */ React.createElement("span", { className: "ml-2 text-[13px]" }, __("Collapse"))
    )))
  );
}
const SEARCH_ICON = "M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z";
function NavSearch({ query, onChange, onSubmit, collapsed, showLabels, onExpand }) {
  const inputRef = useRef(null);
  const focusAfterExpand = useRef(false);
  useEffect(() => {
    if (showLabels && focusAfterExpand.current) {
      focusAfterExpand.current = false;
      inputRef.current?.focus();
    }
  }, [showLabels]);
  if (collapsed && !showLabels) {
    return /* @__PURE__ */ React.createElement("div", { className: "relative group px-2 pt-3" }, /* @__PURE__ */ React.createElement(
      "button",
      {
        onClick: () => {
          focusAfterExpand.current = true;
          onExpand();
        },
        className: "flex items-center justify-center w-full py-2.5 rounded-om-sm text-om-faint hover:text-om-ink hover:bg-om-chip transition-colors"
      },
      /* @__PURE__ */ React.createElement(Icon, { d: SEARCH_ICON, className: "w-5 h-5" })
    ), /* @__PURE__ */ React.createElement(Tooltip, null, __("Search")));
  }
  return /* @__PURE__ */ React.createElement("div", { className: "px-2 pt-3 pb-2" }, /* @__PURE__ */ React.createElement("div", { className: "relative" }, /* @__PURE__ */ React.createElement(
    Icon,
    {
      d: SEARCH_ICON,
      className: "absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-om-faint pointer-events-none"
    }
  ), /* @__PURE__ */ React.createElement(
    "input",
    {
      ref: inputRef,
      type: "search",
      value: query,
      onChange: (e) => onChange(e.target.value),
      onKeyDown: (e) => {
        if (e.key === "Escape") onChange("");
        if (e.key === "Enter") onSubmit();
      },
      placeholder: __("Search menu\u2026"),
      className: "w-full pl-9 pr-3 py-2 rounded-om-sm bg-om-bg border border-om-line text-[13px]\n                               text-om-ink placeholder:text-om-faint\n                               focus:outline-none focus:border-om-ink focus:ring-1 focus:ring-om-ink"
    }
  )));
}
function SearchResultLink({ item, path, onNavigate }) {
  const active = isActive(path, item.match, item.exact);
  return /* @__PURE__ */ React.createElement("div", { className: "px-2" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: item.href,
      prefetch: true,
      onClick: onNavigate,
      className: `flex flex-col gap-0.5 px-3 py-2 rounded-om-sm text-[13px] transition-colors
                            ${active ? "bg-om-ink text-om-on-ink" : "text-om-muted hover:bg-om-chip hover:text-om-ink"}`
    },
    /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, __(item.label)),
    item.trail.length > 0 && /* @__PURE__ */ React.createElement("span", { className: `text-xs ${active ? "text-white/60" : "text-om-faint"}` }, item.trail.map((t) => __(t)).join(" / "))
  ));
}
function NavLink({ link, path, collapsed, showLabels, alertCount }) {
  const active = isActive(path, link.match, link.exact);
  const activeClass = active ? "bg-om-ink text-om-on-ink" : "text-om-muted hover:bg-om-chip hover:text-om-ink";
  return /* @__PURE__ */ React.createElement("div", { className: "relative group px-2" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: link.href,
      prefetch: true,
      className: `flex items-center gap-3 px-3 py-2.5 rounded-om-sm text-[13px] font-medium transition-colors
                            ${activeClass} ${collapsed && !showLabels ? "justify-center !px-0" : ""}`
    },
    /* @__PURE__ */ React.createElement("span", { className: "relative shrink-0" }, /* @__PURE__ */ React.createElement(Icon, { d: ICONS[link.icon], className: "w-5 h-5" }), alertCount > 0 && /* @__PURE__ */ React.createElement("span", { className: "absolute -top-1.5 -right-1.5 flex items-center justify-center w-4 h-4 rounded-full bg-om-blocked text-white font-mono text-[9px] leading-none" }, alertCount > 9 ? "9+" : alertCount)),
    showLabels && /* @__PURE__ */ React.createElement("span", { className: "flex items-center gap-2" }, __(link.label), link.alert && alertCount > 0 && /* @__PURE__ */ React.createElement("span", { className: "inline-flex items-center justify-center px-[7px] py-px rounded-full bg-om-blocked-bg text-om-blocked font-mono text-[10px]" }, alertCount))
  ), collapsed && !showLabels && /* @__PURE__ */ React.createElement(Tooltip, null, __(link.label)));
}
function NavGroup({ group, path, collapsed, showLabels }) {
  const groupActive = isActive(path, group.match);
  const [open, setOpen] = useState(groupActive);
  useEffect(() => {
    if (groupActive) setOpen(true);
  }, [groupActive]);
  const toggle = () => {
    if (group.href) {
      router.visit(group.href);
      setOpen(true);
    } else {
      setOpen((o) => !o);
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: "px-2" }, /* @__PURE__ */ React.createElement("div", { className: "relative group" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: toggle,
      className: `flex items-center gap-3 w-full px-3 py-2.5 rounded-om-sm transition-colors
                                text-om-faint hover:bg-om-chip hover:text-om-ink
                                ${collapsed && !showLabels ? "justify-center !px-0" : ""}
                                ${groupActive && showLabels ? "text-om-ink" : ""}`
    },
    /* @__PURE__ */ React.createElement(Icon, { d: ICONS[group.icon], className: "w-5 h-5 shrink-0" }),
    showLabels && /* @__PURE__ */ React.createElement("span", { className: "flex-1 text-left font-mono text-[10px] uppercase tracking-[0.12em]" }, __(group.label)),
    showLabels && /* @__PURE__ */ React.createElement(
      Icon,
      {
        d: "M19 9l-7 7-7-7",
        className: `w-4 h-4 shrink-0 transition-transform ${open ? "rotate-180" : ""}`
      }
    )
  ), collapsed && !showLabels && /* @__PURE__ */ React.createElement(Tooltip, null, __(group.label))), open && showLabels && /* @__PURE__ */ React.createElement("div", { className: "mt-0.5 ml-4 space-y-0.5 border-l border-om-line pl-3" }, group.children.map(
    (child) => child.children ? /* @__PURE__ */ React.createElement(SubGroup, { key: child.key, group: child, path }) : /* @__PURE__ */ React.createElement(ChildLink, { key: child.href, child, path })
  )));
}
function SubGroup({ group, path }) {
  const active = isActive(path, group.match);
  const [open, setOpen] = useState(active);
  useEffect(() => {
    if (active) setOpen(true);
  }, [active]);
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => setOpen((o) => !o),
      className: `flex items-center gap-2 w-full px-2 py-1.5 rounded-om-sm text-[13px] transition-colors
                            ${active ? "text-om-ink font-medium" : "text-om-muted hover:bg-om-chip hover:text-om-ink"}`
    },
    /* @__PURE__ */ React.createElement("span", { className: "w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60" }),
    __(group.label),
    /* @__PURE__ */ React.createElement(
      Icon,
      {
        d: "M19 9l-7 7-7-7",
        className: `w-3 h-3 ml-auto shrink-0 transition-transform ${open ? "rotate-180" : ""}`
      }
    )
  ), open && /* @__PURE__ */ React.createElement("div", { className: "ml-3 mt-0.5 space-y-0.5 border-l border-om-line2 pl-3" }, group.children.map((child) => /* @__PURE__ */ React.createElement(ChildLink, { key: child.href, child, path, dot: "sm" }))));
}
function ChildLink({ child, path, dot }) {
  const active = isActive(path, child.match, child.exact);
  const dotClass = dot === "sm" ? "w-1 h-1 opacity-50" : "w-1.5 h-1.5 opacity-60";
  if (child.disabled) {
    return /* @__PURE__ */ React.createElement(
      "span",
      {
        title: child.title ? __(child.title) : void 0,
        className: "flex items-center gap-2 px-2 py-1.5 rounded-om-sm text-[13px] text-om-faintest cursor-not-allowed select-none"
      },
      /* @__PURE__ */ React.createElement("span", { className: `rounded-full bg-current shrink-0 ${dotClass}` }),
      __(child.label),
      child.badge && /* @__PURE__ */ React.createElement("span", { className: "ml-auto font-mono text-[10px] bg-om-chip text-om-faint px-1.5 py-0.5 rounded" }, __(child.badge))
    );
  }
  return /* @__PURE__ */ React.createElement(
    Link,
    {
      href: child.href,
      prefetch: true,
      className: `flex items-center gap-2 px-2 py-1.5 rounded-om-sm text-[13px] transition-colors
                        ${active ? "bg-om-ink text-om-on-ink font-medium" : "text-om-muted hover:bg-om-chip hover:text-om-ink"}`
    },
    /* @__PURE__ */ React.createElement("span", { className: `rounded-full bg-current shrink-0 ${dotClass}` }),
    __(child.label)
  );
}
function Tooltip({ children }) {
  return /* @__PURE__ */ React.createElement("span", { className: "absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-om-ink text-om-on-ink text-xs rounded-om-sm whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity shadow-[0_18px_44px_-18px_rgba(0,0,0,.3)] pointer-events-none" }, children);
}
function Icon({ d, className }) {
  return /* @__PURE__ */ React.createElement("svg", { className, fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d }));
}
function isActive(path, matches = [], exact = false) {
  return matches.some((m) => exact ? path === m : path === m || path.startsWith(m + "/") || path === m);
}
export {
  AppLayout as default
};
