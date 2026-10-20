<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import LineItems from '@/Components/LineItems.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({ warehouses: Array })

const form = useForm({
  from_warehouse_id: props.warehouses[0]?.id ?? '',
  to_warehouse_id: props.warehouses[1]?.id ?? '',
  notes: '',
  items: [],
})

function submit() {
  form.transform((d) => ({
    from_warehouse_id: d.from_warehouse_id, to_warehouse_id: d.to_warehouse_id, notes: d.notes || null,
    items: d.items.map((i) => ({ product_id: i.product_id, quantity: Number(i.quantity) })),
  })).post(route('warehouse.transfers.store'))
}
</script>

<template>
  <AppLayout>
    <Head title="Transfer baru" />
    <template #title>Transfer baru</template>

    <form class="max-w-5xl space-y-6" @submit.prevent="submit">
      <div class="card">
        <div class="card-body grid gap-4 md:grid-cols-3">
          <div>
            <label class="label">Dari lokasi</label>
            <select v-model="form.from_warehouse_id" class="input" required><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select>
            <p v-if="form.errors.from_warehouse_id" class="error-text">{{ form.errors.from_warehouse_id }}</p>
          </div>
          <div>
            <label class="label">Ke lokasi</label>
            <select v-model="form.to_warehouse_id" class="input" required><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select>
            <p v-if="form.errors.to_warehouse_id" class="error-text">{{ form.errors.to_warehouse_id }}</p>
          </div>
          <div><label class="label">Catatan</label><input v-model="form.notes" class="input" /></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="text-sm font-semibold">Barang yang dipindahkan</h2></div>
        <div class="card-body">
          <LineItems v-model="form.items" :warehouse-id="form.from_warehouse_id" :errors="form.errors" />
          <p class="mt-3 text-xs text-ink-500">Stok baru keluar dari lokasi asal saat transfer dikirim, bukan saat disimpan.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <button class="btn-primary" :disabled="form.processing || !form.items.length">Simpan sebagai draf</button>
        <Link :href="route('warehouse.transfers.index')" class="btn-secondary">Batal</Link>
      </div>
    </form>
  </AppLayout>
</template>
