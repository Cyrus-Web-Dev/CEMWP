<script setup>
import ProductSearch from '@/Components/ProductSearch.vue'
import { rupiah } from '@/Composables/format'

// v-model: array [{product_id, name, sku, unit, quantity, unit_cost?, available?}]
const lines = defineModel({ type: Array, default: () => [] })
const props = defineProps({
  withCost: { type: Boolean, default: false },
  warehouseId: { type: [Number, String], default: null },
  errors: { type: Object, default: () => ({}) },
})

function add(p) {
  const existing = lines.value.find((l) => l.product_id === p.id)
  if (existing) { existing.quantity = Number(existing.quantity) + 1; return }
  lines.value = [...lines.value, {
    product_id: p.id, name: p.name, sku: p.sku, unit: p.unit, quantity: 1,
    unit_cost: Number(p.cost_price ?? 0), available: p.available,
  }]
}
function remove(i) { lines.value = lines.value.filter((_, idx) => idx !== i) }
const total = () => lines.value.reduce((s, l) => s + Number(l.quantity || 0) * Number(l.unit_cost || 0), 0)
</script>

<template>
  <div>
    <ProductSearch :warehouse-id="props.warehouseId" @select="add" />
    <p v-if="errors.items" class="error-text">{{ errors.items }}</p>

    <div v-if="lines.length" class="mt-3 overflow-x-auto rounded-md border border-ink-200 dark:border-ink-800">
      <table class="data-table">
        <thead>
          <tr>
            <th>Produk</th>
            <th class="w-32 text-right">Jumlah</th>
            <th v-if="withCost" class="w-40 text-right">Harga pokok</th>
            <th v-if="withCost" class="w-36 text-right">Subtotal</th>
            <th class="w-10"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(l, i) in lines" :key="l.product_id">
            <td>
              <div class="font-medium">{{ l.name }}</div>
              <div class="font-mono text-xs text-ink-500">{{ l.sku }}<span v-if="l.available !== undefined && l.available !== null"> · tersedia {{ l.available }} {{ l.unit }}</span></div>
              <p v-if="errors[`items.${i}.product_id`]" class="error-text">{{ errors[`items.${i}.product_id`] }}</p>
            </td>
            <td class="text-right">
              <input v-model.number="l.quantity" type="number" min="1" step="1" class="input text-right" />
              <p v-if="errors[`items.${i}.quantity`]" class="error-text">{{ errors[`items.${i}.quantity`] }}</p>
            </td>
            <td v-if="withCost" class="text-right">
              <input v-model.number="l.unit_cost" type="number" min="0" step="any" class="input text-right" />
              <p v-if="errors[`items.${i}.unit_cost`]" class="error-text">{{ errors[`items.${i}.unit_cost`] }}</p>
            </td>
            <td v-if="withCost" class="num text-right">{{ rupiah(Number(l.quantity || 0) * Number(l.unit_cost || 0)) }}</td>
            <td class="text-right">
              <button type="button" class="text-ink-400 hover:text-red-600" title="Hapus baris" @click="remove(i)">&times;</button>
            </td>
          </tr>
        </tbody>
        <tfoot v-if="withCost">
          <tr>
            <td colspan="3" class="text-right text-sm font-semibold">Total</td>
            <td class="num text-right font-semibold">{{ rupiah(total()) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p v-else class="mt-3 text-sm text-ink-500">Belum ada item. Cari produk di atas untuk menambahkannya.</p>
  </div>
</template>
