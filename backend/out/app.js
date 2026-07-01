import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { loadLocale, setTimezone } from "./lib/i18n";
import "./lib/echo";
createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob("./Pages/**/*.jsx", { eager: true });
    const page = pages[`./Pages/${name}.jsx`];
    if (!page) {
      throw new Error(`Inertia page not found: ${name} (expected resources/js/Pages/${name}.jsx)`);
    }
    return page;
  },
  async setup({ el, App, props }) {
    await loadLocale(props.initialPage.props.locale ?? "en");
    setTimezone(props.initialPage.props.timezone);
    window.__TENANT__ = props.initialPage.props.auth?.user?.tenant_id ?? "g";
    createRoot(el).render(/* @__PURE__ */ React.createElement(App, { ...props }));
  },
  progress: { color: "#1e40af" }
});
