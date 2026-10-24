<?php

namespace App\Http\Controllers;

use App\Models\{Placement, Journal, Assessment, Certificate, AuditLog, InternshipPeriod, InternshipRoster};
use Illuminate\Http\Request;

class ReportController
{
    private const TYPES = ['rosters'=>'Seluruh Siswa / Roster', 'placements'=>'Penempatan PKL',
        'journals'=>'Jurnal Harian', 'assessments'=>'Nilai Siswa', 'certificates'=>'Sertifikat'];

    private function filters(Request $request): array
    {
        $data=$request->validate(['period_id'=>'nullable|integer|min:1|exists:internship_periods,id',
            'major'=>'nullable|string|max:150', 'page'=>'nullable|integer|min:1']);
        $periods=InternshipPeriod::orderByDesc('academic_year')->orderByDesc('id')->get();
        $chosen=(int)($data['period_id']??$periods->firstWhere('academic_year',InternshipRoster::currentAcademicYear())?->id??$periods->first()?->id);
        $major=$data['major']??'';
        $majors=InternshipRoster::where('internship_period_id',$chosen)->distinct()->orderBy('major')->pluck('major');
        // Preserve an unmatched filter rather than silently exporting another major.
        if ($major!=='' && !$majors->contains($major)) $majors->push($major);
        return compact('periods','chosen','major','majors');
    }

    private function rosters(array $filters)
    {
        return InternshipRoster::where('internship_period_id',$filters['chosen'])
            ->when($filters['major']!=='',fn($q)=>$q->where('major',$filters['major']));
    }

    private function placements(array $filters)
    {
        return Placement::where('internship_period_id',$filters['chosen'])
            ->when($filters['major']!=='',fn($q)=>$q->whereHas('roster',fn($r)=>$r->where('major',$filters['major'])
                ->whereColumn('internship_rosters.student_id','placements.student_id')
                ->whereColumn('internship_rosters.internship_period_id','placements.internship_period_id')));
    }

    public function index(Request $request)
    {
        $filters=$this->filters($request);
        return view('reports.index',$filters+[
            'types'=>self::TYPES,
            'rosterCount'=>$this->rosters($filters)->count(),
            'placementCount'=>$this->placements($filters)->count(),
            'audits'=>AuditLog::with('actor')->latest()->orderByDesc('id')->paginate(25)->withQueryString(),
        ]);
    }

    private function linkedRoster(Placement $placement): ?InternshipRoster
    {
        $roster=$placement->roster;
        return $roster && (int)$roster->student_id===(int)$placement->student_id
            && (int)$roster->internship_period_id===(int)$placement->internship_period_id ? $roster : null;
    }

    public function export(Request $request, string $type)
    {
        abort_unless(isset(self::TYPES[$type]),404);
        $filters=$this->filters($request);
        $today=now('Asia/Jakarta')->startOfDay();
        $placements=$this->placements($filters);
        $ids=(clone $placements)->select('id');
        $query=match($type) {
            'rosters'=>$this->rosters($filters)->with(['placement.company','placement.teacher']),
            'placements'=>$placements->with(['student.user','company','teacher','period','roster']),
            'journals'=>Journal::whereIn('placement_id',clone $ids)->with(['placement.student.user','placement.period','placement.roster']),
            'assessments'=>Assessment::whereIn('placement_id',clone $ids)->with(['placement.student.user','placement.period','placement.roster']),
            'certificates'=>Certificate::whereIn('placement_id',clone $ids)->with(['placement.student.user','placement.period','placement.roster']),
        };
        $headers=match($type) {
            'rosters'=>['ID Roster','Tahun Ajaran','NIS','NISN','Nama','Kelas','Jurusan','Mulai PKL','Selesai PKL','Fase PKL','Status Penempatan','ID Penempatan','Perusahaan','Guru Pembimbing','Tanggal Rekap WIB'],
            'placements'=>['ID','NIS','Nama','Perusahaan','Periode','Guru Pembimbing','Status','Kelas','Jurusan','Mulai PKL','Selesai PKL','Tanggal Rekap WIB'],
            'journals'=>['ID','Siswa','Tanggal','Kegiatan','Hasil','Status','Catatan Review','NIS','Tahun Ajaran','Kelas','Jurusan'],
            'assessments'=>['ID','Siswa','Nilai Akhir','Status','Catatan','NIS','Tahun Ajaran','Kelas','Jurusan'],
            'certificates'=>['ID','Nomor','Siswa','Versi','Status','Jadwal Pengambilan','NIS','Tahun Ajaran','Kelas','Jurusan'],
        };
        $year=$filters['periods']->firstWhere('id',$filters['chosen'])?->academic_year??'kosong';
        $filename='simmag-'.$type.'-'.str_replace('/','-',$year).'-'.$today->format('Ymd').'.csv';
        return response()->streamDownload(function() use ($query,$type,$headers,$today) {
            $out=fopen('php://output','w');
            fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,$headers,',','"','');
            // Bounded memory and eager loading per chunk, including the school's unplaced roster.
            foreach ($query->lazyById(250) as $item) {
                if ($type==='rosters') {
                    $placement=$item->placement;
                    $valid=$placement && (int)$placement->student_id===(int)$item->student_id
                        && (int)$placement->internship_period_id===(int)$item->internship_period_id;
                    $values=[$item->id,$item->academic_year,$this->identifier($item->nis),$this->identifier($item->nisn),
                        $item->name,$item->class_name,$item->major,$item->internship_starts_at?->toDateString(),
                        $item->internship_ends_at?->toDateString(),$item->lifecycleLabel($today),
                        $valid?'Sudah ditempatkan':($placement?'Tautan penempatan perlu diperiksa':'Belum ditempatkan'),
                        $valid?$placement->id:null,$valid?$placement->company?->name:null,$valid?$placement->teacher?->name:null,$today->toDateString()];
                } else {
                    $placement=$type==='placements'?$item:$item->placement;
                    $roster=$this->linkedRoster($placement);
                    $name=$roster?->name??$placement->student->user->name;
                    $nis=$this->identifier($roster?->nis??$placement->student->nis);
                    $year=$placement->period?->academic_year;
                    $class=$roster?->class_name??'Roster belum terhubung';
                    $major=$roster?->major??'Roster belum terhubung';
                    $values=match($type) {
                        'placements'=>[$item->id,$nis,$name,$item->company?->name,$year,$item->teacher?->name,
                            $roster?->lifecycleLabel($today)??'Roster belum terhubung',$class,$major,
                            $roster?->internship_starts_at?->toDateString(),$roster?->internship_ends_at?->toDateString(),$today->toDateString()],
                        'journals'=>[$item->id,$name,$item->activity_date->toDateString(),$item->activity,$item->outcome,$item->status,$item->review_note,$nis,$year,$class,$major],
                        'assessments'=>[$item->id,$name,$item->final_score,$item->status,$item->notes,$nis,$year,$class,$major],
                        'certificates'=>[$item->id,$item->number,$name,$item->version,$item->status,$item->pickup_at?->format('Y-m-d H:i'),$nis,$year,$class,$major],
                    };
                }
                fputcsv($out,array_map(fn($v)=>$this->csvValue($v),$values),',','"','');
            }
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    private function identifier(?string $value): string
    {
        // Text marker protects leading zeroes and prevents an identifier becoming a formula.
        return $value===null || $value==='' ? '' : "'".$value;
    }

    private function csvValue(mixed $value): string
    {
        $text=(string)($value??'');
        return preg_match('/^(?:[\t\r\n]|[\s\x{FEFF}]*[=+\-@])/u',$text) ? "'".$text : $text;
    }
}
