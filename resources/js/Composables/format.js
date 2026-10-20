// Pemformat angka & tanggal gaya Indonesia, dipakai di seluruh halaman.
const nf = new Intl.NumberFormat('id-ID')
const rp = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })

export const num = (v) => nf.format(Number(v ?? 0))
export const rupiah = (v) => rp.format(Number(v ?? 0))

export function dateTime(v) {
  if (!v) return '-'
  const d = new Date(v)
  if (isNaN(d)) return String(v)
  return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

export function dateOnly(v) {
  if (!v) return '-'
  const d = new Date(String(v).length <= 10 ? v + 'T00:00:00' : v)
  if (isNaN(d)) return String(v)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Peta status -> kelas badge (kelas ada di resources/css/app.css)
const BADGES = {
  draft: 'badge-slate', ordered: 'badge-seal', partial: 'badge-amber', received: 'badge-green', cancelled: 'badge-red',
  in_transit: 'badge-amber', finalized: 'badge-green',
  pending: 'badge-amber', picking: 'badge-seal', packed: 'badge-seal', shipped: 'badge-seal', delivered: 'badge-green',
  active: 'badge-green', inactive: 'badge-slate',
}
export const badgeClass = (status) => BADGES[status] ?? 'badge-slate'
