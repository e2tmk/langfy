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
                    { text: "Introduction", link: "/introduction" },
                    { text: "Configuration", link: "/configuration" },
                    { text: "Finding Strings", link: "/finding-strings" },
                ],
            },
            {
                text: "Commands",
                items: [
                    { text: "Finder", link: "/commands/finder" },
                    { text: "Trans", link: "/commands/trans" },
                ],
            },
            {
                collapsed: true,
                text: "API Reference",
                items: [
                    { text: "Langfy Class", link: "/api/langfy" },
                    { text: "Finder Class", link: "/api/finder" },
                    { text: "AITranslator Class", link: "/api/aitranslator" },
                ],
            },
        ],

        socialLinks: [
            { icon: "github", link: "https://github.com/e2tmk/langfy" },
        ],

        search: {
            provider: "local"
        }
    },
});
