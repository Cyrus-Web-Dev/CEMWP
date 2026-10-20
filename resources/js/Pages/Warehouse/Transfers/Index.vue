<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, Link } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { useFilters } from '@/Composables/useFilters'
import { num, dateTime } from '@/Composables/format'

const props = defineProps({ transfers: Object, filters: Object, statuses: Object })
const { can } = useCan()
const f = useFilters('warehouse.transfers.index', { status: props.filters.status ?? '' })
</script>

<template>
  <AppLayout>
    <Head title="Transfer lokasi" />
    <template #title>Transfer antar lokasi</template>
    <template #actions>
      <Link v-if="can('transfers.manage')" :href="route('warehouse.transfers.create')" class="btn-primary btn-sm">Buat transfer</Link>
    </template>

    <div class="mb-4 max-w-xs">
      <select v-model="f.status" class="input"><option value="">Semua status</option><option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option></select>
    </div>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Dari</th><th>Ke</th><th class="text-right">Unit</th><th>Dikirim</th><th>Diterima</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="t in transfers.data" :key="t.id">
            <td><Link :href="route('warehouse.transfers.show', t.id)" class="font-mono font-medium hover:underline">{{ t.number }}</Link></td>
            <td class="text-sm">{{ t.from?.name }}</td>
            <td class="text-sm">{{ t.to?.name }}</td>
            <td class="num text-right">{{ num(t.total_units ?? 0) }}</td>
            <td class="text-sm text-ink-500">{{ t.shipped_at ? dateTime(t.shipped_at) : '-' }}</td>
            <td class="text-sm text-ink-500">{{ t.received_at ? dateTime(t.received_at) : '-' }}</td>
            <td><StatusBadge :status="t.status" :labels="statuses" /></td>
          </tr>
          <tr v-if="!transfers.data.length"><td colspan="7" class="py-10 text-center text-ink-500">Belum ada transfer.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="transfers.links" />
  </AppLayout>
</template>
