<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head } from '@inertiajs/vue3'
import { useFilters } from '@/Composables/useFilters'
import { num, dateTime } from '@/Composables/format'

const props = defineProps({ movements: Object, filters: Object, warehouses: Array, types: Object })
const f = useFilters('warehouse.inventory.movements', {
  q: props.filters.q ?? '', warehouse_id: props.filters.warehouse_id ?? '', type: props.filters.type ?? '',
  from: props.filters.from ?? '', to: props.filters.to ?? '',
})
const reasonLabel = { damaged: 'Rusak', lost: 'Hilang', expired: 'Kedaluwarsa', found: 'Ditemukan', correction: 'Koreksi data', sample: 'Sampel / promosi', other: 'Lainnya', opname: 'Hasil opname', transfer_cancelled: 'Transfer dibatalkan' }
</script>

<template>
  <AppLayout>
    <Head title="Riwayat mutasi" />
    <template #title>Riwayat mutasi stok</template>

    <p class="mb-4 max-w-3xl text-sm text-ink-600 dark:text-ink-300">
      Buku besar stok. Setiap perubahan stok tercatat di sini dan tidak bisa diubah atau dihapus; koreksi dilakukan dengan mutasi baru.
    </p>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <input v-model="f.q" class="input lg:col-span-2" placeholder="Cari produk atau nomor dokumen…" />
      <select v-model="f.warehouse_id" class="input"><option value="">Semua lokasi</option><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select>
      <select v-model="f.type" class="input"><option value="">Semua jenis</option><option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option></select>
      <div class="flex gap-2"><input v-model="f.from" type="date" class="input" title="Dari tanggal" /><input v-model="f.to" type="date" class="input" title="Sampai tanggal" /></div>
    </div>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Waktu</th><th>Produk</th><th>Lokasi</th><th>Jenis</th><th class="text-right">Jumlah</th><th class="text-right">Saldo akhir</th><th>Dokumen</th><th>Oleh</th></tr></thead>
        <tbody>
          <tr v-for="m in movements.data" :key="m.id">
            <td class="whitespace-nowrap text-xs text-ink-500">{{ dateTime(m.created_at) }}</td>
            <td>{{ m.product?.name }}<div class="font-mono text-xs text-ink-500">{{ m.product?.sku }}</div></td>
            <td class="text-sm">{{ m.warehouse?.code }}</td>
            <td class="text-sm">{{ types[m.type] ?? m.type }}<div v-if="m.reason" class="text-xs text-ink-500">{{ reasonLabel[m.reason] ?? m.reason }}</div></td>
            <td class="num text-right font-semibold" :class="m.quantity > 0 ? 'text-emerald-600' : 'text-red-600'">{{ m.quantity > 0 ? '+' : '' }}{{ num(m.quantity) }}</td>
            <td class="num text-right text-ink-500">{{ num(m.qty_after) }}</td>
            <td class="font-mono text-xs text-ink-500">{{ m.reference_number ?? '-' }}<div v-if="m.note" class="font-sans text-ink-500">{{ m.note }}</div></td>
            <td class="text-sm text-ink-500">{{ m.user?.name ?? 'Sistem' }}</td>
          </tr>
          <tr v-if="!movements.data.length"><td colspan="8" class="py-10 text-center text-ink-500">Belum ada mutasi yang cocok.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="movements.links" />
  </AppLayout>
</template>
