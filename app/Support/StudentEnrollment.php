<?php

namespace App\Support;

use App\Models\{InternshipRoster, Student, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class StudentEnrollment
{
    // NIS identifies one person across academic years. Never reset an existing password on import.
    public function enroll(array $data): Student
    {
        Validator::make($data, ['major' => ['required', Rule::in(InternshipRoster::officialMajors())]])->validate();
        return DB::transaction(function () use ($data) {
            $student = Student::with('user')->where('nis', $data['nis'])->lockForUpdate()->first();
            if ($student) {
                if (! empty($data['birth_date'])) {
                    Validator::make($data, ['birth_date'=>'required|date_format:Y-m-d|before:today'])->validate();
                    if ($student->birth_date && $student->birth_date->toDateString() !== $data['birth_date']) {
                        throw ValidationException::withMessages(['birth_date'=>'Tanggal lahir NIS '.$data['nis'].' berbeda dari profil. Koreksi melalui Data Siswa.']);
                    }
                    if (! $student->birth_date) $student->update(['birth_date'=>$data['birth_date']]);
                }
                $this->linkRosters($student);
                return $student;
            }
            Validator::make($data, [
                'nis'=>'required|string|max:30', 'name'=>'required|string|max:150',
                'birth_date'=>'required|date_format:Y-m-d|before:today',
                'class_name'=>'required|string|max:70', 'major'=>'required|string|max:120',
                'email'=>'nullable|email|max:255|unique:users,email',
            ], ['birth_date.required'=>'Tanggal lahir wajib diisi untuk membuat akun NIS '.$data['nis'].'.'])->validate();
            $user = User::create([
                'name'=>$data['name'], 'email'=>$data['email'] ?? null,
                'password'=>Carbon::createFromFormat('Y-m-d', $data['birth_date'])->format('dmY'),
                'role'=>'siswa', 'is_active'=>true,
            ]);
            $student = Student::create([
                'user_id'=>$user->id, 'nis'=>$data['nis'], 'nisn'=>$data['nisn'] ?? null,
                'birth_date'=>$data['birth_date'], 'class_name'=>$data['class_name'], 'major'=>$data['major'],
            ]);
            Audit::record('student.account_created', $student, ['user_id'=>$user->id]);
            $this->linkRosters($student);
            return $student->load('user');
        });
    }

    private function linkRosters(Student $student): void
    {
        InternshipRoster::where('nis', $student->nis)->whereNull('student_id')->update([
            'student_id' => $student->id, 'name' => $student->user->name, 'email' => $student->user->email,
        ]);
    }

    public function register(array $data, ?int $actorId = null): Student
    {
        return DB::transaction(function () use ($data, $actorId) {
            $student = $this->enroll($data);
            $roster = InternshipRoster::updateOrCreate([
                'academic_year' => $data['academic_year'] ?? InternshipRoster::currentAcademicYear(),
                'nis' => $student->nis,
            ], [
                'student_id' => $student->id, 'name' => $student->user->name,
                'email' => $student->user->email, 'nisn' => $student->nisn,
                'class_name' => $data['class_name'], 'major' => $data['major'], 'updated_by' => $actorId,
            ]);
            $this->syncLatestRoster($student);
            Audit::record('student.registered', $roster, ['academic_year' => $roster->academic_year]);
            return $student;
        });
    }

    // The latest enrollment defines the current profile; older years remain historical snapshots.
    public function syncLatestRoster(Student $student): void
    {
        $latest = $student->rosters()->orderByDesc('academic_year')->first();
        if ($latest) $student->update($latest->only(['class_name', 'major']));
    }
}
