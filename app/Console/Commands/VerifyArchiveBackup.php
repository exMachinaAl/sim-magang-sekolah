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
        $this->line('Foto jurnal: '.$report['restored_photos']['attachments'].' lampiran / '.$report['restored_photos']['unique_files'].' berkas unik dipulihkan dan hash cocok.');
        $this->line('ZIP foto: '.dirname($report['backup_path']).DIRECTORY_SEPARATOR.$report['photo_backup_file']);
        $this->warn('Cakupan: database, arsip dan foto jurnal pada lokasi terpisah di komputer ini. Pemulihan server baru dan beban produksi masih perlu diuji.');
        return self::SUCCESS;
    }
}
