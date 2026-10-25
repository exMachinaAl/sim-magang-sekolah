<?php

namespace App\Console\Commands;

use App\Support\ArchiveBackupProbe;
use Illuminate\Console\Command;

class VerifyArchiveBackup extends Command
{
    protected $signature='simmag:verify-archive-backup {--mysqldump=mysqldump : Path binary mysqldump} {--mysql=mysql : Path binary mysql}';
    protected $description='Uji backup/pemulihan arsip ke database MySQL lokal sementara; tidak menimpa database kerja.';

    public function handle(ArchiveBackupProbe $probe): int
    {
        try { $report=$probe->run($this->option('mysqldump'),$this->option('mysql')); }
        catch (\Throwable $e) {
            $this->error(get_class($e)===\RuntimeException::class?$e->getMessage():'Uji tidak dapat dijalankan. Periksa koneksi MySQL lokal.');
            return self::FAILURE;
        }
        $this->info('PASS: skema dan isi seluruh tabel hasil pemulihan identik dengan sumber.');
        $this->line('Berkas arsip diperiksa: '.$report['source_integrity']['checked_files']);
        $this->line('Sertifikat terbit lama tanpa arsip aktif: '.$report['source_integrity']['legacy_issued_without_archive'].' (tidak direkonstruksi).');
        $this->line('Database sementara sudah dihapus; database kerja tidak ditimpa.');
        $this->line('Backup: '.$report['backup_path']);
        $this->line('Laporan: '.$report['report_file']);
        $this->warn('Cakupan: arsip di database. Foto jurnal di filesystem, pemulihan server baru, dan transaksi konkuren belum dibuktikan oleh uji ini.');
        return self::SUCCESS;
    }
}
