<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ logs: Object, filters: Object })
const q = ref(props.filters.q ?? '')

function search() {
  router.get(route('activity-log.index'), { q: q.value || undefined }, { preserveState: true, replace: true })
}
</script>

<template>
  <AppLayout>
    <Head title="Log aktivitas" />
    <template #title>Log aktivitas</template>

    <form class="mb-4 flex max-w-md gap-2" @submit.prevent="search">
      <input v-model="q" class="input" placeholder="Cari aksi atau deskripsi…" />
      <button class="btn-secondary">Cari</button>
    </form>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th><th>IP</th></tr></thead>
        <tbody>
          <tr v-for="l in logs.data" :key="l.id">
            <td class="whitespace-nowrap text-xs text-ink-500">{{ l.created_at }}</td>
            <td>{{ l.user?.name ?? 'Sistem' }}</td>
            <td><span class="badge-slate font-mono">{{ l.action }}</span></td>
            <td>{{ l.description }}</td>
            <td class="font-mono text-xs text-ink-500">{{ l.ip_address }}</td>
          </tr>
          <tr v-if="!logs.data.length"><td colspan="5" class="text-center text-ink-500">Belum ada catatan.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="logs.links" />
  </AppLayout>
</template>
