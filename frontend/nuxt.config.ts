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
      // Reverb (main): default real-time backend
      reverbKey: process.env.NUXT_PUBLIC_REVERB_APP_KEY || '',
      reverbHost: process.env.NUXT_PUBLIC_REVERB_HOST || '127.0.0.1',
      reverbPort: parseInt(process.env.NUXT_PUBLIC_REVERB_PORT || '8080', 10),
      reverbScheme: process.env.NUXT_PUBLIC_REVERB_SCHEME || 'http',
      // Pusher (optional): set NUXT_PUBLIC_USE_PUSHER=true and keys to use Pusher instead
      usePusher: process.env.NUXT_PUBLIC_USE_PUSHER === 'true',
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
