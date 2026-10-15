export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',

  ssr: false,

  devtools: { enabled: true },

  // Nuxt 4: source files live in app/
  future: {
    compatibilityVersion: 4,
  },

  modules: [
    '@nuxt/ui',
    '@pinia/nuxt',
    '@vueuse/nuxt',
  ],

  css: ['~/assets/css/main.css'],

  // Field tablets are often offline. Bundle every icon into the client so
  // nothing is fetched from the Iconify API at runtime.
  icon: {
    provider: 'none',
    fallbackToApi: false,
    clientBundle: {
      // Picks up every literal i-heroicons-* name used in app/.
      scan: true,
      // Icons Nuxt UI components use internally (not visible to the scanner).
      icons: [
        'lucide:arrow-down',
        'lucide:arrow-left',
        'lucide:arrow-right',
        'lucide:arrow-up',
        'lucide:arrow-up-right',
        'lucide:check',
        'lucide:chevron-down',
        'lucide:chevron-left',
        'lucide:chevron-right',
        'lucide:chevron-up',
        'lucide:chevrons-left',
        'lucide:chevrons-right',
        'lucide:circle-alert',
        'lucide:circle-check',
        'lucide:circle-x',
        'lucide:copy',
        'lucide:copy-check',
        'lucide:ellipsis',
        'lucide:eye',
        'lucide:eye-off',
        'lucide:file',
        'lucide:folder',
        'lucide:folder-open',
        'lucide:grip-vertical',
        'lucide:hash',
        'lucide:info',
        'lucide:lightbulb',
        'lucide:loader-circle',
        'lucide:menu',
        'lucide:minus',
        'lucide:monitor',
        'lucide:moon',
        'lucide:panel-left-close',
        'lucide:panel-left-open',
        'lucide:plus',
        'lucide:rotate-ccw',
        'lucide:search',
        'lucide:square',
        'lucide:sun',
        'lucide:terminal',
        'lucide:triangle-alert',
        'lucide:upload',
        'lucide:x',
      ],
    },
  },

  // Light mode reads better on glossy tablet screens in bright clinics.
  colorMode: {
    preference: 'light',
    fallback: 'light',
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },

  // Runtime config — override via .env
  runtimeConfig: {
    public: {
      appName: 'PenPlus Monitoring',
      // CouchDB URL including credentials, e.g. http://admin:pass@localhost:5984
      // Set NUXT_PUBLIC_COUCHDB_URL in .env to override
      couchdbUrl: process.env.NUXT_PUBLIC_COUCHDB_URL ?? 'http://admin:1234@localhost:5984',
    }
  },
   devServer: {
    host: '0.0.0.0',
    port: 3005
  },
})

