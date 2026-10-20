<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { num, rupiah, dateTime } from '@/Composables/format'

const props = defineProps({
  warehouse: { type: Object, default: null },
  sales: { type: Object, default: null },
  canPos: Boolean,
})

const s = props.warehouse?.summary
const todo = s ? [
  { label: 'Pesanan menunggu diambil', n: s.fulfillments_pending, href: route('warehouse.fulfillments.index', { status: 'pending' }) },
  { label: 'Pesanan sedang disiapkan', n: s.fulfillments_active, href: route('warehouse.fulfillments.index') },
  { label: 'Transfer dalam perjalanan', n: s.transfers_in_transit, href: route('warehouse.transfers.index', { status: 'in_transit' }) },
  { label: 'PO menunggu barang', n: s.purchase_orders_open, href: route('warehouse.purchase-orders.index') },
] : []
</script>

<template>
  <AppLayout>
    <Head title="Dashboard" />
    <template #title>Dashboard</template>

    <section v-if="canPos || sales" class="mb-6 grid gap-6 lg:grid-cols-3">
      <Link v-if="canPos" :href="route('sales.pos.index')" class="card card-body flex items-center justify-between bg-seal-500 text-ink-950 hover:bg-seal-400">
        <span>
          <span class="block font-serif text-xl font-semibold">Buka kasir</span>
          <span class="text-sm opacity-80">Mulai transaksi penjualan</span>
        </span>
        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
      </Link>
      <template v-if="sales">
        <div class="card card-body">
          <p class="text-sm text-ink-500 dark:text-ink-400">Penjualan hari ini</p>
          <p class="num mt-1 text-2xl font-semibold">{{ rupiah(sales.total) }}</p>
          <p class="mt-1 text-xs text-ink-500">{{ num(sales.orders) }} pesanan · kasir {{ rupiah(sales.pos_total) }} · online {{ rupiah(sales.online_total) }}</p>
        </div>
        <div class="card">
          <ul class="divide-y divide-ink-100 dark:divide-ink-800">
            <li><Link :href="route('sales.orders.index', { status: 'awaiting_payment' })" class="flex items-center justify-between px-5 py-3 text-sm hover:bg-ink-50 dark:hover:bg-ink-800/40"><span>Menunggu pembayaran</span><span class="num font-semibold">{{ sales.awaiting_payment }}</span></Link></li>
            <li><Link :href="route('sales.orders.index', { status: 'needs_review' })" class="flex items-center justify-between px-5 py-3 text-sm hover:bg-ink-50 dark:hover:bg-ink-800/40"><span>Perlu ditinjau</span><span class="num rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="sales.needs_review ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-ink-100 text-ink-500 dark:bg-ink-800'">{{ sales.needs_review }}</span></Link></li>
          </ul>
        </div>
      </template>
    </section>

    <div v-if="!warehouse && !sales && !canPos" class="card card-body text-sm text-ink-600 dark:text-ink-300">
      Akunmu belum punya akses ke data apa pun. Hubungi Admin untuk mengatur role.
    </div>

    <template v-else-if="warehouse">
      <section class="grid gap-6 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
          <p class="text-sm text-ink-500 dark:text-ink-400">Nilai persediaan (harga pokok rata-rata)</p>
          <p class="num mt-1 text-4xl font-semibold tracking-tight">{{ rupiah(s.stock_value) }}</p>
          <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-ink-100 pt-4 text-sm sm:grid-cols-4 dark:border-ink-800">
            <div><dt class="text-ink-500">Produk aktif</dt><dd class="num text-lg font-semibold">{{ num(s.products) }}</dd></div>
            <div><dt class="text-ink-500">Unit di rak</dt><dd class="num text-lg font-semibold">{{ num(s.units_on_hand) }}</dd></div>
            <div><dt class="text-ink-500">Dicadangkan</dt><dd class="num text-lg font-semibold">{{ num(s.units_reserved) }}</dd></div>
            <div>
              <dt class="text-ink-500">Stok menipis / habis</dt>
              <dd class="num text-lg font-semibold" :class="s.low_stock + s.out_of_stock ? 'text-red-600 dark:text-red-400' : ''">{{ num(s.low_stock) }} / {{ num(s.out_of_stock) }}</dd>
            </div>
          </dl>
        </div>

        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Perlu tindakan</h2></div>
          <ul class="divide-y divide-ink-100 dark:divide-ink-800">
            <li v-for="t in todo" :key="t.label">
              <Link :href="t.href" class="flex items-center justify-between px-5 py-3 text-sm hover:bg-ink-50 dark:hover:bg-ink-800/40">
                <span>{{ t.label }}</span>
                <span class="num rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="t.n ? 'bg-seal-100 text-seal-800 dark:bg-seal-900/40 dark:text-seal-300' : 'bg-ink-100 text-ink-500 dark:bg-ink-800'">{{ t.n }}</span>
              </Link>
            </li>
          </ul>
        </div>
      </section>

      <section class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
          <div class="card-header">
            <h2 class="text-sm font-semibold">Perlu diisi ulang</h2>
            <Link :href="route('warehouse.products.index', { status: 'low' })" class="text-xs font-medium text-seal-600 hover:underline dark:text-seal-400">Lihat semua</Link>
          </div>
          <div v-if="warehouse.lowStock.length === 0" class="card-body text-sm text-ink-500">Semua produk berada di atas titik pesan ulang.</div>
          <table v-else class="data-table">
            <thead><tr><th>Produk</th><th class="text-right">Stok</th><th class="text-right">Minimum</th></tr></thead>
            <tbody>
              <tr v-for="p in warehouse.lowStock" :key="p.id">
                <td><Link :href="route('warehouse.products.show', p.id)" class="font-medium hover:underline">{{ p.name }}</Link><div class="font-mono text-xs text-ink-500">{{ p.sku }}</div></td>
                <td class="num text-right font-semibold text-red-600 dark:text-red-400">{{ num(p.stock_on_hand ?? 0) }}</td>
                <td class="num text-right text-ink-500">{{ num(p.min_stock) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="text-sm font-semibold">Mutasi stok terbaru</h2>
            <Link :href="route('warehouse.inventory.movements')" class="text-xs font-medium text-seal-600 hover:underline dark:text-seal-400">Riwayat lengkap</Link>
          </div>
          <div v-if="warehouse.recentMovements.length === 0" class="card-body text-sm text-ink-500">Belum ada mutasi stok.</div>
          <table v-else class="data-table">
            <thead><tr><th>Waktu</th><th>Produk</th><th class="text-right">Jumlah</th></tr></thead>
            <tbody>
              <tr v-for="m in warehouse.recentMovements" :key="m.id">
                <td class="whitespace-nowrap text-xs text-ink-500">{{ dateTime(m.created_at) }}</td>
                <td>{{ m.product?.name }}<div class="text-xs text-ink-500">{{ m.warehouse?.code }}</div></td>
                <td class="num text-right font-semibold" :class="m.quantity > 0 ? 'text-emerald-600' : 'text-red-600'">{{ m.quantity > 0 ? '+' : '' }}{{ num(m.quantity) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </AppLayout>
</template>
