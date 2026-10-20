<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({ product: Object, categories: Array })
const editing = !!props.product

const form = useForm({
  sku: props.product?.sku ?? '',
  barcode: props.product?.barcode ?? '',
  name: props.product?.name ?? '',
  category_id: props.product?.category_id ?? '',
  description: props.product?.description ?? '',
  unit: props.product?.unit ?? 'pcs',
  cost_price: props.product?.cost_price ?? 0,
  selling_price: props.product?.selling_price ?? 0,
  min_stock: props.product?.min_stock ?? 0,
  weight_grams: props.product?.weight_grams ?? 0,
  is_active: props.product?.is_active ?? true,
  is_sellable_online: props.product?.is_sellable_online ?? true,
  track_stock: props.product?.track_stock ?? true,
})

function submit() {
  form.transform((d) => ({ ...d, category_id: d.category_id === '' ? null : d.category_id }))
  if (editing) form.put(route('warehouse.products.update', props.product.id))
  else form.post(route('warehouse.products.store'))
}
</script>

<template>
  <AppLayout>
    <Head :title="editing ? 'Ubah produk' : 'Produk baru'" />
    <template #title>{{ editing ? 'Ubah produk' : 'Produk baru' }}</template>

    <form class="grid max-w-5xl gap-6 lg:grid-cols-3" @submit.prevent="submit">
      <div class="card lg:col-span-2">
        <div class="card-header"><h2 class="text-sm font-semibold">Informasi produk</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="label">Nama produk</label>
            <input v-model="form.name" class="input" required />
            <p v-if="form.errors.name" class="error-text">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="label">SKU</label>
            <input v-model="form.sku" class="input font-mono" required />
            <p v-if="form.errors.sku" class="error-text">{{ form.errors.sku }}</p>
          </div>
          <div>
            <label class="label">Barcode (opsional)</label>
            <input v-model="form.barcode" class="input font-mono" />
            <p v-if="form.errors.barcode" class="error-text">{{ form.errors.barcode }}</p>
          </div>
          <div>
            <label class="label">Kategori</label>
            <select v-model="form.category_id" class="input">
              <option value="">Tanpa kategori</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <p v-if="form.errors.category_id" class="error-text">{{ form.errors.category_id }}</p>
          </div>
          <div>
            <label class="label">Satuan</label>
            <input v-model="form.unit" class="input" required placeholder="pcs, pak, botol…" />
            <p v-if="form.errors.unit" class="error-text">{{ form.errors.unit }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="label">Deskripsi</label>
            <textarea v-model="form.description" rows="4" class="input"></textarea>
            <p v-if="form.errors.description" class="error-text">{{ form.errors.description }}</p>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Harga &amp; stok</h2></div>
          <div class="card-body space-y-4">
            <div>
              <label class="label">Harga jual (Rp)</label>
              <input v-model.number="form.selling_price" type="number" min="0" step="any" class="input text-right" required />
              <p v-if="form.errors.selling_price" class="error-text">{{ form.errors.selling_price }}</p>
            </div>
            <div>
              <label class="label">Harga pokok (Rp)</label>
              <input v-model.number="form.cost_price" type="number" min="0" step="any" class="input text-right" required />
              <p class="mt-1 text-xs text-ink-500">Diperbarui otomatis (rata-rata bergerak) tiap penerimaan barang. Isi manual hanya untuk data awal.</p>
              <p v-if="form.errors.cost_price" class="error-text">{{ form.errors.cost_price }}</p>
            </div>
            <div>
              <label class="label">Titik pesan ulang</label>
              <input v-model.number="form.min_stock" type="number" min="0" step="1" class="input text-right" required />
              <p class="mt-1 text-xs text-ink-500">Stok total di bawah angka ini ditandai menipis. 0 = tanpa peringatan.</p>
              <p v-if="form.errors.min_stock" class="error-text">{{ form.errors.min_stock }}</p>
            </div>
            <div>
              <label class="label">Berat (gram)</label>
              <input v-model.number="form.weight_grams" type="number" min="0" step="1" class="input text-right" required />
              <p v-if="form.errors.weight_grams" class="error-text">{{ form.errors.weight_grams }}</p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h2 class="text-sm font-semibold">Pengaturan</h2></div>
          <div class="card-body space-y-3 text-sm">
            <label class="flex items-start gap-2"><input v-model="form.is_active" type="checkbox" class="mt-0.5 rounded border-ink-300 text-seal-600 focus:ring-seal-500" /><span>Aktif<span class="block text-xs text-ink-500">Produk nonaktif tidak muncul di pencarian transaksi.</span></span></label>
            <label class="flex items-start gap-2"><input v-model="form.is_sellable_online" type="checkbox" class="mt-0.5 rounded border-ink-300 text-seal-600 focus:ring-seal-500" /><span>Dijual online<span class="block text-xs text-ink-500">Tampil di katalog toko online dan aplikasi mobile.</span></span></label>
            <label class="flex items-start gap-2"><input v-model="form.track_stock" type="checkbox" class="mt-0.5 rounded border-ink-300 text-seal-600 focus:ring-seal-500" /><span>Lacak stok<span class="block text-xs text-ink-500">Matikan untuk jasa atau barang tanpa stok.</span></span></label>
            <p v-if="form.errors.track_stock" class="error-text">{{ form.errors.track_stock }}</p>
          </div>
        </div>

        <div class="flex gap-3">
          <button class="btn-primary flex-1" :disabled="form.processing">{{ editing ? 'Simpan perubahan' : 'Simpan produk' }}</button>
          <Link :href="editing ? route('warehouse.products.show', product.id) : route('warehouse.products.index')" class="btn-secondary">Batal</Link>
        </div>
      </div>
    </form>
  </AppLayout>
</template>
