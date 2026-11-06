<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentTemplate extends Model
{
    protected $fillable = ['name', 'major', 'certificate_heading', 'certificate_statement', 'fields', 'based_on_id', 'created_by', 'is_active'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'is_active' => 'boolean'];
    }

    public function snapshot(): array
    {
        return $this->only(['id', 'name', 'major', 'certificate_heading', 'certificate_statement', 'fields']);
    }

    public static function defaultFields(?string $major = null): array
    {
        $competency = $major ? ' pada bidang '.$major : ' sesuai jurusan dan pekerjaan industri';

        return [
            ['key'=>'field_1', 'label'=>'Soft skills', 'description'=>'Etos kerja, kerja sama, integritas dan tanggung jawab.', 'type'=>'score', 'weight'=>20],
            ['key'=>'field_2', 'label'=>'Hard skills', 'description'=>'Pelaksanaan pekerjaan sesuai prosedur dan keselamatan kerja.', 'type'=>'score', 'weight'=>20],
            ['key'=>'field_3', 'label'=>'Pengembangan hard skills', 'description'=>'Kompetensi khusus'.$competency.'.', 'type'=>'score', 'weight'=>20],
            ['key'=>'field_4', 'label'=>'Kewirausahaan', 'description'=>'Pemahaman kegiatan usaha dan kemandirian di tempat kerja.', 'type'=>'score', 'weight'=>20],
            ['key'=>'field_5', 'label'=>'Laporan PKL', 'description'=>'Nilai laporan yang tercantum pada dokumen penilaian industri.', 'type'=>'score', 'weight'=>20],
        ];
    }
}
