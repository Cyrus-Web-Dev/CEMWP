<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import LineItems from '@/Components/LineItems.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({ order: Object, suppliers: Array, warehouses: Array })
const editing = !!props.order

const defaultWarehouse = props.warehouses.find((w) => w.is_default)?.id ?? props.warehouses[0]?.id ?? ''

const form = useForm({
  supplier_id: props.order?.supplier_id ?? '',
  warehouse_id: props.order?.warehouse_id ?? defaultWarehouse,
  expected_at: props.order?.expected_at ? String(props.order.expected_at).slice(0, 10) : '',
  notes: props.order?.notes ?? '',
  items: (props.order?.items ?? []).map((i) => ({
    product_id: i.product_id, name: i.product?.name, sku: i.product?.sku, unit: i.product?.unit,
    quantity: i.qty_ordered, unit_cost: Number(i.unit_cost),
  })),
})

function submit() {
  form.transform((d) => ({
    supplier_id: d.supplier_id, warehouse_id: d.warehouse_id, expected_at: d.expected_at || null, notes: d.notes || null,
    items: d.items.map((i) => ({ product_id: i.product_id, quantity: Number(i.quantity), unit_cost: Number(i.unit_cost) })),
  }))
  if (editing) form.put(route('warehouse.purchase-orders.update', props.order.id))
  else form.post(route('warehouse.purchase-orders.store'))
}
</script>

<template>
  <AppLayout>
    <Head :title="editing ? `Ubah ${order.number}` : 'PO baru'" />
    <template #title>{{ editing ? `Ubah ${order.number}` : 'Purchase order baru' }}</template>

    <form class="max-w-5xl space-y-6" @submit.prevent="submit">
      <div class="card">
        <div class="card-body grid gap-4 md:grid-cols-4">
          <div class="md:col-span-2">
            <label class="label">Supplier</label>
            <select v-model="form.supplier_id" class="input" required><option value="" disabled>Pilih supplier</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></select>
            <p v-if="form.errors.supplier_id" class="error-text">{{ form.errors.supplier_id }}</p>
          </div>
          <div>
            <label class="label">Dikirim ke</label>
            <select v-model="form.warehouse_id" class="input" required><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select>
            <p v-if="form.errors.warehouse_id" class="error-text">{{ form.errors.warehouse_id }}</p>
          </div>
          <div>
            <label class="label">Estimasi tiba</label>
            <input v-model="form.expected_at" type="date" class="input" />
            <p v-if="form.errors.expected_at" class="error-text">{{ form.errors.expected_at }}</p>
          </div>
          <div class="md:col-span-4">
            <label class="label">Catatan</label>
            <input v-model="form.notes" class="input" placeholder="Opsional" />
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="text-sm font-semibold">Barang yang dipesan</h2></div>
        <div class="card-body"><LineItems v-model="form.items" with-cost :errors="form.errors" /></div>
      </div>

      <div class="flex gap-3">
        <button class="btn-primary" :disabled="form.processing || !form.items.length">{{ editing ? 'Simpan perubahan' : 'Simpan sebagai draf' }}</button>
        <Link :href="editing ? route('warehouse.purchase-orders.show', order.id) : route('warehouse.purchase-orders.index')" class="btn-secondary">Batal</Link>
      </div>
    </form>
  </AppLayout>
</template>
