<div class="space-y-5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
    <div class="bg-primary-50 dark:bg-primary-500/10 p-4 rounded-xl text-primary-700 dark:text-primary-400">
        <h4 class="font-bold text-base mb-2">Apa itu Periode Akuntansi?</h4>
        <p>Periode Akuntansi adalah rentang waktu (biasanya 1 bulan) di mana semua transaksi keuangan (Penjualan, Pengeluaran, Jurnal) dicatat dan dikumpulkan sebelum ditutup untuk menghasilkan Laporan Keuangan final.</p>
    </div>

    <div>
        <h4 class="font-bold text-gray-900 dark:text-white mb-2">1. Saldo Awal Kas & Stock Opname</h4>
        <ul class="list-disc pl-5 space-y-1">
            <li><b>Saldo Awal Kas & Bank:</b> Angka patokan awal khusus untuk membantu perhitungan di Laporan Arus Kas (Cash Flow) agar selisih mutasi bulan tersebut dapat ditambahkan ke angka ini. <i>(Catatan: Bukan pencatatan Modal).</i></li>
            <li><b>Nilai Stock Opname:</b> Total nilai fisik barang di gudang pada hari terakhir periode. Wajib diisi di akhir bulan sebelum melakukan <b>Tutup Buku</b>, agar HPP (Harga Pokok Penjualan) dapat dihitung dengan akurat di Laporan Laba Rugi.</li>
        </ul>
    </div>

    <div>
        <h4 class="font-bold text-gray-900 dark:text-white mb-2">2. Jurnal Penutup & Tutup Buku (Closing)</h4>
        <ul class="list-disc pl-5 space-y-1">
            <li><b>Tutup Buku (Close Period):</b> Mengunci periode agar tidak ada lagi yang bisa menambah, mengubah, atau menghapus Invoice dan Jurnal di rentang tanggal tersebut.</li>
            <li><b>Jurnal Penutup Otomatis:</b> Saat Anda klik Tutup Buku, sistem otomatis membuat jurnal untuk "menge-nol-kan" semua akun Pendapatan dan Beban, lalu memindahkannya ke akun Laba/Rugi Ditahan (Retained Earnings) pada bulan tersebut.</li>
        </ul>
    </div>

    <div>
        <h4 class="font-bold text-gray-900 dark:text-white mb-2">3. Posting Penyusutan (Depreciation)</h4>
        <p class="mb-2">Jika Anda memiliki Aset Tetap (seperti Mesin, Kendaraan), setiap bulan nilainya akan menyusut. Anda harus membuat Jurnal Umum penyesuaian untuk mencatat beban penyusutan ini <b>sebelum</b> menekan tombol Tutup Buku.</p>
        <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
            <p class="font-mono text-xs">Contoh Jurnal Penyusutan:</p>
            <ul class="list-none mt-2 text-xs space-y-1">
                <li><span class="font-semibold text-emerald-600">Debit:</span> 6xxx - Beban Penyusutan Aset</li>
                <li><span class="font-semibold text-rose-600">Kredit:</span> 12xx - Akumulasi Penyusutan Aset</li>
            </ul>
        </div>
    </div>

    <div class="bg-warning-50 dark:bg-warning-500/10 p-4 rounded-xl text-warning-700 dark:text-warning-400 mt-2">
        <div class="flex items-start gap-2">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 mt-0.5 shrink-0" />
            <p><b>Penting:</b> Pastikan semua data transaksi sudah benar dan <b>Nilai Stock Opname</b> sudah diinput sebelum Anda menekan tombol Tutup Buku. Periode yang sudah CLOSED tidak bisa dibuka kembali secara normal.</p>
        </div>
    </div>
</div>
