import { defineConfig } from "vitepress";

// https://vitepress.dev/reference/site-config
export default defineConfig({
    title: "Langfy",
    description:
        "AI-powered translation package for Laravel applications with automatic string discovery and intelligent translation management.",

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
                items: [
                    { text: "Introduction", link: "/" },
                    { text: "Finding Strings", link: "/finding-strings" },
                ],
            },
            {
                text: "Commands",
                items: [{ text: "Finder Command", link: "/commands/finder" }],
            },
            {
                collapsed: true,
                text: "API Reference",
                items: [{ text: "Langfy Class", link: "/api/langfy" }],
            },
        ],

        socialLinks: [
            { icon: "github", link: "https://github.com/e2tmk/langfy" },
        ],
    },
});
