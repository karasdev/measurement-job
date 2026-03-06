<script setup lang="ts">
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

const props = defineProps<{
  results: { city: string; min_temp: number; max_temp: number; avg_temp: number; count: number }[]
  maxCities?: number
}>()

const canvas = ref<HTMLCanvasElement | null>(null)
let chartInstance: Chart | null = null

const maxCities = computed(() => props.maxCities ?? 20)
const slice = computed(() => (props.results || []).slice(0, maxCities.value))

function initChart() {
  if (!canvas.value || !slice.value.length) return
  const ctx = canvas.value.getContext('2d')
  if (!ctx) return
  chartInstance?.destroy()
  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: slice.value.map((r) => r.city),
      datasets: [
        { label: 'Min', data: slice.value.map((r) => r.min_temp), backgroundColor: 'rgba(59, 130, 246, 0.7)' },
        { label: 'Avg', data: slice.value.map((r) => r.avg_temp), backgroundColor: 'rgba(34, 197, 94, 0.7)' },
        { label: 'Max', data: slice.value.map((r) => r.max_temp), backgroundColor: 'rgba(239, 68, 68, 0.7)' },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      aspectRatio: 2,
      plugins: { legend: { position: 'top' } },
      scales: {
        y: { title: { display: true, text: 'Temperature (°C)' } },
      },
    },
  })
}

watch([canvas, slice], () => initChart(), { immediate: true })

onBeforeUnmount(() => {
  chartInstance?.destroy()
  chartInstance = null
})
</script>

<template>
  <div class="w-full" style="max-height: 400px">
    <canvas v-if="slice.length" ref="canvas" />
    <p v-else class="text-sm text-gray-500">No temperature data to chart.</p>
  </div>
</template>
