<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { useFilters } from '@/Composables/useFilters'
import { num, rupiah } from '@/Composables/format'

const props = defineProps({ products: Object, filters: Object, categories: Array })
const { can } = useCan()
const f = useFilters('warehouse.products.index', {
  q: props.filters.q ?? '', category_id: props.filters.category_id ?? '', status: props.filters.status ?? '',
})

function stockState(p) {
  if (!p.track_stock) return null
  const total = Number(p.stock_on_hand ?? 0)
  if (total <= 0) return { text: 'Habis', cls: 'badge-red' }
  if (p.min_stock > 0 && total <= p.min_stock) return { text: 'Menipis', cls: 'badge-amber' }
  return null
}
</script>

<template>
  <AppLayout>
    <Head title="Produk" />
    <template #title>Produk</template>
    <template #actions>
      <Link v-if="can('products.manage')" :href="route('warehouse.products.create')" class="btn-primary btn-sm">Tambah produk</Link>
    </template>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
      <input v-model="f.q" class="input" placeholder="Cari nama, SKU, atau barcode…" />
      <select v-model="f.category_id" class="input">
        <option value="">Semua kategori</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="f.status" class="input">
        <option value="">Semua status</option>
        <option value="active">Aktif</option>
        <option value="inactive">Nonaktif</option>
        <option value="low">Stok menipis</option>
        <option value="out">Stok habis</option>
      </select>
    </div>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr><th>Produk</th><th>Kategori</th><th class="text-right">Stok fisik</th><th class="text-right">Tersedia</th><th class="text-right">Minimum</th><th class="text-right">Harga jual</th></tr>
        </thead>
        <tbody>
          <tr v-for="p in products.data" :key="p.id">
            <td>
              <Link :href="route('warehouse.products.show', p.id)" class="font-medium hover:underline">{{ p.name }}</Link>
              <span v-if="!p.is_active" class="badge-slate ml-2">Nonaktif</span>
              <div class="font-mono text-xs text-ink-500">{{ p.sku }}<span v-if="p.barcode"> · {{ p.barcode }}</span></div>
            </td>
            <td class="text-sm">{{ p.category?.name ?? '-' }}</td>
            <template v-if="p.track_stock">
              <td class="num text-right">
                {{ num(p.stock_on_hand ?? 0) }} <span class="text-xs text-ink-500">{{ p.unit }}</span>
                <span v-if="stockState(p)" :class="stockState(p).cls" class="ml-2">{{ stockState(p).text }}</span>
              </td>
              <td class="num text-right">{{ num((p.stock_on_hand ?? 0) - (p.stock_reserved ?? 0)) }}</td>
              <td class="num text-right text-ink-500">{{ num(p.min_stock) }}</td>
            </template>
            <td v-else colspan="3" class="text-center text-xs text-ink-500">stok tidak dilacak</td>
            <td class="num text-right">{{ rupiah(p.selling_price) }}</td>
          </tr>
          <tr v-if="!products.data.length">
            <td colspan="6" class="py-10 text-center text-ink-500">
              Belum ada produk yang cocok.
              <Link v-if="can('products.manage')" :href="route('warehouse.products.create')" class="font-medium text-seal-600 hover:underline">Tambah produk pertama</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="products.links" />
  </AppLayout>
</template>
