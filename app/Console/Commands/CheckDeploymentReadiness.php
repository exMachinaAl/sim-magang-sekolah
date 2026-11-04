<?php

namespace App\Console\Commands;

use App\Support\DeploymentReadiness;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CheckDeploymentReadiness extends Command
{
    protected $signature = 'simmag:check-deployment {--target=staging : staging atau production} {--json : Keluarkan JSON tanpa rahasia}';

    protected $description = 'Periksa konfigurasi deployment SIMMAG secara read-only tanpa menampilkan kredensial';

    public function handle(DeploymentReadiness $readiness): int
    {
        try {
            $report = $readiness->inspect((string) $this->option('target'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::INVALID;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Pemeriksaan', 'Jenis', 'Hasil', 'Keterangan'], collect($report['checks'])->map(
                fn ($check, $key) => [$key, $check['level'], $check['passed'] ? 'LULUS' : 'PERLU TINDAKAN', $check['message']]
            )->values()->all());
            $this->line('Target: '.$report['target'].' · gagal wajib: '.$report['required_failures'].' · saran: '.$report['advisories']);
            $this->line('Pemeriksaan read-only; APP_KEY, password, token, dan isi data tidak ditampilkan.');
        }

        return $report['ready'] ? self::SUCCESS : self::FAILURE;
    }
}

