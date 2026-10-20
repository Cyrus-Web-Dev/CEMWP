<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { num, rupiah, dateTime } from '@/Composables/format'

const props = defineProps({ current: Object, outlets: Array, history: Object, canOperate: Boolean })

const open = useForm({ warehouse_id: props.outlets[0]?.id ?? '', opening_cash: 0 })
const close = useForm({ counted_cash: '', notes: '' })

function submitClose() {
  if (confirm('Tutup shift sekarang? Setelah ditutup transaksi baru butuh shift baru.')) close.post(route('sales.shifts.close', props.current.id))
}
</script>

<template>
  <AppLayout>
    <Head title="Shift kasir" />
    <template #title>Shift kasir</template>

    <div v-if="canOperate" class="mb-6 grid gap-6 lg:grid-cols-2">
      <!-- Belum ada shift -->
      <form v-if="!current" class="card" @submit.prevent="open.post(route('sales.shifts.open'))">
        <div class="card-header"><h2 class="text-sm font-semibold">Buka shift</h2></div>
        <div class="card-body space-y-4">
          <p v-if="!outlets.length" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            Belum ada outlet. Tambahkan lokasi stok bertipe <strong>Toko / outlet</strong> di Gudang &gt; Lokasi stok.
          </p>
          <div>
            <label class="label">Outlet</label>
            <select v-model="open.warehouse_id" class="input" required><option v-for="w in outlets" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})</option></select>
            <p v-if="open.errors.warehouse_id" class="error-text">{{ open.errors.warehouse_id }}</p>
          </div>
          <div>
            <label class="label">Kas awal di laci (Rp)</label>
            <input v-model.number="open.opening_cash" type="number" min="0" step="any" class="input text-right" required />
            <p v-if="open.errors.opening_cash" class="error-text">{{ open.errors.opening_cash }}</p>
          </div>
          <button class="btn-primary" :disabled="open.processing || !outlets.length">Buka shift &amp; mulai berjualan</button>
        </div>
      </form>

      <!-- Shift berjalan -->
      <template v-else>
        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Shift berjalan · {{ current.outlet }}</h2><Link :href="route('sales.pos.index')" class="btn-accent btn-sm">Ke kasir</Link></div>
          <dl class="card-body grid grid-cols-2 gap-4 text-sm">
            <div><dt class="text-ink-500">Dibuka</dt><dd>{{ dateTime(current.opened_at) }}</dd></div>
            <div><dt class="text-ink-500">Kas awal</dt><dd class="num">{{ rupiah(current.opening_cash) }}</dd></div>
            <div><dt class="text-ink-500">Transaksi</dt><dd class="num">{{ num(current.summary.orders_count) }}</dd></div>
            <div><dt class="text-ink-500">Penjualan</dt><dd class="num font-semibold">{{ rupiah(current.summary.sales_total) }}</dd></div>
            <div v-for="m in current.summary.by_method" :key="m.code"><dt class="text-ink-500">{{ m.name }}</dt><dd class="num">{{ rupiah(m.total) }}</dd></div>
            <div v-if="current.summary.cash_refunds > 0"><dt class="text-ink-500">Refund tunai</dt><dd class="num text-red-600">−{{ rupiah(current.summary.cash_refunds) }}</dd></div>
          </dl>
        </div>

        <form class="card" @submit.prevent="submitClose">
          <div class="card-header"><h2 class="text-sm font-semibold">Tutup shift</h2></div>
          <div class="card-body space-y-4">
            <p class="text-sm text-ink-600 dark:text-ink-300">Hitung uang fisik di laci, lalu isi di bawah. Selisih terhadap perkiraan sistem dicatat.</p>
            <div>
              <label class="label">Uang tunai di laci saat ini (Rp)</label>
              <input v-model.number="close.counted_cash" type="number" min="0" step="any" class="input text-right" required />
              <p v-if="close.errors.counted_cash" class="error-text">{{ close.errors.counted_cash }}</p>
            </div>
            <div><label class="label">Catatan</label><input v-model="close.notes" class="input" placeholder="Opsional" /></div>
            <button class="btn-danger" :disabled="close.processing">Tutup shift</button>
          </div>
        </form>
      </template>
    </div>

    <div class="card overflow-x-auto">
      <div class="card-header"><h2 class="text-sm font-semibold">Riwayat shift</h2></div>
      <table class="data-table">
        <thead><tr><th>Kasir</th><th>Outlet</th><th>Buka</th><th>Tutup</th><th class="text-right">Perkiraan kas</th><th class="text-right">Dihitung</th><th class="text-right">Selisih</th></tr></thead>
        <tbody>
          <tr v-for="s in history.data" :key="s.id">
            <td><Link :href="route('sales.shifts.show', s.id)" class="font-medium hover:underline">{{ s.user?.name }}</Link></td>
            <td class="text-sm">{{ s.warehouse?.name }}</td>
            <td class="text-xs text-ink-500">{{ dateTime(s.opened_at) }}</td>
            <td class="text-xs text-ink-500">{{ dateTime(s.closed_at) }}</td>
            <td class="num text-right">{{ rupiah(s.expected_cash) }}</td>
            <td class="num text-right">{{ rupiah(s.counted_cash) }}</td>
            <td class="num text-right font-semibold" :class="Number(s.difference) < 0 ? 'text-red-600' : Number(s.difference) > 0 ? 'text-amber-600' : 'text-emerald-600'">{{ Number(s.difference) > 0 ? '+' : '' }}{{ num(s.difference) }}</td>
          </tr>
          <tr v-if="!history.data.length"><td colspan="7" class="py-8 text-center text-ink-500">Belum ada shift yang ditutup.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="history.links" />
  </AppLayout>
</template>
