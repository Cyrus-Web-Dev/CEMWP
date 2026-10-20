<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import ShopLayout from '@/Layouts/ShopLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { num, rupiah } from '@/Composables/format'

const props = defineProps({ shippingMethods: Array, paymentMethods: Array, prefill: Object, loggedIn: Boolean, requestKey: String })

const form = useForm({
  request_key: props.requestKey,
  customer_name: props.prefill.customer_name, customer_phone: props.prefill.customer_phone, customer_email: props.prefill.customer_email,
  shipping_method_id: props.shippingMethods[0]?.id ?? '',
  payment_method: props.paymentMethods[0]?.code ?? '',
  recipient_name: props.prefill.recipient_name, recipient_phone: props.prefill.recipient_phone,
  shipping_address: props.prefill.shipping_address, shipping_city: props.prefill.shipping_city, shipping_postal_code: props.prefill.shipping_postal_code,
  coupon_code: '', notes: '',
})

const shipping = computed(() => props.shippingMethods.find((m) => m.id === form.shipping_method_id))
const isPickup = computed(() => !!shipping.value?.is_pickup)
const payment = computed(() => props.paymentMethods.find((m) => m.code === form.payment_method))

// ---- Ringkasan dari server ----
const quote = ref(null)
const quoteError = ref('')
let timer = null
let seq = 0
async function refreshQuote() {
  const mine = ++seq
  try {
    const { data } = await window.axios.post(route('shop.checkout.quote'), {
      coupon_code: form.coupon_code || null, shipping_method_id: form.shipping_method_id || null, customer_phone: form.customer_phone || null,
    })
    if (mine === seq) { quote.value = data; quoteError.value = '' }
  } catch (e) {
    if (mine === seq) quoteError.value = e.response?.data?.message ?? 'Gagal menghitung total.'
  }
}
function scheduleQuote() { clearTimeout(timer); timer = setTimeout(refreshQuote, 350) }
watch(() => form.shipping_method_id, refreshQuote)
watch(() => form.coupon_code, scheduleQuote)
onMounted(refreshQuote)
onBeforeUnmount(() => clearTimeout(timer))

function submit() {
  form.transform((d) => ({ ...d, coupon_code: d.coupon_code || null, customer_email: d.customer_email || null })).post(route('shop.checkout.store'))
}
const field = 'input bg-white'
</script>

<template>
  <ShopLayout>
    <Head title="Checkout" />
    <Link :href="route('shop.cart')" class="mb-4 inline-block text-sm text-[#7A6A58] hover:underline">← Kembali ke keranjang</Link>
    <h1 class="mb-6 font-serif text-2xl font-semibold">Checkout</h1>

    <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
      <div class="space-y-6 lg:col-span-2">
        <section class="rounded-xl border border-[#E7DCC8] bg-white p-5">
          <h2 class="font-serif text-lg font-semibold">Kontak</h2>
          <p v-if="!loggedIn" class="mt-1 text-xs text-[#7A6A58]">Belanja tanpa akun juga bisa. <Link :href="route('shop.auth')" class="underline">Masuk / daftar</Link> untuk melacak semua pesananmu.</p>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><label class="label">Nama</label><input v-model="form.customer_name" :class="field" required /><p v-if="form.errors.customer_name" class="error-text">{{ form.errors.customer_name }}</p></div>
            <div><label class="label">Nomor HP / WhatsApp</label><input v-model="form.customer_phone" :class="field" inputmode="tel" required @blur="refreshQuote" /><p v-if="form.errors.customer_phone" class="error-text">{{ form.errors.customer_phone }}</p></div>
            <div class="sm:col-span-2"><label class="label">Email (opsional)</label><input v-model="form.customer_email" type="email" :class="field" /><p v-if="form.errors.customer_email" class="error-text">{{ form.errors.customer_email }}</p></div>
          </div>
        </section>

        <section class="rounded-xl border border-[#E7DCC8] bg-white p-5">
          <h2 class="font-serif text-lg font-semibold">Pengiriman</h2>
          <div class="mt-4 space-y-2">
            <label v-for="m in shippingMethods" :key="m.id" class="flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 text-sm" :class="form.shipping_method_id === m.id ? 'border-[#2A1F17] bg-[#FBF7EF]' : 'border-[#E7DCC8]'">
              <span class="flex items-center gap-3"><input v-model="form.shipping_method_id" type="radio" :value="m.id" class="text-[#2A1F17] focus:ring-[#E2B659]" /> {{ m.label }}</span>
              <span class="text-xs text-[#7A6A58]"><template v-if="m.max_days != null">{{ m.min_days ?? 0 }}-{{ m.max_days }} hari</template></span>
            </label>
          </div>
          <p v-if="form.errors.shipping_method_id" class="error-text">{{ form.errors.shipping_method_id }}</p>

          <div v-if="!isPickup" class="mt-5 grid gap-4 sm:grid-cols-2">
            <div><label class="label">Nama penerima</label><input v-model="form.recipient_name" :class="field" /></div>
            <div><label class="label">HP penerima</label><input v-model="form.recipient_phone" :class="field" inputmode="tel" /></div>
            <div class="sm:col-span-2"><label class="label">Alamat lengkap</label><textarea v-model="form.shipping_address" rows="3" :class="field"></textarea></div>
            <div><label class="label">Kota / kabupaten</label><input v-model="form.shipping_city" :class="field" /></div>
            <div><label class="label">Kode pos</label><input v-model="form.shipping_postal_code" :class="field" inputmode="numeric" /></div>
          </div>
          <p v-else class="mt-4 text-sm text-[#7A6A58]">Kamu akan mengambil pesanan di toko setelah kami kabari siap.</p>
        </section>

        <section class="rounded-xl border border-[#E7DCC8] bg-white p-5">
          <h2 class="font-serif text-lg font-semibold">Pembayaran</h2>
          <div class="mt-4 space-y-2">
            <label v-for="m in paymentMethods" :key="m.code" class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm" :class="form.payment_method === m.code ? 'border-[#2A1F17] bg-[#FBF7EF]' : 'border-[#E7DCC8]'">
              <input v-model="form.payment_method" type="radio" :value="m.code" class="text-[#2A1F17] focus:ring-[#E2B659]" /> {{ m.name }}
            </label>
          </div>
          <p v-if="payment?.kind === 'cod'" class="mt-3 text-xs text-[#7A6A58]">Bayar tunai ke kurir saat barang tiba. Stok langsung dialokasikan untukmu.</p>
          <p v-else-if="payment?.kind === 'manual'" class="mt-3 text-xs text-[#7A6A58]">Instruksi transfer ditampilkan setelah pesanan dibuat. Pesanan dibatalkan otomatis bila tidak dibayar tepat waktu.</p>
          <p v-else-if="payment?.kind === 'gateway'" class="mt-3 text-xs text-[#7A6A58]">Kamu akan diarahkan ke halaman pembayaran aman (QRIS, e-wallet, virtual account).</p>
          <p v-if="form.errors.payment_method" class="error-text">{{ form.errors.payment_method }}</p>
          <div class="mt-4"><label class="label">Catatan untuk penjual (opsional)</label><input v-model="form.notes" :class="field" /></div>
        </section>
      </div>

      <aside class="self-start rounded-xl border border-[#E7DCC8] bg-white p-5">
        <h2 class="font-serif text-lg font-semibold">Pesananmu</h2>
        <ul v-if="quote" class="mt-4 space-y-2 text-sm">
          <li v-for="l in quote.lines" :key="l.product_id" class="flex justify-between gap-3"><span>{{ l.name }} <span class="text-[#7A6A58]">×{{ l.quantity }}</span></span><span class="num">{{ rupiah(l.line_total) }}</span></li>
        </ul>
        <p v-else class="mt-4 text-sm text-[#7A6A58]">Menghitung…</p>

        <div class="mt-4">
          <label class="label">Kode kupon</label>
          <input v-model="form.coupon_code" class="input bg-white uppercase" placeholder="Punya kupon?" />
          <p v-if="quote?.coupon" class="mt-1 text-xs text-emerald-700">Kupon {{ quote.coupon.code }} dipakai.</p>
          <p v-else-if="quote?.coupon_error" class="mt-1 text-xs text-red-600">{{ quote.coupon_error }}</p>
        </div>

        <dl v-if="quote" class="mt-4 space-y-1 border-t border-[#E7DCC8] pt-4 text-sm">
          <div class="flex justify-between"><dt class="text-[#7A6A58]">Subtotal</dt><dd class="num">{{ rupiah(quote.subtotal) }}</dd></div>
          <div v-if="quote.discount_total > 0" class="flex justify-between text-emerald-700"><dt>Diskon</dt><dd class="num">−{{ rupiah(quote.discount_total) }}</dd></div>
          <div class="flex justify-between"><dt class="text-[#7A6A58]">Ongkir</dt><dd class="num">{{ quote.shipping_cost > 0 ? rupiah(quote.shipping_cost) : 'Gratis' }}</dd></div>
          <div v-if="quote.tax_total > 0" class="flex justify-between"><dt class="text-[#7A6A58]">PPN</dt><dd class="num">{{ rupiah(quote.tax_total) }}</dd></div>
          <div class="flex justify-between border-t border-[#E7DCC8] pt-2 text-lg font-semibold"><dt>Total</dt><dd class="num">{{ rupiah(quote.grand_total) }}</dd></div>
        </dl>
        <p v-if="quote?.shortages?.length" class="mt-3 text-xs text-red-600">Stok kurang: {{ quote.shortages.map((s) => `${s.name} (tersedia ${s.available})`).join(', ') }}</p>
        <p v-if="quoteError" class="mt-3 text-xs text-red-600">{{ quoteError }}</p>

        <button class="btn-primary mt-5 w-full py-3" :disabled="form.processing || !quote || !!quote.shortages?.length">{{ form.processing ? 'Memproses…' : 'Buat pesanan' }}</button>
      </aside>
    </form>
  </ShopLayout>
</template>
