<?php

namespace Database\Seeders;

use App\Models\{InternshipRoster, Student};
use App\Support\StudentEnrollment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StudentPklDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Contoh siswa hanya untuk lingkungan local/testing, bukan produksi.');
        }

        DB::transaction(function () {
            $today = now('Asia/Jakarta')->startOfDay();
            $academicYear = InternshipRoster::currentAcademicYear();
            $names = ['Alya', 'Bima', 'Citra', 'Damar'];
            $enrollment = app(StudentEnrollment::class);
            foreach (InternshipRoster::officialMajors() as $index => $major) {
                foreach ($names as $offset => $name) {
                    $number = $index * 4 + $offset + 1;
                    $nis = 'DEMO-PKL-'.str_pad($number, 3, '0', STR_PAD_LEFT);
                    // Never overwrite a matching identity, password, or schedule on a second run.
                    if (Student::where('nis', $nis)->exists() || InternshipRoster::where('nis', $nis)->exists()) continue;
                    $student = $enrollment->register([
                        'academic_year' => $academicYear, 'nis' => $nis,
                        'name' => $name.' Contoh PKL '.$number, 'birth_date' => '2008-11-07',
                        'class_name' => 'XII '.($index + 1), 'major' => $major,
                    ]);
                    $roster = $student->rosters()->where('academic_year', $academicYear)->firstOrFail();
                    $schedule = match ($offset) {
                        0 => [$today->copy()->subDays(30), $today->copy()->addDays(30)],
                        1 => [$today->copy()->addDays(15), $today->copy()->addDays(105)],
                        2 => [$today->copy()->subDays(100), $today->copy()->subDay()],
                        default => [null, null],
                    };
                    $roster->update([
                        'internship_status' => $offset < 3 ? 'internship' : 'pending',
                        'internship_starts_at' => $schedule[0], 'internship_ends_at' => $schedule[1],
                        'schedule_source' => $offset < 3 ? 'individual_override' : null,
                        'notes' => 'DATA DEMO FIKTIF — StudentPklDemoSeeder, bukan data sekolah asli.',
                    ]);
                }
            }
        });
    }
}
