<?php

namespace App\Http\Controllers;

use App\Models\{InternshipPeriod, InternshipRoster, InternshipScheduleBatch, Student};
use App\Support\{Audit, StudentEnrollment};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InternshipRosterController
{
    public function index(Request $request)
    {
        $request->validate([
            'q'=>'nullable|string|max:100', 'major'=>'nullable|string|max:120',
            'academic_year'=>['nullable','regex:/^\d{4}\/\d{4}$/'],
            'batch'=>'nullable|boolean', 'roster_id'=>'nullable|integer',
        ]);
        $search = trim((string) $request->q);
        $request->merge(['q'=>$search]);
        $selectedMajor = $request->major ?: null;
        $batchMode = $request->boolean('batch');
        $showStudents = $selectedMajor || $search !== '' || $request->filled('roster_id');
        $academicYear = $request->academic_year ?: $this->currentAcademicYear();
        $base = InternshipRoster::query()->where('academic_year', $academicYear);
        $allForYear = (clone $base)->get();
        $lifecycleCounts = $allForYear->countBy(fn (InternshipRoster $roster) => $roster->lifecycleState());
        $totals = [
            'all' => (clone $base)->count(),
            'internship' => (clone $base)->where('internship_status', 'internship')->count(),
            'not_internship' => (clone $base)->where('internship_status', 'not_internship')->count(),
            'pending' => (clone $base)->where('internship_status', 'pending')->count(),
            'scheduled' => $lifecycleCounts->get('scheduled', 0),
            'pkl' => $lifecycleCounts->get('pkl', 0),
            'school' => $lifecycleCounts->get('school', 0),
            'unscheduled' => $lifecycleCounts->get('unscheduled', 0),
        ];
        $totals['percentage'] = $totals['all'] > 0
            ? round(($totals['internship'] / $totals['all']) * 100, 1)
            : 0;

        $majorStats = collect(InternshipRoster::officialMajors())->merge($allForYear->pluck('major'))->unique()->map(function ($major) use ($allForYear) {
            $rows = $allForYear->where('major', $major);
            $states = $rows->countBy(fn (InternshipRoster $roster) => $roster->lifecycleState());
            return (object) [
                'major' => $major,
                'total' => $rows->count(),
                'internship_count' => $rows->where('internship_status', 'internship')->count(),
                'not_internship_count' => $rows->where('internship_status', 'not_internship')->count(),
                'pending_count' => $rows->where('internship_status', 'pending')->count(),
                'scheduled_count' => $states->get('scheduled', 0),
                'pkl_count' => $states->get('pkl', 0),
                'school_count' => $states->get('school', 0),
                'unscheduled_count' => $states->get('unscheduled', 0),
            ];
        })->sortBy('major')->values();

        $items = $this->filteredQuery($request, $academicYear)
            ->when(! $showStudents, fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['period', 'updater', 'scheduleBatch.creator'])->orderBy('major')->orderBy('class_name')->orderBy('name')
            ->paginate(25)->withQueryString();
        $academicYears = InternshipRoster::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year');
        if (! $academicYears->contains($academicYear)) {
            $academicYears->prepend($academicYear);
        }

        return view('internships.index', [
            'items' => $items,
            'totals' => $totals,
            'majorStats' => $majorStats,
            'visibleMajorStats' => $majorStats->filter(fn ($stat) => $search === '' || str_contains(mb_strtolower($stat->major), mb_strtolower($search)) || $this->normalizeMajor($search) === $stat->major),
            'selectedMajor' => $selectedMajor, 'search' => $search, 'batchMode' => $batchMode, 'showStudents' => $showStudents,
            'defaultRosterIds' => $batchMode && $selectedMajor ? (clone $base)->where('major',$selectedMajor)->pluck('id')->all() : [],
            'majors' => collect(InternshipRoster::officialMajors()),
            'batchCandidates' => $batchMode ? (clone $base)->orderBy('major')->orderBy('class_name')->orderBy('name')->get() : collect(),
            'recentBatches' => InternshipScheduleBatch::with('creator')->where('academic_year', $academicYear)
                ->latest()->limit(8)->get(),
            'periods' => InternshipPeriod::orderByDesc('academic_year')->get(),
            'academicYears' => $academicYears,
            'academicYear' => $academicYear,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedRoster($request);
        if (! empty($data['internship_starts_at'])) {
            $data['internship_status'] = 'internship';
            $data['schedule_source'] = 'individual_override';
        } elseif ($data['internship_status'] !== 'internship') {
            $data['internship_starts_at'] = null;
            $data['internship_ends_at'] = null;
        }
        DB::transaction(function () use ($data, $request) {
            $student = app(StudentEnrollment::class)->enroll($data);
            unset($data['birth_date']);
            $data['student_id'] = $student->id;
            $data['name'] = $student->user->name;
            $data['email'] = $student->user->email;
            $data['updated_by'] = $request->user()->id;
            $roster = InternshipRoster::create($data);
            app(StudentEnrollment::class)->syncLatestRoster($student);
            Audit::record('internship_roster.created', $roster, ['status' => $roster->internship_status]);
        });

        return back()->with('success', 'Siswa dan akun terhubung ke roster. Login dengan NIS; password akun baru: tanggal lahir DDMMYYYY. Password akun lama tidak diubah.');
    }

    public function update(Request $request, InternshipRoster $roster)
    {
        $data = $this->validatedStatus($request);
        $before = $roster->only(['internship_status', 'internship_starts_at', 'internship_ends_at', 'schedule_source', 'schedule_batch_id', 'notes']);
        if ($data['internship_status'] !== 'internship') {
            $data['internship_starts_at'] = null;
            $data['internship_ends_at'] = null;
            $data['schedule_source'] = null;
            $data['schedule_batch_id'] = null;
        } elseif (! empty($data['internship_starts_at'])) {
            $data['schedule_source'] = 'individual_override';
            $data['schedule_batch_id'] = null;
        } else {
            $data['schedule_source'] = null;
            $data['schedule_batch_id'] = null;
        }
        DB::transaction(function () use ($roster, $data, $request, $before) {
            if (! $roster->student_id && (! empty($data['birth_date']) || $data['internship_status'] === 'internship')) {
                $student = app(StudentEnrollment::class)->enroll($roster->only(['nis', 'nisn', 'name', 'email', 'class_name', 'major']) + ['birth_date' => $data['birth_date'] ?? null]);
                $data['student_id'] = $student->id;
                $data['name'] = $student->user->name;
                $data['email'] = $student->user->email;
            }
            unset($data['birth_date']);
            $roster->update($data + ['updated_by' => $request->user()->id]);
            Audit::record('internship_roster.updated', $roster, ['before' => $before, 'after' => $data]);
        });

        return back()->with('success', 'Status magang siswa berhasil diperbarui.');
    }

    public function scheduleBatch(Request $request)
    {
        $data = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'name' => ['required', 'string', 'max:150'],
            'selected_majors' => ['required', 'array', 'min:1', 'max:8'],
            'selected_majors.*' => ['string', Rule::in(InternshipRoster::officialMajors())],
            'roster_ids' => ['required', 'array', 'min:1', 'max:5000'],
            'roster_ids.*' => ['integer', 'distinct', 'exists:internship_rosters,id'],
            'internship_starts_at' => ['required', 'date'],
            'internship_ends_at' => ['required', 'date', 'after_or_equal:internship_starts_at'],
            'overwrite_batch_schedules' => ['nullable', 'boolean'],
        ]);

        $overwrite = (bool) ($data['overwrite_batch_schedules'] ?? false);
        $scope = count($data['selected_majors']) === count(InternshipRoster::officialMajors())
            ? 'school'
            : (count($data['selected_majors']) === 1 ? 'major' : 'custom');

        [$batch, $applied, $skipped] = DB::transaction(function () use ($data, $overwrite, $scope, $request) {
            $rosters = InternshipRoster::query()
                ->where('academic_year', $data['academic_year'])
                ->whereIn('major', $data['selected_majors'])
                ->whereIn('id', $data['roster_ids'])
                ->lockForUpdate()
                ->get();
            if ($rosters->count() !== count($data['roster_ids'])) {
                throw ValidationException::withMessages([
                    'roster_ids' => 'Pilihan siswa tidak sesuai dengan tahun ajaran atau jurusan yang dipilih.',
                ]);
            }
            $targets = $rosters->filter(function (InternshipRoster $roster) use ($overwrite) {
                if ($roster->schedule_source === 'individual_override') {
                    return false;
                }
                return $overwrite || ! $roster->internship_starts_at;
            })->values();
            $batch = InternshipScheduleBatch::create([
                'academic_year' => $data['academic_year'],
                'name' => $data['name'],
                'scope' => $scope,
                'selected_majors' => array_values($data['selected_majors']),
                'starts_at' => $data['internship_starts_at'],
                'ends_at' => $data['internship_ends_at'],
                'student_count' => $targets->count(),
                'created_by' => $request->user()->id,
            ]);
            $source = $scope === 'school' ? 'school_batch' : ($scope === 'major' ? 'major_batch' : 'custom_batch');
            foreach ($targets as $roster) {
                $before = $roster->only(['internship_status', 'internship_starts_at', 'internship_ends_at', 'schedule_source', 'schedule_batch_id']);
                $roster->update([
                    'internship_status' => 'internship',
                    'internship_starts_at' => $data['internship_starts_at'],
                    'internship_ends_at' => $data['internship_ends_at'],
                    'schedule_source' => $source,
                    'schedule_batch_id' => $batch->id,
                    'updated_by' => $request->user()->id,
                ]);
                Audit::record('internship_roster.schedule_applied', $roster, [
                    'batch_id' => $batch->id, 'before' => $before,
                ]);
            }
            Audit::record('internship_schedule_batch.created', $batch, [
                'student_count' => $targets->count(), 'majors' => $data['selected_majors'],
            ]);
            return [$batch, $targets->count(), $rosters->count() - $targets->count()];
        });

        $message = "Jadwal {$batch->name} diterapkan ke {$applied} siswa.";
        if ($skipped) {
            $message .= " {$skipped} siswa dilewati karena memiliki jadwal individual atau jadwal batch yang tidak diizinkan ditimpa.";
        }

        return back()->with('success', $message);
    }

    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'roster_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'roster_ids.*' => ['integer', 'exists:internship_rosters,id'],
            'internship_status' => ['required', Rule::in(array_keys(InternshipRoster::statuses()))],
            'internship_period_id' => ['nullable', 'exists:internship_periods,id'],
        ]);
        $periodId = $data['internship_status'] === 'internship' ? ($data['internship_period_id'] ?? null) : null;
        $rosters = InternshipRoster::whereIn('id', $data['roster_ids'])->get();
        DB::transaction(function () use ($rosters, $data, $periodId, $request): void {
            foreach ($rosters as $roster) {
                $before = $roster->internship_status;
                $roster->update([
                    'internship_status' => $data['internship_status'],
                    'internship_period_id' => $periodId,
                    'internship_starts_at' => $data['internship_status'] === 'internship' ? $roster->internship_starts_at : null,
                    'internship_ends_at' => $data['internship_status'] === 'internship' ? $roster->internship_ends_at : null,
                    'schedule_source' => $data['internship_status'] === 'internship' ? $roster->schedule_source : null,
                    'schedule_batch_id' => $data['internship_status'] === 'internship' ? $roster->schedule_batch_id : null,
                    'updated_by' => $request->user()->id,
                ]);
                Audit::record('internship_roster.bulk_updated', $roster, [
                    'status_before' => $before, 'status_after' => $data['internship_status'],
                ]);
            }
        });

        return back()->with('success', $rosters->count().' siswa berhasil diperbarui secara massal.');
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'roster_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);
        [$headers, $rows] = $this->readCsv($request->file('roster_file')->getRealPath());
        $required = ['nis', 'nama', 'kelas', 'jurusan'];
        $missing = array_values(array_diff($required, $headers));
        if ($missing) {
            throw ValidationException::withMessages([
                'roster_file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'.',
            ]);
        }
        if (count($rows) > 5000) {
            throw ValidationException::withMessages(['roster_file' => 'Satu impor dibatasi maksimal 5.000 siswa.']);
        }

        $prepared = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if (! array_filter($row, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }
            $academicYear = trim($row['tahun_ajaran'] ?? '') ?: $data['academic_year'];
            $nis = trim($row['nis'] ?? '');
            $key = $academicYear.'|'.$nis;
            if (isset($seen[$key])) $errors[] = "Baris {$line}: NIS {$nis} pada tahun {$academicYear} berulang (sebelumnya baris {$seen[$key]}).";
            $seen[$key] = $line;
            $name = trim($row['nama'] ?? '');
            $className = trim($row['kelas'] ?? '');
            $rawMajor = trim($row['jurusan'] ?? '');
            $major = $this->normalizeMajor($rawMajor);
            $rawStatus = trim($row['status_magang'] ?? '');
            $status = $rawStatus === '' ? null : $this->normalizeStatus($rawStatus);
            if (! preg_match('/^\d{4}\/\d{4}$/', $academicYear)) $errors[] = "Baris {$line}: tahun_ajaran tidak valid.";
            if ($nis === '' || mb_strlen($nis) > 30) $errors[] = "Baris {$line}: NIS wajib dan maksimal 30 karakter.";
            if ($name === '') $errors[] = "Baris {$line}: nama wajib diisi.";
            if ($className === '') $errors[] = "Baris {$line}: kelas wajib diisi.";
            if ($rawMajor === '') {
                $errors[] = "Baris {$line}: jurusan wajib diisi.";
            } elseif ($major === null) {
                $errors[] = "Baris {$line}: jurusan '{$rawMajor}' tidak termasuk delapan jurusan resmi.";
            }
            if ($rawStatus !== '' && $status === null) $errors[] = "Baris {$line}: status_magang tidak dikenali.";
            $email = trim($row['email'] ?? '');
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Baris {$line}: email tidak valid.";

            $periodId = null;
            $periodName = trim($row['periode'] ?? '');
            if ($periodName !== '') {
                $periodId = InternshipPeriod::where('name', $periodName)->value('id');
                if (! $periodId) $errors[] = "Baris {$line}: periode '{$periodName}' tidak ditemukan.";
                elseif (InternshipPeriod::find($periodId)->academic_year !== $academicYear) $errors[] = "Baris {$line}: tag periode harus sesuai tahun_ajaran.";
            }
            $startsAt = trim($row['mulai_pkl'] ?? '') ?: null;
            $endsAt = trim($row['selesai_pkl'] ?? '') ?: null;
            if (($startsAt && ! $endsAt) || (! $startsAt && $endsAt)) $errors[] = "Baris {$line}: mulai_pkl dan selesai_pkl harus diisi berpasangan.";
            $validation = Validator::make([
                'nama' => $name, 'kelas' => $className, 'nisn' => trim($row['nisn'] ?? ''),
                'email' => $email, 'catatan' => trim($row['catatan'] ?? ''),
                'tanggal_lahir' => trim($row['tanggal_lahir'] ?? '') ?: null,
                'mulai_pkl' => $startsAt, 'selesai_pkl' => $endsAt,
            ], [
                'nama' => 'required|string|max:150', 'kelas' => 'required|string|max:70',
                'nisn' => 'nullable|string|max:30', 'email' => 'nullable|email|max:255',
                'catatan' => 'nullable|string|max:2000',
                'tanggal_lahir' => 'nullable|date_format:Y-m-d|before:today',
                'mulai_pkl' => 'nullable|date_format:Y-m-d',
                'selesai_pkl' => 'nullable|date_format:Y-m-d|after_or_equal:mulai_pkl',
            ]);
            foreach ($validation->errors()->all() as $error) $errors[] = "Baris {$line}: {$error}";
            $prepared[] = [
                '_line' => $line,
                'birth_date' => trim($row['tanggal_lahir'] ?? '') ?: null,
                'academic_year' => $academicYear, 'nis' => $nis, 'nisn' => trim($row['nisn'] ?? '') ?: null,
                'name' => $name, 'email' => $email ?: null, 'class_name' => $className, 'major' => $major ?: $rawMajor,
                'internship_status' => $status, 'internship_period_id' => $periodId,
                'internship_starts_at' => $startsAt, 'internship_ends_at' => $endsAt,
                'notes' => trim($row['catatan'] ?? '') ?: null,
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages(['roster_file' => array_slice($errors, 0, 20)]);
        }
        if (! $prepared) {
            throw ValidationException::withMessages(['roster_file' => 'File belum berisi siswa. Isi data di bawah header template sebelum mengimpor.']);
        }

        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($prepared, $request, &$created, &$updated): void {
            foreach ($prepared as $row) {
                try {
                    $student = app(StudentEnrollment::class)->enroll($row);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['roster_file' => 'Baris '.$row['_line'].' (NIS '.$row['nis'].'): '.implode(' ', $exception->validator->errors()->all())]);
                }
                unset($row['birth_date'], $row['_line']);
                $row['name'] = $student->user->name;
                $row['email'] = $student->user->email;
                $row['nisn'] = $student->nisn;
                $status = $row['internship_status'];
                unset($row['internship_status']);
                $existing = InternshipRoster::where('academic_year', $row['academic_year'])->where('nis', $row['nis'])->first();
                $values = $row + [
                    'student_id' => $student->id,
                    'updated_by' => $request->user()->id,
                ];
                if ($status !== null) {
                    $values['internship_status'] = $status;
                    if ($status !== 'internship') $values['internship_period_id'] = null;
                } elseif (! $existing) {
                    $values['internship_status'] = 'pending';
                } else {
                    unset($values['internship_period_id']);
                }
                if ($row['internship_starts_at'] && $row['internship_ends_at']) {
                    $values['internship_status'] = 'internship';
                    $values['schedule_source'] = 'individual_override';
                    $values['schedule_batch_id'] = null;
                } elseif ($status !== null && $status !== 'internship') {
                    $values['internship_starts_at'] = null;
                    $values['internship_ends_at'] = null;
                    $values['schedule_source'] = null;
                    $values['schedule_batch_id'] = null;
                } elseif ($existing) {
                    unset($values['internship_starts_at'], $values['internship_ends_at']);
                }
                $roster = InternshipRoster::updateOrCreate(
                    ['academic_year' => $row['academic_year'], 'nis' => $row['nis']],
                    $values
                );
                app(StudentEnrollment::class)->syncLatestRoster($student);
                $existing ? $updated++ : $created++;
                Audit::record('internship_roster.imported', $roster, ['created' => ! $existing]);
            }
        });

        return back()->with('success', "Impor selesai: {$created} siswa baru dan {$updated} siswa diperbarui.");
    }

    public function export(Request $request)
    {
        $academicYear = $request->academic_year ?: $this->currentAcademicYear();
        $items = $this->filteredQuery($request, $academicYear)->with(['period', 'student'])
            ->orderBy('major')->orderBy('class_name')->orderBy('name')->get();
        $filename = 'data-status-magang-'.str_replace('/', '-', $academicYear).'.csv';

        return response()->streamDownload(function () use ($items): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['tahun_ajaran', 'nis', 'nisn', 'nama', 'email', 'kelas', 'jurusan', 'status_magang', 'mulai_pkl', 'selesai_pkl', 'status_hari_ini', 'periode', 'catatan', 'tanggal_lahir']);
            foreach ($items as $item) {
                fputcsv($output, [
                    $this->safeCsvValue($item->academic_year), $this->excelIdentifier($item->nis),
                    $this->excelIdentifier($item->nisn), $this->safeCsvValue($item->name),
                    $this->safeCsvValue($item->email), $this->safeCsvValue($item->class_name),
                    $this->safeCsvValue($item->major), $this->safeCsvValue($item->internship_status),
                    $item->internship_starts_at?->toDateString(), $item->internship_ends_at?->toDateString(),
                    $this->safeCsvValue($item->lifecycleState()),
                    $this->safeCsvValue($item->period?->name), $this->safeCsvValue($item->notes),
                    $item->student?->birth_date?->toDateString(),
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(['students', 'schedule'])],
            'example' => ['nullable', 'boolean'],
            'academic_year' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
        ]);
        $schedule = ($data['type'] ?? 'students') === 'schedule';
        $example = $request->boolean('example', true);
        $academicYear = $data['academic_year'] ?? $this->currentAcademicYear();
        $filename = ($example ? 'contoh-' : 'template-').'import-siswa'.($schedule ? '-jadwal-pkl' : '').'.csv';

        return response()->streamDownload(function () use ($schedule, $example, $academicYear): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            $headers = ['tahun_ajaran', 'nis', 'nisn', 'nama', 'tanggal_lahir', 'kelas', 'jurusan', 'email', 'catatan'];
            if ($schedule) $headers = array_merge($headers, ['status_magang', 'mulai_pkl', 'selesai_pkl']);
            fputcsv($output, $headers);
            if ($example) {
                foreach (InternshipRoster::officialMajors() as $index => $major) {
                    $number = $index + 1;
                    $row = [$academicYear, 'CONTOH'.str_pad($number, 3, '0', STR_PAD_LEFT), '', 'Contoh Siswa '.$number,
                        '2008-11-07', 'XII 1', $major, '', 'Data contoh: ganti dengan data asli'];
                    if ($schedule) $row = array_merge($row, ['magang', substr($academicYear, 0, 4).'-07-01', substr($academicYear, 0, 4).'-12-31']);
                    fputcsv($output, $row);
                }
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredQuery(Request $request, string $academicYear): Builder
    {
        return InternshipRoster::query()->where('academic_year', $academicYear)
            ->when($request->filled('roster_id'), fn ($query) => $query->whereKey($request->integer('roster_id')))
            ->when($request->major, fn ($query, $major) => $query->where('major', $major))
            ->when($request->internship_status, fn ($query, $status) => $query->where('internship_status', $status))
            ->when($request->lifecycle, function ($query, $state) {
                $today = now()->toDateString();
                match ($state) {
                    'not_internship' => $query->where('internship_status', 'not_internship'),
                    'unscheduled' => $query->where('internship_status', '!=', 'not_internship')->where(fn ($q) => $q->whereNull('internship_starts_at')->orWhereNull('internship_ends_at')),
                    'scheduled' => $query->whereDate('internship_starts_at', '>', $today),
                    'pkl' => $query->whereDate('internship_starts_at', '<=', $today)->whereDate('internship_ends_at', '>=', $today),
                    'school' => $query->whereDate('internship_ends_at', '<', $today),
                    default => null,
                };
            })
            ->when($request->q, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('nis', 'like', '%'.$search.'%')
                        ->orWhere('nisn', 'like', '%'.$search.'%')
                        ->orWhere('major', 'like', '%'.$search.'%');
                    if ($major = $this->normalizeMajor($search)) $nested->orWhere('major', $major);
                });
            });
    }

    private function validatedRoster(Request $request): array
    {
        $data = $request->validate([
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'nis' => ['required', 'string', 'max:30', Rule::unique('internship_rosters')->where('academic_year', $request->academic_year)],
            'nisn' => ['nullable', 'string', 'max:30'], 'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'], 'class_name' => ['required', 'string', 'max:70'],
            'major' => ['required', 'string', Rule::in(InternshipRoster::officialMajors())],
            'internship_status' => ['required', Rule::in(array_keys(InternshipRoster::statuses()))],
            'internship_period_id' => ['nullable', 'exists:internship_periods,id'],
            'internship_starts_at' => ['nullable', 'date', 'required_with:internship_ends_at'],
            'internship_ends_at' => ['nullable', 'date', 'required_with:internship_starts_at', 'after_or_equal:internship_starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['internship_status'] !== 'internship') $data['internship_period_id'] = null;
        return $data;
    }

    private function validatedStatus(Request $request): array
    {
        $data = $request->validate([
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'internship_status' => ['required', Rule::in(array_keys(InternshipRoster::statuses()))],
            'internship_period_id' => ['nullable', 'exists:internship_periods,id'],
            'internship_starts_at' => ['nullable', 'date', 'required_with:internship_ends_at'],
            'internship_ends_at' => ['nullable', 'date', 'required_with:internship_starts_at', 'after_or_equal:internship_starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['internship_status'] !== 'internship') $data['internship_period_id'] = null;
        return $data;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $firstLine = fgets($handle) ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);
        $rawHeaders = fgetcsv($handle, 0, $delimiter) ?: [];
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), $rawHeaders);
        if (count(array_unique($headers)) !== count($headers) || in_array('', $headers, true)) {
            fclose($handle);
            throw ValidationException::withMessages(['roster_file' => 'Header kolom tidak boleh kosong atau berulang. Gunakan template yang disediakan.']);
        }
        $rows = [];
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($values) > count($headers)) {
                fclose($handle);
                throw ValidationException::withMessages(['roster_file' => 'Baris '.(count($rows) + 2).': jumlah kolom melebihi header. Simpan ulang sebagai CSV dari Excel; teks yang mengandung koma harus diapit tanda kutip.']);
            }
            $values = array_pad($values, count($headers), '');
            $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
            if (count($rows) > 5000) break;
        }
        fclose($handle);
        return [$headers, $rows];
    }

    private function normalizeHeader(?string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
        return str_replace([' ', '-'], '_', mb_strtolower(trim($header)));
    }

    private function normalizeStatus(string $status): ?string
    {
        $status = str_replace([' ', '-'], '_', mb_strtolower(trim($status)));
        return match ($status) {
            'magang', 'ya', 'yes', '1', 'internship' => 'internship',
            'tidak_magang', 'tidak', 'no', '0', 'not_internship' => 'not_internship',
            'belum_ditentukan', 'pending', 'belum' => 'pending',
            default => null,
        };
    }

    private function normalizeMajor(string $major): ?string
    {
        $key = mb_strtolower(trim($major));
        $key = preg_replace('/[^a-z0-9]+/u', ' ', $key);
        $key = trim(preg_replace('/\s+/', ' ', $key));

        return match ($key) {
            'teknik instalasi tenaga listrik', 'titl' => 'Teknik Instalasi Tenaga Listrik',
            'teknik kimia industri', 'tki' => 'Teknik Kimia Industri',
            'desain komunikasi visual', 'design komunikasi visual', 'dkv' => 'Desain Komunikasi Visual',
            'analisis pengujian laboratorium', 'analisis pengujian laboraturium', 'apl' => 'Analisis Pengujian Laboratorium',
            'kuliner' => 'Kuliner',
            'teknik komputer dan jaringan', 'teknik komputer jaringan', 'tkj' => 'Teknik Komputer dan Jaringan',
            'teknik pemanasan tata udara dan pendinginan', 'teknik pemanasan tata udara pendinginan', 'tptup' => 'Teknik Pemanasan, Tata Udara, dan Pendinginan',
            'teknik otomasi industri', 'toi' => 'Teknik Otomasi Industri',
            default => null,
        };
    }

    private function safeCsvValue(mixed $value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function excelIdentifier(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? '' : "\t".$value;
    }

    private function currentAcademicYear(): string
    {
        return InternshipRoster::currentAcademicYear();
    }
}
