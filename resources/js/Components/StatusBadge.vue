<script setup>
import { computed } from 'vue'
import { badgeClass } from '@/Composables/format'

const props = defineProps({
  status: { type: String, required: true },
  labels: { type: Object, default: () => ({}) },
})
const text = computed(() => props.labels[props.status] ?? props.status)
</script>

<template>
  <span :class="badgeClass(status)">{{ text }}</span>
</template>
