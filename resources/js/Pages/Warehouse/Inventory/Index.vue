<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ProductSearch from '@/Components/ProductSearch.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { useFilters } from '@/Composables/useFilters'
import { num, rupiah } from '@/Composables/format'

const props = defineProps({ inventories: Object, filters: Object, warehouses: Array, totals: Object, reasons: Object })
const { can } = useCan()
const f = useFilters('warehouse.inventory.index', {
  q: props.filters.q ?? '', warehouse_id: props.filters.warehouse_id ?? '', status: props.filters.status ?? '',
})

const exportUrl = computed(() => {
  const params = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== ''))
  return route('warehouse.inventory.export', params)
})

// --- Penyesuaian stok ---
const showAdjust = ref(false)
const picked = ref(null) // { id, name, sku, unit }
const adjust = useForm({ product_id: null, warehouse_id: '', direction: 'out', quantity: 1, reason: 'damaged', note: '' })

function openAdjust(row = null) {
  showAdjust.value = true
  if (row) {
    picked.value = { id: row.product.id, name: row.product.name, sku: row.product.sku, unit: row.product.unit }
    adjust.product_id = row.product.id
    adjust.warehouse_id = row.warehouse_id
  }
}
function pick(p) { picked.value = p; adjust.product_id = p.id }
function submitAdjust() {
  adjust.post(route('warehouse.inventory.adjust'), {
    preserveScroll: true,
    onSuccess: () => { adjust.reset('quantity', 'note'); picked.value = null; adjust.product_id = null; showAdjust.value = false },
  })
}

function badge(row) {
  if (row.qty_on_hand <= 0) return { t: 'Habis', c: 'badge-red' }
  if (row.product.min_stock > 0 && row.qty_on_hand <= row.product.min_stock) return { t: 'Menipis', c: 'badge-amber' }
  return null
}
</script>

<template>
  <AppLayout>
    <Head title="Stok" />
    <template #title>Stok per lokasi</template>
    <template #actions>
      <a v-if="can('inventory.export')" :href="exportUrl" class="btn-secondary btn-sm">Unduh CSV</a>
      <button v-if="can('inventory.adjust')" class="btn-primary btn-sm" @click="openAdjust()">Penyesuaian stok</button>
    </template>

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
      <div class="card card-body"><p class="text-sm text-ink-500">Unit (sesuai filter)</p><p class="num text-2xl font-semibold">{{ num(totals.units) }}</p></div>
      <div class="card card-body"><p class="text-sm text-ink-500">Dicadangkan pesanan</p><p class="num text-2xl font-semibold">{{ num(totals.reserved) }}</p></div>
      <div class="card card-body"><p class="text-sm text-ink-500">Nilai persediaan</p><p class="num text-2xl font-semibold">{{ rupiah(totals.value) }}</p></div>
    </div>

    <form v-if="showAdjust" class="card mb-6" @submit.prevent="submitAdjust">
      <div class="card-header"><h2 class="text-sm font-semibold">Penyesuaian stok manual</h2><button type="button" class="btn-ghost btn-sm" @click="showAdjust = false">Tutup</button></div>
      <div class="card-body grid gap-4 md:grid-cols-6">
        <div class="md:col-span-3">
          <label class="label">Produk</label>
          <div v-if="picked" class="flex items-center justify-between rounded-md border border-ink-200 px-3 py-2 text-sm dark:border-ink-700">
            <span><span class="font-medium">{{ picked.name }}</span> <span class="font-mono text-xs text-ink-500">{{ picked.sku }}</span></span>
            <button type="button" class="text-ink-400 hover:text-red-600" @click="picked = null; adjust.product_id = null">&times;</button>
          </div>
          <ProductSearch v-else :warehouse-id="adjust.warehouse_id || null" @select="pick" />
          <p v-if="adjust.errors.product_id" class="error-text">{{ adjust.errors.product_id }}</p>
        </div>
        <div class="md:col-span-3">
          <label class="label">Lokasi</label>
          <select v-model="adjust.warehouse_id" class="input" required>
            <option value="" disabled>Pilih lokasi</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})</option>
          </select>
          <p v-if="adjust.errors.warehouse_id" class="error-text">{{ adjust.errors.warehouse_id }}</p>
        </div>
        <div class="md:col-span-2">
          <label class="label">Arah</label>
          <select v-model="adjust.direction" class="input"><option value="out">Kurangi stok</option><option value="in">Tambah stok</option></select>
        </div>
        <div class="md:col-span-1">
          <label class="label">Jumlah</label>
          <input v-model.number="adjust.quantity" type="number" min="1" step="1" class="input text-right" required />
          <p v-if="adjust.errors.quantity" class="error-text">{{ adjust.errors.quantity }}</p>
        </div>
        <div class="md:col-span-3">
          <label class="label">Alasan</label>
          <select v-model="adjust.reason" class="input"><option v-for="(label, key) in reasons" :key="key" :value="key">{{ label }}</option></select>
        </div>
        <div class="md:col-span-5">
          <label class="label">Catatan</label>
          <input v-model="adjust.note" class="input" placeholder="Opsional, mis. kardus basah saat hujan" />
        </div>
        <div class="md:col-span-1 md:self-end"><button class="btn-accent w-full" :disabled="adjust.processing || !adjust.product_id">Catat</button></div>
      </div>
    </form>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
      <input v-model="f.q" class="input" placeholder="Cari nama, SKU, atau barcode…" />
      <select v-model="f.warehouse_id" class="input"><option value="">Semua lokasi</option><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select>
      <select v-model="f.status" class="input">
        <option value="">Semua kondisi</option><option value="low">Menipis</option><option value="out">Habis</option><option value="reserved">Ada reservasi</option>
      </select>
    </div>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Produk</th><th>Lokasi</th><th class="text-right">Fisik</th><th class="text-right">Dicadangkan</th><th class="text-right">Tersedia</th><th class="text-right">Nilai</th><th v-if="can('inventory.adjust')"></th></tr></thead>
        <tbody>
          <tr v-for="r in inventories.data" :key="r.id">
            <td>
              <Link :href="route('warehouse.products.show', r.product.id)" class="font-medium hover:underline">{{ r.product.name }}</Link>
              <span v-if="badge(r)" :class="badge(r).c" class="ml-2">{{ badge(r).t }}</span>
              <div class="font-mono text-xs text-ink-500">{{ r.product.sku }}</div>
            </td>
            <td class="text-sm">{{ r.warehouse.name }}</td>
            <td class="num text-right">{{ num(r.qty_on_hand) }} <span class="text-xs text-ink-500">{{ r.product.unit }}</span></td>
            <td class="num text-right text-ink-500">{{ num(r.qty_reserved) }}</td>
            <td class="num text-right font-semibold">{{ num(r.qty_on_hand - r.qty_reserved) }}</td>
            <td class="num text-right">{{ rupiah(r.qty_on_hand * Number(r.product.cost_price)) }}</td>
            <td v-if="can('inventory.adjust')" class="text-right"><button class="btn-ghost btn-sm" @click="openAdjust(r)">Sesuaikan</button></td>
          </tr>
          <tr v-if="!inventories.data.length"><td colspan="7" class="py-10 text-center text-ink-500">Tidak ada data stok yang cocok. Stok pertama dimasukkan lewat Penerimaan barang.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="inventories.links" />
  </AppLayout>
</template>
