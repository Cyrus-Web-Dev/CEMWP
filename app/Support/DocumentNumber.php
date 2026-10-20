<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Nomor dokumen berurutan per bulan: PO-202610-0001. Dikunci per baris sehingga tidak pernah ganda. */
class DocumentNumber
{
    public static function next(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $period = now()->format('Ym');
            $key = "{$prefix}-{$period}";

            DB::table('document_sequences')->insertOrIgnore(['seq_key' => $key, 'last_value' => 0]);
            $row = DB::table('document_sequences')->where('seq_key', $key)->lockForUpdate()->first();
            $next = ((int) $row->last_value) + 1;
            DB::table('document_sequences')->where('seq_key', $key)->update(['last_value' => $next]);

            return sprintf('%s-%s-%04d', $prefix, $period, $next);
        });
    }
}
