<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, Link } from '@inertiajs/vue3'
import { useFilters } from '@/Composables/useFilters'
import { num, dateTime } from '@/Composables/format'

const props = defineProps({ fulfillments: Object, filters: Object, counts: Object, statuses: Object })
const f = useFilters('warehouse.fulfillments.index', { q: props.filters.q ?? '', status: props.filters.status ?? '' })

const tabs = [{ key: '', label: 'Semua' }, ...Object.entries(props.statuses).map(([key, label]) => ({ key, label }))]
const countOf = (key) => (key === '' ? Object.values(props.counts).reduce((s, n) => s + Number(n), 0) : Number(props.counts[key] ?? 0))
const channelLabel = { ecommerce: 'Online', pos: 'Kasir', manual: 'Manual' }
</script>

<template>
  <AppLayout>
    <Head title="Pengiriman pesanan" />
    <template #title>Pengiriman pesanan</template>

    <div class="mb-4 flex flex-wrap gap-1.5">
      <button v-for="t in tabs" :key="t.key" type="button" class="rounded-full border px-3 py-1 text-sm transition-colors"
        :class="f.status === t.key ? 'border-seal-500 bg-seal-500 font-medium text-ink-950' : 'border-ink-200 text-ink-600 hover:bg-ink-100 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800'"
        @click="f.status = t.key">
        {{ t.label }} <span class="num text-xs opacity-70">{{ countOf(t.key) }}</span>
      </button>
    </div>
    <input v-model="f.q" class="input mb-4 max-w-sm" placeholder="Cari nomor tugas, nomor pesanan, atau penerima…" />

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Tugas</th><th>Pesanan</th><th>Penerima</th><th>Saluran</th><th class="text-right">Unit</th><th>Masuk</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="x in fulfillments.data" :key="x.id">
            <td>
              <Link :href="route('warehouse.fulfillments.show', x.id)" class="font-mono font-medium hover:underline">{{ x.number }}</Link>
              <span v-if="x.priority === 'urgent'" class="badge-red ml-2">Mendesak</span>
            </td>
            <td class="font-mono text-xs">{{ x.source_number ?? '-' }}</td>
            <td class="text-sm">{{ x.recipient_name ?? '-' }}</td>
            <td class="text-sm">{{ channelLabel[x.channel] ?? x.channel }}</td>
            <td class="num text-right">{{ num(x.total_units ?? 0) }}</td>
            <td class="text-sm text-ink-500">{{ dateTime(x.created_at) }}</td>
            <td><StatusBadge :status="x.status" :labels="statuses" /></td>
          </tr>
          <tr v-if="!fulfillments.data.length"><td colspan="7" class="py-10 text-center text-ink-500">Belum ada tugas pengiriman. Tugas muncul otomatis saat pesanan online masuk.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="fulfillments.links" />
  </AppLayout>
</template>
