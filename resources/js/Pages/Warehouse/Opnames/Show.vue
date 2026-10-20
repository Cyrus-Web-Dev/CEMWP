<script setup>
import { computed, reactive, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, router } from '@inertiajs/vue3'
import { useCan } from '@/Composables/can'
import { num, dateTime } from '@/Composables/format'

const props = defineProps({ opname: Object, items: Array, statuses: Object })
const { can } = useCan()

const editable = props.opname.status === 'draft' && can('opname.manage')
const search = ref('')

// Hasil hitung dipegang di sini; kosong = belum dihitung.
const counts = reactive(Object.fromEntries(props.items.map((i) => [i.id, i.counted_qty ?? ''])))
const saving = ref(false)

const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.items
  return props.items.filter((i) => `${i.product?.name} ${i.product?.sku} ${i.product?.barcode ?? ''}`.toLowerCase().includes(q))
})
const countedNow = computed(() => Object.values(counts).filter((v) => v !== '' && v !== null).length)
const diff = (i) => (counts[i.id] === '' || counts[i.id] === null ? null : Number(counts[i.id]) - i.system_qty)

function save(after) {
  saving.value = true
  router.put(route('warehouse.opnames.counts', props.opname.id), { counts: { ...counts } }, {
    preserveScroll: true,
    onSuccess: () => { if (after) after() },
    onFinish: () => { saving.value = false },
  })
}
function finalize() {
  const n = countedNow.value
  if (!confirm(`Finalisasi opname? Stok sistem disamakan dengan hasil hitung untuk ${n} produk yang sudah diisi. Produk yang dikosongkan tidak diubah.`)) return
  save(() => router.post(route('warehouse.opnames.finalize', props.opname.id), {}, { preserveScroll: true }))
}
function cancel() {
  if (confirm('Batalkan opname ini? Hasil hitung dibuang.')) router.post(route('warehouse.opnames.cancel', props.opname.id), {}, { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head :title="opname.number" />
    <template #title>{{ opname.number }} · {{ opname.warehouse?.name }}</template>
    <template #actions>
      <StatusBadge :status="opname.status" :labels="statuses" />
      <template v-if="editable">
        <button class="btn-secondary btn-sm" :disabled="saving" @click="save()">Simpan hasil</button>
        <button v-if="can('opname.finalize')" class="btn-accent btn-sm" :disabled="saving || countedNow === 0" @click="finalize">Finalisasi</button>
        <button class="btn-ghost btn-sm text-red-600" @click="cancel">Batalkan</button>
      </template>
    </template>

    <div v-if="editable" class="mb-4 max-w-3xl rounded-md border border-ink-200 bg-white px-4 py-3 text-sm text-ink-600 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-300">
      Hitung fisik tiap produk lalu isi kolom "Hasil hitung". Selisih dihitung terhadap stok sistem <strong>saat finalisasi</strong>,
      jadi finalisasi segera setelah selesai menghitung. Produk yang dikosongkan tidak diubah.
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <input v-model="search" class="input max-w-sm" placeholder="Cari produk di daftar…" />
      <p class="text-sm text-ink-500">Terisi {{ countedNow }} dari {{ items.length }} produk<span v-if="opname.finalized_at"> · final {{ dateTime(opname.finalized_at) }} oleh {{ opname.finalizer?.name }}</span></p>
    </div>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Produk</th><th class="text-right">Stok sistem</th><th class="w-40 text-right">Hasil hitung</th><th class="text-right">Selisih</th></tr></thead>
        <tbody>
          <tr v-for="i in visible" :key="i.id">
            <td>{{ i.product?.name }}<div class="font-mono text-xs text-ink-500">{{ i.product?.sku }}</div></td>
            <td class="num text-right">{{ num(i.system_qty) }} <span class="text-xs text-ink-500">{{ i.product?.unit }}</span></td>
            <td class="text-right">
              <input v-if="editable" v-model="counts[i.id]" type="number" min="0" step="1" class="input text-right" />
              <span v-else class="num">{{ i.counted_qty ?? '-' }}</span>
            </td>
            <td class="num text-right font-semibold">
              <template v-if="opname.status === 'finalized'">
                <span :class="i.difference > 0 ? 'text-emerald-600' : i.difference < 0 ? 'text-red-600' : 'text-ink-400'">{{ i.difference === null ? '-' : (i.difference > 0 ? '+' : '') + i.difference }}</span>
              </template>
              <template v-else>
                <span v-if="diff(i) === null" class="text-ink-400">-</span>
                <span v-else :class="diff(i) > 0 ? 'text-emerald-600' : diff(i) < 0 ? 'text-red-600' : 'text-ink-400'">{{ diff(i) > 0 ? '+' : '' }}{{ diff(i) }}</span>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
