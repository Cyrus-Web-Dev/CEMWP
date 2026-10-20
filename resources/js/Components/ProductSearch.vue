<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  warehouseId: { type: [Number, String], default: null }, // bila diisi, hasil menyertakan stok tersedia di lokasi itu
  placeholder: { type: String, default: 'Cari produk (nama, SKU, atau barcode)…' },
})
const emit = defineEmits(['select'])

const q = ref('')
const results = ref([])
const open = ref(false)
const loading = ref(false)
let timer = null
let seq = 0

async function fetchResults() {
  const mine = ++seq
  loading.value = true
  try {
    const { data } = await window.axios.get(route('warehouse.products.search'), {
      params: { q: q.value, warehouse_id: props.warehouseId || undefined },
    })
    if (mine === seq) results.value = data
  } catch (e) {
    if (mine === seq) results.value = []
  } finally {
    if (mine === seq) loading.value = false
  }
}

watch(q, () => {
  clearTimeout(timer)
  if (q.value.trim().length === 0) { results.value = []; return }
  timer = setTimeout(fetchResults, 250)
})
onBeforeUnmount(() => clearTimeout(timer))

// Tunda sedikit agar klik pada hasil sempat terbaca sebelum daftar menutup.
function closeSoon() { setTimeout(() => { open.value = false }, 150) }

function pick(p) {
  emit('select', p)
  q.value = ''
  results.value = []
  open.value = false
}
</script>

<template>
  <div class="relative">
    <input v-model="q" type="text" class="input" :placeholder="placeholder" autocomplete="off"
      @focus="open = true" @blur="closeSoon" />
    <div v-if="open && (results.length || loading)"
      class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-md border border-ink-200 bg-white shadow-lg dark:border-ink-700 dark:bg-ink-900">
      <p v-if="loading && !results.length" class="px-3 py-2 text-sm text-ink-500">Mencari…</p>
      <button v-for="p in results" :key="p.id" type="button" @mousedown.prevent="pick(p)"
        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-ink-50 dark:hover:bg-ink-800">
        <span class="min-w-0">
          <span class="block truncate font-medium">{{ p.name }}</span>
          <span class="font-mono text-xs text-ink-500">{{ p.sku }}</span>
        </span>
        <span v-if="p.available !== undefined" class="shrink-0 text-xs text-ink-500">tersedia {{ p.available }} {{ p.unit }}</span>
      </button>
    </div>
  </div>
</template>
