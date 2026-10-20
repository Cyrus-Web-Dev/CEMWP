<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { num, rupiah, dateTime } from '@/Composables/format'

const props = defineProps({ product: Object, inventories: Array, movements: Array })
const { can } = useCan()

const onHand = props.inventories.reduce((s, i) => s + i.qty_on_hand, 0)
const reserved = props.inventories.reduce((s, i) => s + i.qty_reserved, 0)

function destroy() {
  if (confirm(`Hapus produk "${props.product.name}"? Produk yang sudah punya riwayat tetap tersimpan untuk audit.`)) {
    router.delete(route('warehouse.products.destroy', props.product.id))
  }
}
const typeLabel = { receipt: 'Penerimaan', sale: 'Penjualan', sale_return: 'Retur', transfer_out: 'Transfer keluar', transfer_in: 'Transfer masuk', adjustment: 'Penyesuaian', opname: 'Opname' }
</script>

<template>
  <AppLayout>
    <Head :title="product.name" />
    <template #title>{{ product.name }}</template>
    <template #actions>
      <Link v-if="can('products.manage')" :href="route('warehouse.products.edit', product.id)" class="btn-secondary btn-sm">Ubah</Link>
      <button v-if="can('products.manage')" class="btn-ghost btn-sm text-red-600" @click="destroy">Hapus</button>
    </template>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="card lg:col-span-1">
        <div class="card-header"><h2 class="text-sm font-semibold">Detail</h2><span v-if="!product.is_active" class="badge-slate">Nonaktif</span></div>
        <dl class="card-body space-y-3 text-sm">
          <div><dt class="text-ink-500">SKU</dt><dd class="font-mono">{{ product.sku }}</dd></div>
          <div v-if="product.barcode"><dt class="text-ink-500">Barcode</dt><dd class="font-mono">{{ product.barcode }}</dd></div>
          <div><dt class="text-ink-500">Kategori</dt><dd>{{ product.category?.name ?? '-' }}</dd></div>
          <div><dt class="text-ink-500">Harga jual</dt><dd class="num">{{ rupiah(product.selling_price) }}</dd></div>
          <div><dt class="text-ink-500">Harga pokok (rata-rata)</dt><dd class="num">{{ rupiah(product.cost_price) }}</dd></div>
          <div><dt class="text-ink-500">Titik pesan ulang</dt><dd class="num">{{ num(product.min_stock) }} {{ product.unit }}</dd></div>
          <div><dt class="text-ink-500">Berat</dt><dd class="num">{{ num(product.weight_grams) }} g</dd></div>
          <div><dt class="text-ink-500">Dijual online</dt><dd>{{ product.is_sellable_online ? 'Ya' : 'Tidak' }}</dd></div>
          <div v-if="product.description"><dt class="text-ink-500">Deskripsi</dt><dd class="whitespace-pre-line">{{ product.description }}</dd></div>
        </dl>
      </div>

      <div class="space-y-6 lg:col-span-2">
        <div class="card">
          <div class="card-header">
            <h2 class="text-sm font-semibold">Stok per lokasi</h2>
            <span v-if="product.track_stock" class="num text-sm text-ink-500">Total {{ num(onHand) }} · tersedia {{ num(onHand - reserved) }} {{ product.unit }}</span>
          </div>
          <div v-if="!product.track_stock" class="card-body text-sm text-ink-500">Stok produk ini tidak dilacak.</div>
          <div v-else-if="!inventories.length" class="card-body text-sm text-ink-500">
            Belum ada stok. Masukkan lewat
            <Link :href="route('warehouse.receipts.create')" class="font-medium text-seal-600 hover:underline">Penerimaan barang</Link>.
          </div>
          <table v-else class="data-table">
            <thead><tr><th>Lokasi</th><th class="text-right">Fisik</th><th class="text-right">Dicadangkan</th><th class="text-right">Tersedia</th></tr></thead>
            <tbody>
              <tr v-for="i in inventories" :key="i.id">
                <td>{{ i.warehouse.name }} <span class="font-mono text-xs text-ink-500">{{ i.warehouse.code }}</span></td>
                <td class="num text-right">{{ num(i.qty_on_hand) }}</td>
                <td class="num text-right text-ink-500">{{ num(i.qty_reserved) }}</td>
                <td class="num text-right font-semibold">{{ num(i.qty_on_hand - i.qty_reserved) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">25 mutasi terakhir</h2></div>
          <div v-if="!movements.length" class="card-body text-sm text-ink-500">Belum ada mutasi.</div>
          <div v-else class="overflow-x-auto">
            <table class="data-table">
              <thead><tr><th>Waktu</th><th>Jenis</th><th>Lokasi</th><th class="text-right">Jumlah</th><th class="text-right">Saldo</th><th>Referensi</th></tr></thead>
              <tbody>
                <tr v-for="m in movements" :key="m.id">
                  <td class="whitespace-nowrap text-xs text-ink-500">{{ dateTime(m.created_at) }}</td>
                  <td>{{ typeLabel[m.type] ?? m.type }}</td>
                  <td class="text-sm">{{ m.warehouse?.code }}</td>
                  <td class="num text-right font-semibold" :class="m.quantity > 0 ? 'text-emerald-600' : 'text-red-600'">{{ m.quantity > 0 ? '+' : '' }}{{ num(m.quantity) }}</td>
                  <td class="num text-right text-ink-500">{{ num(m.qty_after) }}</td>
                  <td class="font-mono text-xs text-ink-500">{{ m.reference_number ?? '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
