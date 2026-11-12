<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class StudentLogin
{
    public static function fromNis(?string $nis): string
    {
        $username = preg_replace('/\D+/', '', (string) $nis) ?? '';
        if ($username === '') {
            throw ValidationException::withMessages([
                'nis' => 'NIS harus mengandung angka agar username siswa dapat dibuat.',
            ]);
        }

        return $username;
    }
}
