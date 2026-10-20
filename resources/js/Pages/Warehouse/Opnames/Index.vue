<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { dateTime } from '@/Composables/format'

const props = defineProps({ opnames: Object, warehouses: Array, statuses: Object })
const { can } = useCan()
const form = useForm({ warehouse_id: props.warehouses[0]?.id ?? '', notes: '' })
function start() { form.post(route('warehouse.opnames.store')) }
</script>

<template>
  <AppLayout>
    <Head title="Stok opname" />
    <template #title>Stok opname</template>

    <form v-if="can('opname.manage')" class="card mb-6 max-w-3xl" @submit.prevent="start">
      <div class="card-header"><h2 class="text-sm font-semibold">Mulai opname baru</h2></div>
      <div class="card-body grid gap-4 md:grid-cols-4">
        <div class="md:col-span-1">
          <label class="label">Lokasi</label>
          <select v-model="form.warehouse_id" class="input" required><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select>
          <p v-if="form.errors.warehouse_id" class="error-text">{{ form.errors.warehouse_id }}</p>
        </div>
        <div class="md:col-span-2"><label class="label">Catatan</label><input v-model="form.notes" class="input" placeholder="Opsional, mis. opname akhir bulan" /></div>
        <div class="md:self-end"><button class="btn-accent w-full" :disabled="form.processing">Mulai hitung</button></div>
      </div>
    </form>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Lokasi</th><th>Dimulai</th><th>Progres hitung</th><th>Oleh</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="o in opnames.data" :key="o.id">
            <td><Link :href="route('warehouse.opnames.show', o.id)" class="font-mono font-medium hover:underline">{{ o.number }}</Link></td>
            <td class="text-sm">{{ o.warehouse?.name }}</td>
            <td class="text-sm text-ink-500">{{ dateTime(o.created_at) }}</td>
            <td class="num text-sm">{{ o.counted_count }} / {{ o.items_count }}</td>
            <td class="text-sm text-ink-500">{{ o.creator?.name ?? '-' }}</td>
            <td><StatusBadge :status="o.status" :labels="statuses" /></td>
          </tr>
          <tr v-if="!opnames.data.length"><td colspan="6" class="py-10 text-center text-ink-500">Belum ada stok opname.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="opnames.links" />
  </AppLayout>
</template>
