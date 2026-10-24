<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class InternshipRoster extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $roster) {
            $roster->internship_period_id = InternshipPeriod::forAcademicYear($roster->academic_year)->id;
        });
    }

    public function placement()
    {
        return $this->hasOne(Placement::class);
    }

    protected $fillable = [
        'academic_year', 'nis', 'nisn', 'name', 'email', 'class_name', 'major',
        'internship_status', 'internship_starts_at', 'internship_ends_at', 'schedule_source',
        'schedule_batch_id', 'internship_period_id', 'student_id', 'updated_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'internship_starts_at' => 'date',
            'internship_ends_at' => 'date',
        ];
    }

    public function period()
    {
        return $this->belongsTo(InternshipPeriod::class, 'internship_period_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scheduleBatch()
    {
        return $this->belongsTo(InternshipScheduleBatch::class, 'schedule_batch_id');
    }

    public static function statuses(): array
    {
        return [
            'pending' => 'Belum ditentukan',
            'internship' => 'Magang',
            'not_internship' => 'Tidak magang',
        ];
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->internship_status] ?? $this->internship_status;
    }

    public static function officialMajors(): array
    {
        return [
            'Teknik Instalasi Tenaga Listrik',
            'Teknik Kimia Industri',
            'Desain Komunikasi Visual',
            'Analisis Pengujian Laboratorium',
            'Kuliner',
            'Teknik Komputer dan Jaringan',
            'Teknik Pemanasan, Tata Udara, dan Pendinginan',
            'Teknik Otomasi Industri',
        ];
    }

    public static function currentAcademicYear(): string
    {
        $today = now('Asia/Jakarta');
        $start = $today->month >= 7 ? $today->year : $today->year - 1;
        return $start.'/'.($start + 1);
    }

    public static function lifecycleLabels(): array
    {
        return [
            'not_internship' => 'Tidak magang',
            'unscheduled' => 'Belum dijadwalkan',
            'scheduled' => 'Terjadwal',
            'pkl' => 'Sedang PKL',
            'school' => 'Masuk sekolah',
        ];
    }

    public function lifecycleState(?CarbonInterface $date = null): string
    {
        if ($this->internship_status === 'not_internship') {
            return 'not_internship';
        }

        if (! $this->internship_starts_at || ! $this->internship_ends_at || $this->internship_ends_at->lt($this->internship_starts_at)) {
            return 'unscheduled';
        }

        $today = ($date ?? now())->copy()->startOfDay();
        if ($today->lt($this->internship_starts_at)) {
            return 'scheduled';
        }
        if ($today->lte($this->internship_ends_at)) {
            return 'pkl';
        }

        return 'school';
    }

    public function lifecycleLabel(?CarbonInterface $date = null): string
    {
        return self::lifecycleLabels()[$this->lifecycleState($date)];
    }

    public function returnToSchoolDate()
    {
        return $this->internship_ends_at?->copy()->addDay();
    }

    public function scheduleSourceLabel(): string
    {
        return match ($this->schedule_source) {
            'school_batch' => 'Batch sekolah',
            'major_batch' => 'Batch jurusan',
            'custom_batch' => 'Batch pilihan',
            'individual_override' => 'Jadwal individual',
            default => 'Belum dijadwalkan',
        };
    }
}
