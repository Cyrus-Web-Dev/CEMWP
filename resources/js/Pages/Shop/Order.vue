<script setup>
import ShopLayout from '@/Layouts/ShopLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { num, rupiah, dateTime } from '@/Composables/format'

const props = defineProps({ order: Object, instructions: String, canCancel: Boolean })
const o = props.order

const tone = {
  awaiting_payment: 'border-amber-300 bg-amber-50 text-amber-900', processing: 'border-sky-300 bg-sky-50 text-sky-900', shipped: 'border-sky-300 bg-sky-50 text-sky-900',
  completed: 'border-emerald-300 bg-emerald-50 text-emerald-900', cancelled: 'border-red-300 bg-red-50 text-red-900', expired: 'border-ink-300 bg-ink-50 text-ink-700', needs_review: 'border-amber-300 bg-amber-50 text-amber-900',
}
function cancel() {
  if (confirm('Batalkan pesanan ini?')) router.post(route('shop.orders.cancel', o.number), {}, { preserveScroll: true })
}
</script>

<template>
  <ShopLayout>
    <Head :title="`Pesanan ${o.number}`" />
    <Link :href="route('shop.home')" class="mb-4 inline-block text-sm text-[#7A6A58] hover:underline">← Lanjut belanja</Link>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div><p class="text-xs uppercase tracking-wider text-[#8A7A66]">Nomor pesanan</p><h1 class="font-mono text-2xl font-semibold">{{ o.number }}</h1></div>
      <p class="text-sm text-[#7A6A58]">{{ dateTime(o.placed_at) }}</p>
    </div>

    <div class="mb-6 rounded-xl border px-5 py-4" :class="tone[o.status] ?? 'border-[#E7DCC8] bg-white'">
      <p class="font-semibold">{{ o.status_label }}</p>
      <p class="text-sm opacity-90">Pembayaran: {{ o.payment_status_label }}</p>
      <p v-if="o.status === 'awaiting_payment' && o.expires_at" class="mt-1 text-sm">Bayar sebelum <strong>{{ dateTime(o.expires_at) }}</strong>, atau pesanan dibatalkan otomatis.</p>
      <p v-if="o.status === 'needs_review'" class="mt-1 text-sm">Pembayaranmu sudah kami terima, tetapi ada kendala stok. Tim kami akan menghubungimu atau mengembalikan dana.</p>
    </div>

    <!-- Instruksi pembayaran -->
    <section v-if="o.status === 'awaiting_payment' && o.payment" class="mb-6 rounded-xl border border-[#E7DCC8] bg-white p-5">
      <h2 class="font-serif text-lg font-semibold">Cara membayar</h2>
      <template v-if="o.payment.kind === 'gateway'">
        <p class="mt-2 text-sm text-[#4A3B2E]">Selesaikan pembayaran lewat halaman aman berikut.</p>
        <a v-if="o.payment.redirect_url" :href="o.payment.redirect_url" class="btn-primary mt-3 inline-flex px-6 py-2.5">Bayar sekarang</a>
      </template>
      <template v-else-if="o.payment.kind === 'manual'">
        <p class="mt-2 text-sm text-[#4A3B2E]">{{ instructions }}</p>
        <p class="num mt-2 text-lg font-semibold">Jumlah: {{ rupiah(o.grand_total) }}</p>
        <p class="mt-2 text-xs text-[#7A6A58]">Setelah transfer, tim kami mengonfirmasi pembayaranmu. Halaman ini diperbarui otomatis saat dikonfirmasi — muat ulang untuk memeriksa.</p>
      </template>
    </section>
    <p v-if="o.payment?.kind === 'cod' && ['processing', 'shipped'].includes(o.status)" class="mb-6 rounded-xl border border-[#E7DCC8] bg-white p-5 text-sm">Siapkan uang pas <strong class="num">{{ rupiah(o.grand_total) }}</strong> untuk kurir saat barang tiba.</p>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        <ul class="divide-y divide-[#E7DCC8] rounded-xl border border-[#E7DCC8] bg-white">
          <li v-for="i in o.items" :key="i.product_id" class="flex justify-between gap-4 p-4 text-sm">
            <span>{{ i.name }} <span class="text-[#7A6A58]">×{{ num(i.quantity) }}</span></span><span class="num font-medium">{{ rupiah(i.line_total) }}</span>
          </li>
          <li class="space-y-1 p-4 text-sm">
            <p class="flex justify-between"><span class="text-[#7A6A58]">Subtotal</span><span class="num">{{ rupiah(o.subtotal) }}</span></p>
            <p v-if="o.discount_total > 0" class="flex justify-between text-emerald-700"><span>Diskon {{ o.coupon_code ? `(${o.coupon_code})` : '' }}</span><span class="num">−{{ rupiah(o.discount_total) }}</span></p>
            <p class="flex justify-between"><span class="text-[#7A6A58]">Ongkir</span><span class="num">{{ o.shipping_cost > 0 ? rupiah(o.shipping_cost) : 'Gratis' }}</span></p>
            <p v-if="o.tax_total > 0" class="flex justify-between"><span class="text-[#7A6A58]">PPN</span><span class="num">{{ rupiah(o.tax_total) }}</span></p>
            <p class="flex justify-between border-t border-[#E7DCC8] pt-2 text-base font-semibold"><span>Total</span><span class="num">{{ rupiah(o.grand_total) }}</span></p>
          </li>
        </ul>

        <div class="rounded-xl border border-[#E7DCC8] bg-white p-5 text-sm">
          <h2 class="font-serif text-lg font-semibold">Pengiriman</h2>
          <p class="mt-2">{{ o.shipping.label }}</p>
          <p v-if="o.shipping.recipient_name" class="text-[#4A3B2E]">{{ o.shipping.recipient_name }} · {{ o.shipping.recipient_phone }}</p>
          <p class="whitespace-pre-line text-[#7A6A58]">{{ o.shipping.address }}<span v-if="o.shipping.city">, {{ o.shipping.city }}</span> {{ o.shipping.postal_code }}</p>
          <p v-if="o.shipping.tracking_number" class="mt-2">Kurir <strong>{{ o.shipping.courier }}</strong> · resi <span class="font-mono">{{ o.shipping.tracking_number }}</span></p>
        </div>
      </div>

      <div class="space-y-6 self-start">
        <div class="rounded-xl border border-[#E7DCC8] bg-white p-5">
          <h2 class="font-serif text-lg font-semibold">Status pesanan</h2>
          <ol class="mt-3 space-y-3 border-l-2 border-[#E7DCC8] pl-4 text-sm">
            <li v-for="(t, i) in o.timeline" :key="i">
              <p class="font-medium">{{ t.label }}</p>
              <p class="text-xs text-[#7A6A58]">{{ dateTime(t.at) }}<span v-if="t.note"> · {{ t.note }}</span></p>
            </li>
          </ol>
        </div>
        <button v-if="canCancel" class="btn-secondary w-full text-red-700" @click="cancel">Batalkan pesanan</button>
      </div>
    </div>
  </ShopLayout>
</template>
