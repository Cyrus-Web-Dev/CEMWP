<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import LineItems from '@/Components/LineItems.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({ suppliers: Array, warehouses: Array, requestKey: String })

const form = useForm({
  request_key: props.requestKey,
  warehouse_id: props.warehouses.find((w) => w.is_default)?.id ?? props.warehouses[0]?.id ?? '',
  supplier_id: '',
  notes: '',
  items: [],
})

function submit() {
  form.transform((d) => ({
    request_key: d.request_key, warehouse_id: d.warehouse_id, supplier_id: d.supplier_id || null, notes: d.notes || null,
    items: d.items.map((i) => ({ product_id: i.product_id, quantity: Number(i.quantity), unit_cost: Number(i.unit_cost) })),
  })).post(route('warehouse.receipts.store'))
}
</script>

<template>
  <AppLayout>
    <Head title="Terima barang tanpa PO" />
    <template #title>Terima barang tanpa PO</template>

    <form class="max-w-5xl space-y-6" @submit.prevent="submit">
      <div class="card">
        <div class="card-body grid gap-4 md:grid-cols-3">
          <div>
            <label class="label">Masuk ke lokasi</label>
            <select v-model="form.warehouse_id" class="input" required><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select>
            <p v-if="form.errors.warehouse_id" class="error-text">{{ form.errors.warehouse_id }}</p>
          </div>
          <div>
            <label class="label">Supplier (opsional)</label>
            <select v-model="form.supplier_id" class="input"><option value="">Tanpa supplier</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></select>
          </div>
          <div><label class="label">Catatan</label><input v-model="form.notes" class="input" /></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="text-sm font-semibold">Barang yang diterima</h2></div>
        <div class="card-body">
          <LineItems v-model="form.items" with-cost :errors="form.errors" />
          <p class="mt-3 text-xs text-ink-500">Harga pokok per unit dipakai menghitung harga pokok rata-rata produk. Untuk stok awal, isi dengan harga perolehan.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <button class="btn-primary" :disabled="form.processing || !form.items.length">Terima barang &amp; tambah stok</button>
        <Link :href="route('warehouse.receipts.index')" class="btn-secondary">Batal</Link>
      </div>
    </form>
  </AppLayout>
</template>
