<script setup>
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { useFilters } from '@/Composables/useFilters'
import { num, rupiah, dateOnly } from '@/Composables/format'

const props = defineProps({ filters: Object, report: Object })
const f = useFilters('sales.reports.index', { from: props.filters.from, to: props.filters.to, channel: props.filters.channel ?? '' })

const r = props.report
const maxDay = computed(() => Math.max(1, ...r.daily.map((d) => d.total)))
const channelName = { pos: 'Kasir', ecommerce: 'Online' }
</script>

<template>
  <AppLayout>
    <Head title="Laporan penjualan" />
    <template #title>Laporan penjualan</template>

    <div class="mb-6 flex flex-wrap items-end gap-3">
      <div><label class="label">Dari</label><input v-model="f.from" type="date" class="input" /></div>
      <div><label class="label">Sampai</label><input v-model="f.to" type="date" class="input" /></div>
      <div><label class="label">Saluran</label><select v-model="f.channel" class="input"><option value="">Semua</option><option value="pos">Kasir</option><option value="ecommerce">Online</option></select></div>
    </div>

    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="card card-body"><p class="text-sm text-ink-500">Penjualan kotor</p><p class="num text-2xl font-semibold">{{ rupiah(r.gross) }}</p><p class="text-xs text-ink-500">{{ num(r.orders) }} pesanan · rata-rata {{ rupiah(r.average_order) }}</p></div>
      <div class="card card-body"><p class="text-sm text-ink-500">Retur / refund</p><p class="num text-2xl font-semibold text-red-600">{{ rupiah(r.returns) }}</p></div>
      <div class="card card-body"><p class="text-sm text-ink-500">Penjualan bersih</p><p class="num text-2xl font-semibold">{{ rupiah(r.net) }}</p></div>
      <div class="card card-body"><p class="text-sm text-ink-500">Diskon · ongkir · PPN</p><p class="num text-lg font-semibold">{{ rupiah(r.discount) }}</p><p class="text-xs text-ink-500">ongkir {{ rupiah(r.shipping) }} · PPN {{ rupiah(r.tax) }}</p></div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="card lg:col-span-2">
        <div class="card-header"><h2 class="text-sm font-semibold">Penjualan per hari</h2></div>
        <div class="card-body">
          <p v-if="!r.daily.length" class="text-sm text-ink-500">Tidak ada penjualan pada periode ini.</p>
          <ul v-else class="space-y-1.5">
            <li v-for="d in r.daily" :key="d.day" class="flex items-center gap-3 text-sm">
              <span class="w-24 shrink-0 text-xs text-ink-500">{{ dateOnly(d.day) }}</span>
              <span class="h-5 flex-1 overflow-hidden rounded bg-ink-100 dark:bg-ink-800"><span class="block h-full bg-seal-500" :style="{ width: (d.total / maxDay * 100) + '%' }"></span></span>
              <span class="num w-28 text-right">{{ rupiah(d.total) }}</span>
              <span class="w-12 text-right text-xs text-ink-500">{{ d.orders }}x</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="space-y-6">
        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Per saluran</h2></div>
          <table class="data-table"><tbody>
            <tr v-for="c in r.by_channel" :key="c.channel"><td>{{ channelName[c.channel] ?? c.channel }} <span class="text-xs text-ink-500">({{ c.orders }})</span></td><td class="num text-right">{{ rupiah(c.total) }}</td></tr>
            <tr v-if="!r.by_channel.length"><td class="text-ink-500">-</td></tr>
          </tbody></table>
        </div>
        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Per metode bayar</h2></div>
          <table class="data-table"><tbody>
            <tr v-for="m in r.by_method" :key="m.name"><td>{{ m.name }} <span class="text-xs text-ink-500">({{ m.count }})</span></td><td class="num text-right">{{ rupiah(m.total) }}</td></tr>
            <tr v-if="!r.by_method.length"><td class="text-ink-500">-</td></tr>
          </tbody></table>
        </div>
      </div>
    </div>

    <div class="card mt-6 overflow-x-auto">
      <div class="card-header"><h2 class="text-sm font-semibold">10 produk terlaris (berdasarkan omzet)</h2></div>
      <table class="data-table">
        <thead><tr><th>Produk</th><th class="text-right">Terjual</th><th class="text-right">Omzet</th><th class="text-right">Margin kotor</th></tr></thead>
        <tbody>
          <tr v-for="p in r.top_products" :key="p.sku"><td>{{ p.name }}<div class="font-mono text-xs text-ink-500">{{ p.sku }}</div></td><td class="num text-right">{{ num(p.qty) }}</td><td class="num text-right">{{ rupiah(p.revenue) }}</td><td class="num text-right" :class="p.margin < 0 ? 'text-red-600' : ''">{{ rupiah(p.margin) }}</td></tr>
          <tr v-if="!r.top_products.length"><td colspan="4" class="py-6 text-center text-ink-500">Belum ada data.</td></tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
