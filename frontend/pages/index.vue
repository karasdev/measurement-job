<script setup lang="ts">
const featureCards = [
  {
    icon: 'i-lucide-server-cog',
    title: 'Laravel + Filament',
    description: 'Laravel handles the API and job flow, and Filament is used for the admin panel.',
  },
  {
    icon: 'i-lucide-monitor-smartphone',
    title: 'Nuxt + Nuxt UI',
    description: 'Nuxt powers the frontend app, and Nuxt UI provides the ready components used in the screens.',
  },
  {
    icon: 'i-lucide-database-zap',
    title: 'MySQL + Queues',
    description: 'MySQL stores users, jobs, and results, while queue workers run generation and processing in the background.',
  },
]
</script>

<template>
  <div class="landing-shell text-white">
    <UContainer class="relative z-10 flex min-h-screen flex-col px-4 py-6 sm:py-8 lg:py-10">
      <header class="h-10" />

      <main class="flex flex-1 items-center py-10 sm:py-14 lg:py-20">
        <section class="mx-auto w-full max-w-5xl text-center">
          <UBadge
            color="primary"
            variant="soft"
            class="rounded-full border border-primary/35 px-4 py-1 text-xs tracking-wider text-primary-300 uppercase"
          >
            Husain Alsaigh
          </UBadge>

          <h1 class="mt-6 text-balance text-4xl font-extrabold tracking-tight sm:text-6xl lg:text-7xl">
            Full stack
            <span class="block text-primary">Challenge</span>
          </h1>

          <p class="mx-auto mt-6 max-w-3xl text-pretty text-lg leading-relaxed text-white/70 sm:text-2xl">
            High-performance real-time data processing powered by Laravel and WebSockets. Process millions of rows.
          </p>

          <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
            <UButton to="/register" color="primary" size="xl" class="px-8">
              Get Started
            </UButton>
            <UButton to="/login" variant="soft" color="neutral" size="xl" class="px-8">
              Sign In
            </UButton>
          </div>

          <div id="features" class="mt-14 grid gap-4 text-left md:grid-cols-3">
            <UCard
              v-for="feature in featureCards"
              :key="feature.title"
              class="feature-card border border-white/10 bg-white/5"
            >
              <template #header>
                <div class="inline-flex rounded-lg bg-primary/15 p-2 text-primary">
                  <UIcon :name="feature.icon" class="h-5 w-5" />
                </div>
              </template>
              <h3 class="text-lg font-semibold text-white">{{ feature.title }}</h3>
              <p class="mt-2 text-sm leading-relaxed text-white/65">{{ feature.description }}</p>
            </UCard>
          </div>
        </section>
      </main>
    </UContainer>
  </div>
</template>

<style scoped>
.landing-shell {
  position: relative;
  min-height: 100vh;
  background: linear-gradient(180deg, #0b1227 0%, #070b18 100%);
}

.feature-card {
  transition: transform 150ms ease, border-color 150ms ease;
}

.feature-card:hover {
  transform: translateY(-2px);
  border-color: rgb(59 130 246 / 0.4);
}
</style>
