<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { num, dateTime } from '@/Composables/format'

defineProps({ receipts: Object })
const { can } = useCan()
</script>

<template>
  <AppLayout>
    <Head title="Penerimaan barang" />
    <template #title>Penerimaan barang</template>
    <template #actions>
      <Link v-if="can('purchasing.receive')" :href="route('warehouse.receipts.create')" class="btn-primary btn-sm">Terima tanpa PO</Link>
    </template>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Waktu</th><th>Supplier</th><th>PO</th><th>Lokasi</th><th class="text-right">Total unit</th></tr></thead>
        <tbody>
          <tr v-for="r in receipts.data" :key="r.id">
            <td><Link :href="route('warehouse.receipts.show', r.id)" class="font-mono font-medium hover:underline">{{ r.number }}</Link></td>
            <td class="text-sm">{{ dateTime(r.received_at) }}</td>
            <td class="text-sm">{{ r.supplier?.name ?? '-' }}</td>
            <td class="font-mono text-xs">{{ r.purchase_order?.number ?? 'Tanpa PO' }}</td>
            <td class="text-sm">{{ r.warehouse?.name }}</td>
            <td class="num text-right">{{ num(r.total_units ?? 0) }}</td>
          </tr>
          <tr v-if="!receipts.data.length"><td colspan="6" class="py-10 text-center text-ink-500">Belum ada penerimaan barang.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="receipts.links" />
  </AppLayout>
</template>
