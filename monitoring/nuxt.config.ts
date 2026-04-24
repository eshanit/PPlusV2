export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',

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
    couchdbUrl: process.env.COUCHDB_URL ?? '',
    couchdbUser: process.env.COUCHDB_USER ?? '',
    couchdbPassword: process.env.COUCHDB_PASSWORD ?? '',
    public: {
      appName: 'PenPlus Monitoring',
    },
  },
})
