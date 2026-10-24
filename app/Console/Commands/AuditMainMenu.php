<?php

namespace App\Console\Commands;

use App\Models\InternshipRoster;
use App\Support\MainMenuAudit;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AuditMainMenu extends Command
{
    protected $signature = 'simmag:audit-main-menu {--year= : Tag tahun ajaran, default tahun berjalan} {--json : Keluarkan JSON tanpa identitas pribadi}';

    protected $description = 'Periksa indikator sinkronisasi menu utama tanpa mengubah data';

    public function handle(MainMenuAudit $audit): int
    {
        try {
            $result = $audit->inspect($this->option('year') ?: InternshipRoster::currentAcademicYear());
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::INVALID;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }

        $this->info('Audit menu utama — '.$result['academic_year'].' — '.$result['checked_at']);
        $this->warn('Read-only. Status modul berasal dari audit kode '.$result['code_reviewed_at'].'. Angka nol bukan bukti seluruh masalah selesai.');
        $this->table(['Urutan', 'Menu', 'Tag', 'Prioritas'], collect($result['modules'])->map(fn ($module) => [
            $module['order'], $module['name'], match($module['status']) {'red'=>'RED FLAG','stable'=>'STABLE',default=>'UNSTABLE'}, $module['priority'],
        ])->values()->all());
        $this->table(['Indikator', 'Jumlah'], collect($result['metrics'])->map(fn ($value, $key) => [$key, $value])->values()->all());
        $this->table(['Fase Kontrol PKL', 'Jumlah'], collect($result['phases'])->map(fn ($value, $key) => [InternshipRoster::lifecycleLabels()[$key], $value])->values()->all());
        $this->line('Makna indikator dan checklist ulang: docs/AUDIT_MENU_UTAMA.md. Pemeriksaan ini tidak memasang jadwal otomatis.');
        return self::SUCCESS;
    }
}
