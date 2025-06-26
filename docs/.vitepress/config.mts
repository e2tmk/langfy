import { defineConfig } from "vitepress";

// https://vitepress.dev/reference/site-config
export default defineConfig({
    title: "Langfy",
    description:
        "Powerful utility for finding and processing Eloquent model records with a fluent, chainable API.",

    head: [["link", { rel: "icon", href: "/logo-purple.png" }]],

    themeConfig: {
        logo: {
            light: "/logo-purple.png",
            dark: "/logo-zinc.png",
        },

        nav: [
            { text: "Home", link: "/" },
            { text: "Examples", link: "/examples" },
        ],

        sidebar: [
            {
                text: "Getting Started",
                items: [{ text: "Introduction", link: "/" }],
            },
            {
                text: "API Reference",
                items: [{ text: "Langfy Class", link: "/api/langfy" }],
            },
        ],

        socialLinks: [
            { icon: "github", link: "https://github.com/e2tmk/langfy" },
        ],
    },
});
