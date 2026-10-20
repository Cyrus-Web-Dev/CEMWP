import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Cek izin pengguna saat ini: const { can } = useCan(); can('products.manage')
export function useCan() {
  const perms = computed(() => usePage().props.auth?.user?.permissions ?? [])
  const can = (p) => perms.value.includes(p)

  return { can }
}
