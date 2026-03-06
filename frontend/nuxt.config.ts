// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },
  // Keep Nuxt 3 folder structure (pages, components, etc. at root)
  srcDir: '.',
  dir: { app: 'app' },
  modules: ['@nuxt/ui'],
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://127.0.0.1:8000',
      pusherKey: process.env.NUXT_PUBLIC_PUSHER_KEY || '',
      pusherCluster: process.env.NUXT_PUBLIC_PUSHER_CLUSTER || 'mt1',
    },
  },
  app: {
    head: {
      title: 'Measurements Dashboard',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
    },
  },
})
