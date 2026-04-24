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
    },
  },
})
