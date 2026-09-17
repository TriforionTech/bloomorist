<div class="space-y-4 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
    <div class="bg-primary-50 dark:bg-primary-500/10 p-4 rounded-xl text-primary-600 dark:text-primary-400">
        <h4 class="font-bold text-base mb-2">Apa itu Jurnal Umum?</h4>
        <p>Jurnal Umum (General Journal) adalah fitur pembukuan tingkat lanjut (menggunakan standar <i>double-entry</i> akuntansi) untuk mencatat transaksi yang <b>tidak tercakup secara otomatis</b> oleh sistem Invoice/Sales.</p>
    </div>

    <div>
        <h4 class="font-bold text-gray-900 dark:text-white mb-2">Kapan Saya Harus Menggunakannya?</h4>
        <ul class="list-disc pl-5 space-y-1">
            <li><b>Memasukkan Modal Awal:</b> Contoh: Menyetorkan uang modal ke Kas (Debit: Kas, Kredit: Modal Pemilik).</li>
            <li><b>Transaksi Biaya Non-Otomatis:</b> Contoh: Membayar denda pajak, mencatat penyusutan aset, atau biaya operasional lain.</li>
            <li><b>Jurnal Penyesuaian/Koreksi:</b> Memindahkan saldo jika terjadi salah catat antar akun agar Laporan Neraca tetap <i>balance</i>.</li>
        </ul>
    </div>

    <div>
        <h4 class="font-bold text-gray-900 dark:text-white mb-2">Aturan Dasar (Sistem Double-Entry)</h4>
        <p class="mb-2">Setiap transaksi di Jurnal Umum harus memiliki pasangan <b>Debit</b> dan <b>Kredit</b> yang seimbang (Total Rp Debit = Total Rp Kredit).</p>
        <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
            <p class="font-mono text-xs">Contoh Mencatat Modal Masuk ke Bank Rp 10.000.000:</p>
            <ul class="list-none mt-2 text-xs space-y-1">
                <li><span class="font-semibold text-emerald-600">Debit:</span> 1102 - Bank (Rp 10.000.000)</li>
                <li><span class="font-semibold text-rose-600">Kredit:</span> 3101 - Modal Pemilik (Rp 10.000.000)</li>
            </ul>
        </div>
    </div>

    <div class="bg-warning-50 dark:bg-warning-500/10 p-4 rounded-xl text-warning-700 dark:text-warning-400 mt-4">
        <div class="flex items-start gap-2">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 mt-0.5 shrink-0" />
            <p><b>Peringatan:</b> Jurnal Umum akan langsung berdampak pada <b>Buku Besar</b> dan <b>Neraca</b> perusahaan Anda. Jika Anda tidak yakin akun mana yang harus di-Debit/Kredit, konsultasikan dengan Akuntan.</p>
        </div>
    </div>
</div>
