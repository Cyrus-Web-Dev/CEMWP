<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link } from '@inertiajs/vue3'
import { useFilters } from '@/Composables/useFilters'
import { rupiah, dateTime } from '@/Composables/format'

const props = defineProps({ orders: Object, filters: Object, statuses: Object, paymentLabels: Object })
const f = useFilters('sales.orders.index', {
  q: props.filters.q ?? '', status: props.filters.status ?? '', channel: props.filters.channel ?? '',
  from: props.filters.from ?? '', to: props.filters.to ?? '',
})
const cls = {
  awaiting_payment: 'badge-amber', processing: 'badge-seal', shipped: 'badge-seal', completed: 'badge-green',
  cancelled: 'badge-red', expired: 'badge-slate', needs_review: 'badge-red',
}
</script>

<template>
  <AppLayout>
    <Head title="Pesanan" />
    <template #title>Pesanan</template>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <input v-model="f.q" class="input lg:col-span-2" placeholder="Cari nomor, nama, atau HP pelanggan…" />
      <select v-model="f.status" class="input"><option value="">Semua status</option><option v-for="(l, k) in statuses" :key="k" :value="k">{{ l }}</option></select>
      <select v-model="f.channel" class="input"><option value="">Semua saluran</option><option value="pos">Kasir</option><option value="ecommerce">Online</option></select>
      <div class="flex gap-2"><input v-model="f.from" type="date" class="input" /><input v-model="f.to" type="date" class="input" /></div>
    </div>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Waktu</th><th>Saluran</th><th>Pelanggan</th><th class="text-right">Total</th><th>Pembayaran</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="o in orders.data" :key="o.id">
            <td><Link :href="route('sales.orders.show', o.id)" class="font-mono font-medium hover:underline">{{ o.number }}</Link></td>
            <td class="whitespace-nowrap text-xs text-ink-500">{{ dateTime(o.placed_at) }}</td>
            <td class="text-sm">{{ o.channel === 'pos' ? 'Kasir' : 'Online' }}</td>
            <td class="text-sm">{{ o.customer_name ?? 'Umum' }}<div class="text-xs text-ink-500">{{ o.customer_phone }}</div></td>
            <td class="num text-right">{{ rupiah(o.grand_total) }}</td>
            <td class="text-sm">{{ paymentLabels[o.payment_status] ?? o.payment_status }}</td>
            <td><span :class="cls[o.status] ?? 'badge-slate'">{{ statuses[o.status] ?? o.status }}</span></td>
          </tr>
          <tr v-if="!orders.data.length"><td colspan="7" class="py-10 text-center text-ink-500">Belum ada pesanan yang cocok.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="orders.links" />
  </AppLayout>
</template>
