import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'

export default [
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            globals: {
                ...globals.browser,
                // Ziggy exposes route() globally via the Blade template.
                route: 'readonly',
                // Leaflet is loaded as a global from resources/views/app.blade.php.
                L: 'readonly',
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            // Laravel paginator labels are trusted server-rendered HTML.
            'vue/no-v-text-v-html-on-component': 'off',
            'vue/html-self-closing': ['error', { html: { void: 'always', normal: 'never', component: 'always' } }],
        },
    },
    {
        ignores: ['node_modules/**', 'public/build/**', 'vendor/**', 'resources/js/Pages/Auth/**'],
    },
]
