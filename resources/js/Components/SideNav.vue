<script setup>
import { Link, usePage } from '@inertiajs/vue3'

defineProps({
  groups: { type: Array, required: true }, // [{ title, items: [{ name, href, icon }] }]
})
defineEmits(['navigate'])

const page = usePage()

function isActive(href) {
  const path = new URL(href).pathname
  const url = page.url.split('?')[0]
  return url === path || url.startsWith(path + '/')
}
</script>

<template>
  <nav class="space-y-4">
    <div v-for="group in groups" :key="group.title">
      <p v-if="group.title" class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">{{ group.title }}</p>
      <Link
        v-for="item in group.items" :key="item.name" :href="item.href"
        class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
        :class="isActive(item.href) ? 'bg-seal-500/15 text-seal-300' : 'text-ink-300 hover:bg-ink-800 hover:text-white'"
        @click="$emit('navigate')"
      >
        <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
        </svg>
        {{ item.name }}
      </Link>
    </div>
  </nav>
</template>
