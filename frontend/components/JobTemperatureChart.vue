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
  const isDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
  const gridColor = isDark ? 'rgba(148, 163, 184, 0.12)' : 'rgba(100, 116, 139, 0.16)'
  const tickColor = isDark ? 'rgba(203, 213, 225, 0.72)' : 'rgba(71, 85, 105, 0.8)'
  const labelColor = isDark ? 'rgba(226, 232, 240, 0.86)' : 'rgba(30, 41, 59, 0.88)'
  chartInstance?.destroy()
  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: slice.value.map((r) => r.city),
      datasets: [
        { label: 'Min', data: slice.value.map((r) => r.min_temp), backgroundColor: 'rgba(37, 99, 235, 0.78)', borderRadius: 3 },
        { label: 'Avg', data: slice.value.map((r) => r.avg_temp), backgroundColor: 'rgba(5, 150, 105, 0.78)', borderRadius: 3 },
        { label: 'Max', data: slice.value.map((r) => r.max_temp), backgroundColor: 'rgba(220, 38, 38, 0.76)', borderRadius: 3 },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: {
            color: labelColor,
            boxWidth: 22,
            boxHeight: 8,
            useBorderRadius: true,
          },
        },
        tooltip: {
          backgroundColor: isDark ? 'rgba(15, 23, 42, 0.96)' : 'rgba(255, 255, 255, 0.96)',
          borderColor: isDark ? 'rgba(51, 65, 85, 0.9)' : 'rgba(203, 213, 225, 0.95)',
          borderWidth: 1,
          titleColor: labelColor,
          bodyColor: labelColor,
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: tickColor, maxRotation: 45, minRotation: 35 },
        },
        y: {
          grid: { color: gridColor },
          ticks: { color: tickColor },
          title: { display: true, text: 'Temperature (°C)', color: tickColor },
        },
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
  <div class="h-[360px] w-full">
    <canvas v-if="slice.length" ref="canvas" />
    <p v-else class="text-sm text-gray-500 dark:text-gray-400">No temperature data to chart.</p>
  </div>
</template>
