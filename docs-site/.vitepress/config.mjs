import { defineConfig } from 'vitepress'

export default defineConfig({
    title: 'Laravel Model Explorer',
    description: 'A zero-config browser UI for exploring your Eloquent models.',
    base: '/laravel-model-explorer/',

    head: [
        ['link', { rel: 'icon', href: '/laravel-model-explorer/favicon.ico' }],
    ],

    themeConfig: {
        nav: [
            { text: 'Guide', link: '/guide/getting-started' },
            { text: 'GitHub', link: 'https://github.com/one-learning-community/laravel-model-explorer' },
            { text: 'Packagist', link: 'https://packagist.org/packages/onelearningcommunity/laravel-model-explorer' },
        ],

        sidebar: [
            {
                text: 'Guide',
                items: [
                    { text: 'Getting Started', link: '/guide/getting-started' },
                    { text: 'Configuration', link: '/guide/configuration' },
                ],
            },
            {
                text: 'Features',
                items: [
                    { text: 'Model List', link: '/guide/model-list' },
                    { text: 'Model Detail', link: '/guide/model-detail' },
                    { text: 'Record Browser', link: '/guide/record-browser' },
                    { text: 'Relationship Graph', link: '/guide/relationship-graph' },
                ],
            },
        ],

        socialLinks: [
            { icon: 'github', link: 'https://github.com/one-learning-community/laravel-model-explorer' },
        ],

        footer: {
            message: 'Released under the MIT License.',
            copyright: 'Copyright © One Learning Community',
        },

        search: {
            provider: 'local',
        },
    },
})
