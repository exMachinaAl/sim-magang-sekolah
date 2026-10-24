<?php
namespace App\Support;

use App\Models\{Certificate, CertificatePrintTemplate};
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule, ValidationException};

class CertificatePrint
{
    public static function fail(string $message): never
    {
        throw ValidationException::withMessages(['certificate'=>$message]);
    }

    public function options(Request $request, CertificatePrintTemplate $template): array
    {
        return $request->validate([
            'print_options.birth_place'=>'required|string|max:100',
            'print_options.bidang_pekerjaan'=>'required|string|max:150',
            'print_options.tanggal_pengesahan'=>'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            'print_options.nama_pimpinan_industri'=>[$template->front_variant==='kepala_sekolah_dan_industri'?'required':'nullable','string','max:150'],
            'print_options.nama_pembimbing'=>[$template->back_variant==='pembimbing_sekolah'?'required':'nullable','string','max:150'],
            'print_options.nip_pembimbing'=>[$template->back_variant==='pembimbing_sekolah'?'required':'nullable','string','max:50'],
            'print_options.nisn_nipd'=>'required|string|max:50',
        ])['print_options'];
    }

    public function validateMapping(array $mapping, array $fields): void
    {
        if (count($mapping)!==5 || array_diff(array_keys(config('certificate_print.mapping')),array_keys($mapping)) || count(array_unique($mapping))!==5 || count($fields)!==5) {
            self::fail('Format rata-rata 80/20 memerlukan tepat empat aspek berbeda dan satu laporan; petakan kelimanya secara eksplisit. Gunakan format bebas untuk aspek lain.');
        }
        foreach ($mapping as $key) {
            $field=collect($fields)->firstWhere('key',$key);
            if (!$field || $field['type']!=='score' || (int)$field['weight']!==20) self::fail('Format 80/20 memerlukan lima aspek angka masing-masing berbobot 20%. Nilai lama tidak diubah otomatis.');
        }
    }

    /** Supplement an archive source with immutable layout metadata and server-derived values. */
    public function source(Certificate $certificate): array
    {
        $template=CertificatePrintTemplate::findOrFail($certificate->print_template_id);
        $p=$certificate->placement; $a=$p->assessment; $options=$certificate->print_options ?? [];
        if (!$a->template_snapshot || (int)$template->assessment_template_id!==(int)$a->assessment_template_id) self::fail('Template cetak harus terhubung ke versi template penilaian siswa ini.');
        if (!$p->student->birth_date) self::fail('Lengkapi tanggal lahir pada Data Siswa sebelum membuat sertifikat.');
        foreach (['birth_place','bidang_pekerjaan','tanggal_pengesahan','nisn_nipd'] as $key) if (empty($options[$key])) self::fail('Data cetak belum lengkap: '.$key);
        if ($template->front_variant==='kepala_sekolah_dan_industri' && empty($options['nama_pimpinan_industri'])) self::fail('Nama pimpinan industri wajib untuk sertifikat dua pihak.');
        if ($template->back_variant==='pembimbing_sekolah' && (empty($options['nama_pembimbing']) || empty($options['nip_pembimbing']))) self::fail('Nama dan NIP penandatangan sekolah harus diisi.');
        $date=\Carbon\Carbon::parse($options['tanggal_pengesahan'],'Asia/Jakarta');
        if ($date->gt(now('Asia/Jakarta')->endOfDay())) self::fail('Tanggal pengesahan tidak boleh di masa depan.');
        $format=fn($n)=>number_format((float)$n,2,',','');
        $values=$template->settings + [
            'nomor_sertifikat'=>$certificate->number, 'nama_siswa'=>$p->student->user->name, 'nis'=>$p->student->nis,
            'nisn_nipd'=>$options['nisn_nipd'], 'kelas'=>$p->roster->class_name, 'konsentrasi_keahlian'=>$p->roster->major,
            'tempat_tanggal_lahir'=>$options['birth_place'].', '.$p->student->birth_date->locale('id')->translatedFormat('d F Y'),
            'bidang_pekerjaan'=>$options['bidang_pekerjaan'], 'nama_industri'=>$p->company->name,
            'tanggal_mulai_pkl'=>$p->roster->internship_starts_at->locale('id')->translatedFormat('d F Y'),
            'tanggal_selesai_pkl'=>$p->roster->internship_ends_at->locale('id')->translatedFormat('d F Y'),
            'tanggal_pengesahan'=>$date->locale('id')->translatedFormat('d F Y'),
            'nama_pimpinan_industri'=>$options['nama_pimpinan_industri'] ?? '',
            'nama_pembimbing'=>$template->back_variant==='pembimbing_industri' ? $a->industry_assessor : $options['nama_pembimbing'],
            'nip_pembimbing'=>$options['nip_pembimbing'] ?? '',
            'tanggal_penilaian'=>$a->assessed_on->locale('id')->translatedFormat('d F Y'),
            'nilai_akhir_pkl'=>$format($a->final_score), 'nilai_hitung'=>$format($a->calculated_score),
        ];
        $fields=$a->template_snapshot['fields'];
        foreach ($fields as $i=>$field) {
            $n=$i+1; $values['aspek_'.$n]=$field['label']; $values['deskripsi_'.$n]=$field['description'];
            $values['hasil_'.$n]=$field['type']==='score'?$format($a->field_values[$field['key']]):$a->field_values[$field['key']];
            $values['bobot_'.$n]=$field['type']==='score'?$field['weight'].'%':'—';
        }
        $mapping=$template->score_mapping ? array_replace(array_fill_keys(array_keys(config('certificate_print.mapping')),''),$template->score_mapping) : null;
        if ($mapping) {
            $this->validateMapping($mapping,$fields);
            $sum=0;
            foreach ($mapping as $name=>$key) {
                $field=collect($fields)->firstWhere('key',$key); $score=(float)$a->field_values[$key];
                // Do not silently round a fractional aspect to the integer layout requested in the source package.
                if ($name!=='laporan_pkl' && floor($score)!==$score) self::fail('Empat aspek pada format asli harus bilangan bulat; pilih format bebas untuk nilai desimal, jangan membulatkan diam-diam.');
                $values['nilai_'.$name]=$name==='laporan_pkl'?$format($score):(string)(int)$score;
                $values['deskripsi_'.$name]=$field['description'];
                if ($name!=='laporan_pkl') $sum+=$score;
            }
            $values['nilai_rata_rata_aspek']=$format($sum/4);
            $calculated=round(($sum/4)*0.8+(float)$a->field_values[$template->score_mapping['laporan_pkl']]*0.2,2);
            if (abs($calculated-(float)$a->calculated_score)>0.005) self::fail('Hasil hitung 80/20 tidak cocok dengan penilaian disahkan; periksa sumber.');
        }
        foreach ($values as $value) if (str_contains((string)$value,'${')) self::fail('Data cetak mengandung penanda template yang belum terisi.');
        $result=['template_id'=>$template->id,'template_name'=>$template->name,'front_variant'=>$template->front_variant,
            'back_variant'=>$template->back_variant,'front_hash'=>$template->front_hash,'back_hash'=>$template->back_hash,
            'score_mapping'=>$mapping,'values'=>$values];
        // Add only for the new renderer: old DOCX/builtin snapshots remain comparable unchanged.
        if ($template->renderer()==='pdf_background') $result['pdf_background']=[
            'front_hash'=>$template->front_pdf_hash,'back_hash'=>$template->back_pdf_hash,
            'layout'=>app(CertificatePdfBackground::class)->validateLayout($template->pdf_layout??[]),
        ];
        return $result;
    }
}
