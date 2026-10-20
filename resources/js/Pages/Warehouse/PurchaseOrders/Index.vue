<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, Link } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { useFilters } from '@/Composables/useFilters'
import { rupiah, dateOnly } from '@/Composables/format'

const props = defineProps({ orders: Object, filters: Object, statuses: Object })
const { can } = useCan()
const f = useFilters('warehouse.purchase-orders.index', { q: props.filters.q ?? '', status: props.filters.status ?? '' })
</script>

<template>
  <AppLayout>
    <Head title="Purchase order" />
    <template #title>Purchase order</template>
    <template #actions>
      <Link v-if="can('purchasing.manage')" :href="route('warehouse.purchase-orders.create')" class="btn-primary btn-sm">Buat PO</Link>
    </template>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
      <input v-model="f.q" class="input sm:col-span-2" placeholder="Cari nomor PO atau supplier…" />
      <select v-model="f.status" class="input"><option value="">Semua status</option><option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option></select>
    </div>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Supplier</th><th>Tujuan</th><th>Dipesan</th><th>Estimasi tiba</th><th class="text-right">Item</th><th class="text-right">Total</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="o in orders.data" :key="o.id">
            <td><Link :href="route('warehouse.purchase-orders.show', o.id)" class="font-mono font-medium hover:underline">{{ o.number }}</Link></td>
            <td>{{ o.supplier }}</td>
            <td class="text-sm">{{ o.warehouse }}</td>
            <td class="text-sm">{{ dateOnly(o.ordered_at) }}</td>
            <td class="text-sm">{{ dateOnly(o.expected_at) }}</td>
            <td class="num text-right">{{ o.lines }}</td>
            <td class="num text-right">{{ rupiah(o.total) }}</td>
            <td><StatusBadge :status="o.status" :labels="statuses" /></td>
          </tr>
          <tr v-if="!orders.data.length"><td colspan="8" class="py-10 text-center text-ink-500">Belum ada PO.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="orders.links" />
  </AppLayout>
</template>
