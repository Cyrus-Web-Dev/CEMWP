<script setup>
import { ref } from 'vue'
import ShopLayout from '@/Layouts/ShopLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({ pending: Object })

// Tab awal mengikuti langkah yang sedang berjalan (verifikasi kode)
const tab = ref(props.pending?.mode === 'reset' ? 'reset' : props.pending?.mode === 'register' ? 'register' : 'login')

const login = useForm({ email: '', password: '', remember: false })
const reg = useForm({ name: '', email: props.pending?.email ?? '', phone: '', password: '', password_confirmation: '', marketing_consent: false })
const regVerify = useForm({ email: props.pending?.email ?? '', code: '' })
const resetStart = useForm({ email: props.pending?.email ?? '' })
const resetVerify = useForm({ email: props.pending?.email ?? '', code: '', password: '', password_confirmation: '' })

const awaitingCode = (mode) => props.pending?.mode === mode
function registerStart() {
  reg.transform((d) => ({ ...d, phone: d.phone || null })).post(route('shop.register.start'), { preserveScroll: true, onSuccess: () => { regVerify.email = reg.email } })
}
function sendReset() {
  resetStart.post(route('shop.reset.start'), { preserveScroll: true, onSuccess: () => { resetVerify.email = resetStart.email } })
}
const tabs = [['login', 'Masuk'], ['register', 'Daftar'], ['reset', 'Lupa password']]
</script>

<template>
  <ShopLayout>
    <Head title="Masuk" />
    <div class="mx-auto max-w-md">
      <div class="mb-5 flex rounded-lg bg-[#F1E8D6] p-1 text-sm font-medium">
        <button v-for="[k, l] in tabs" :key="k" class="flex-1 rounded-md px-3 py-2" :class="tab === k ? 'bg-white shadow-sm' : 'text-[#7A6A58]'" @click="tab = k">{{ l }}</button>
      </div>

      <!-- Masuk -->
      <form v-if="tab === 'login'" class="space-y-4 rounded-xl border border-[#E7DCC8] bg-white p-5" @submit.prevent="login.post(route('shop.login'))">
        <div><label class="label">Email</label><input v-model="login.email" type="email" class="input bg-white" autocomplete="email" required /><p v-if="login.errors.email" class="error-text">{{ login.errors.email }}</p></div>
        <div><label class="label">Password</label><input v-model="login.password" type="password" class="input bg-white" autocomplete="current-password" required /></div>
        <label class="flex items-center gap-2 text-sm"><input v-model="login.remember" type="checkbox" class="rounded border-[#D9CBB5] text-[#2A1F17] focus:ring-[#E2B659]" /> Ingat saya</label>
        <button class="btn-primary w-full" :disabled="login.processing">Masuk</button>
      </form>

      <!-- Daftar: langkah 1 -->
      <form v-else-if="tab === 'register' && !awaitingCode('register')" class="space-y-4 rounded-xl border border-[#E7DCC8] bg-white p-5" @submit.prevent="registerStart">
        <div><label class="label">Nama lengkap</label><input v-model="reg.name" class="input bg-white" required /><p v-if="reg.errors.name" class="error-text">{{ reg.errors.name }}</p></div>
        <div><label class="label">Email</label><input v-model="reg.email" type="email" class="input bg-white" required /><p v-if="reg.errors.email" class="error-text">{{ reg.errors.email }}</p></div>
        <div><label class="label">Nomor HP (opsional)</label><input v-model="reg.phone" class="input bg-white" inputmode="tel" /><p v-if="reg.errors.phone" class="error-text">{{ reg.errors.phone }}</p></div>
        <div><label class="label">Password</label><input v-model="reg.password" type="password" class="input bg-white" autocomplete="new-password" required /><p v-if="reg.errors.password" class="error-text">{{ reg.errors.password }}</p><p class="mt-1 text-xs text-[#7A6A58]">Minimal 8 karakter, huruf besar & kecil, ada angka.</p></div>
        <div><label class="label">Ulangi password</label><input v-model="reg.password_confirmation" type="password" class="input bg-white" autocomplete="new-password" required /></div>
        <label class="flex items-start gap-2 text-sm"><input v-model="reg.marketing_consent" type="checkbox" class="mt-0.5 rounded border-[#D9CBB5] text-[#2A1F17] focus:ring-[#E2B659]" /> <span>Saya bersedia menerima info promo & kupon lewat WhatsApp/email. (Boleh dikosongkan; bisa dicabut kapan saja.)</span></label>
        <button class="btn-primary w-full" :disabled="reg.processing">Kirim kode verifikasi</button>
      </form>
      <!-- Daftar: langkah 2 -->
      <form v-else-if="tab === 'register'" class="space-y-4 rounded-xl border border-[#E7DCC8] bg-white p-5" @submit.prevent="regVerify.post(route('shop.register.verify'))">
        <p class="text-sm text-[#4A3B2E]">Kami mengirim kode 6 digit ke <strong>{{ pending.email }}</strong>. Masukkan di bawah untuk menyelesaikan pendaftaran.</p>
        <div><label class="label">Kode verifikasi</label><input v-model="regVerify.code" class="input bg-white text-center font-mono text-lg tracking-widest" maxlength="6" inputmode="numeric" required /><p v-if="regVerify.errors.code" class="error-text">{{ regVerify.errors.code }}</p></div>
        <button class="btn-primary w-full" :disabled="regVerify.processing">Selesaikan pendaftaran</button>
      </form>

      <!-- Lupa password -->
      <form v-else-if="!awaitingCode('reset')" class="space-y-4 rounded-xl border border-[#E7DCC8] bg-white p-5" @submit.prevent="sendReset">
        <p class="text-sm text-[#4A3B2E]">Masukkan email akunmu. Kami kirim kode untuk mengatur ulang password.</p>
        <div><label class="label">Email</label><input v-model="resetStart.email" type="email" class="input bg-white" required /><p v-if="resetStart.errors.email" class="error-text">{{ resetStart.errors.email }}</p></div>
        <button class="btn-primary w-full" :disabled="resetStart.processing">Kirim kode</button>
      </form>
      <form v-else class="space-y-4 rounded-xl border border-[#E7DCC8] bg-white p-5" @submit.prevent="resetVerify.post(route('shop.reset.verify'))">
        <p class="text-sm text-[#4A3B2E]">Masukkan kode yang dikirim ke <strong>{{ pending.email }}</strong> dan password baru.</p>
        <div><label class="label">Kode</label><input v-model="resetVerify.code" class="input bg-white text-center font-mono text-lg tracking-widest" maxlength="6" inputmode="numeric" required /><p v-if="resetVerify.errors.code" class="error-text">{{ resetVerify.errors.code }}</p></div>
        <div><label class="label">Password baru</label><input v-model="resetVerify.password" type="password" class="input bg-white" autocomplete="new-password" required /><p v-if="resetVerify.errors.password" class="error-text">{{ resetVerify.errors.password }}</p></div>
        <div><label class="label">Ulangi password</label><input v-model="resetVerify.password_confirmation" type="password" class="input bg-white" autocomplete="new-password" required /></div>
        <button class="btn-primary w-full" :disabled="resetVerify.processing">Ganti password</button>
      </form>
    </div>
  </ShopLayout>
</template>
