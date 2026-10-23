<?php

namespace App\Http\Controllers;

use App\Models\{Company, InternshipPeriod, InternshipRoster, Placement, User};
use App\Support\{Access, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};

class PlacementController
{
    public function index(Request $request)
    {
        $request->validate(['period_id'=>'nullable|integer|exists:internship_periods,id','q'=>'nullable|string|max:100']);
        $periods = InternshipPeriod::whereNotNull('academic_year')->orderByDesc('academic_year')->get();
        $period = $request->filled('period_id') ? $periods->firstWhere('id',$request->integer('period_id'))
            : ($periods->firstWhere('academic_year',InternshipRoster::currentAcademicYear()) ?? $periods->first());
        $items = Access::placements($request->user())->with(['student.user','company','period','teacher','roster'])
            ->where('internship_period_id',$period?->id ?? 0)
            ->when($request->q, fn ($q,$term) => $q->whereHas('student',fn($s)=>$s->where('nis','like','%'.$term.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$term.'%'))))
            ->latest()->paginate(20)->withQueryString();
        $candidates = InternshipRoster::with(['student.user','placement.company'])
            ->where('internship_period_id',$period?->id ?? 0)
            ->whereHas('student.user',fn($q)=>$q->where('is_active',true))
            ->orderBy('major')->orderBy('class_name')->orderBy('name')->get();
        return view('masters.placements', [
            'items'=>$items,'candidates'=>$candidates,'periods'=>$periods,'period'=>$period,
            'companies'=>Company::orderBy('name')->get(),
            'teachers'=>User::where('role','guru')->where('is_active',true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'roster_ids'=>'required|array|min:1|max:5000','roster_ids.*'=>'required|integer|distinct|exists:internship_rosters,id',
            'internship_period_id'=>'required|integer|exists:internship_periods,id',
            'company_id'=>'required|integer|exists:companies,id',
            'teacher_id'=>['nullable','integer',Rule::exists('users','id')->where('role','guru')->where('is_active',true)],
            'status'=>'prohibited','starts_at'=>'prohibited','ends_at'=>'prohibited',
        ]);
        $count = DB::transaction(function () use ($data) {
            $tag = InternshipPeriod::whereKey($data['internship_period_id'])->lockForUpdate()->firstOrFail();
            if (! $tag->academic_year || ! $tag->is_active) throw ValidationException::withMessages(['internship_period_id'=>'Tag tidak tersedia untuk penempatan baru.']);
            $rosters = InternshipRoster::with('student.user')->whereIn('id',$data['roster_ids'])->orderBy('id')->lockForUpdate()->get();
            foreach ($rosters as $roster) {
                if ($roster->academic_year !== $tag->academic_year || (int)$roster->internship_period_id !== $tag->id
                    || ! $roster->student?->user?->is_active || $roster->internship_status === 'not_internship') {
                    throw ValidationException::withMessages(['roster_ids'=>'Pilihan '.$roster->nis.' tidak sesuai tag, akun belum aktif/terhubung, atau dinyatakan tidak magang. Periksa Kontrol PKL.']);
                }
                if (Placement::where('internship_roster_id',$roster->id)->orWhere(fn($q)=>$q->where('student_id',$roster->student_id)->where('internship_period_id',$tag->id))->exists()) {
                    throw ValidationException::withMessages(['roster_ids'=>'Siswa '.$roster->nis.' sudah memiliki penempatan pada tag ini. Tidak ada penempatan yang ditimpa; muat ulang pilihan.']);
                }
            }
            foreach ($rosters as $roster) {
                $placement = Placement::create([
                    'internship_roster_id'=>$roster->id, 'student_id'=>$roster->student_id,
                    'internship_period_id'=>$tag->id, 'company_id'=>$data['company_id'], 'teacher_id'=>$data['teacher_id'] ?? null,
                ]);
                Audit::record('placement.created',$placement,['roster_id'=>$roster->id,'tag'=>$tag->academic_year]);
            }
            return $rosters->count();
        });
        return back()->with('success', $count.' siswa ditempatkan. Jadwal dan fase PKL tetap mengikuti Kontrol PKL.');
    }

    public function update(Request $request, Placement $placement)
    {
        $data = $request->validate([
            'company_id'=>'required|integer|exists:companies,id',
            'teacher_id'=>['nullable','integer',Rule::exists('users','id')->where('role','guru')->where('is_active',true)],
            'status'=>'prohibited','internship_period_id'=>'prohibited','internship_roster_id'=>'prohibited',
        ]);
        $data['teacher_id'] ??= null;
        DB::transaction(function () use ($placement,$data) {
            $locked = Placement::whereKey($placement->id)->lockForUpdate()->firstOrFail();
            $changed = (int)$locked->company_id !== (int)$data['company_id'] || (int)$locked->teacher_id !== (int)$data['teacher_id'];
            if ($changed && ($locked->journals()->exists() || $locked->assessment()->exists() || $locked->certificate()->exists() || $locked->monitoring()->exists())) {
                throw ValidationException::withMessages(['company_id'=>'Penempatan sudah memiliki dokumen kegiatan/nilai/sertifikat. Perubahan industri atau pembimbing memerlukan proses koreksi terpisah.']);
            }
            $before = $locked->only(['company_id','teacher_id']);
            $locked->update($data);
            Audit::record('placement.updated',$locked,['before'=>$before,'after'=>$data]);
        });
        return back()->with('success','Penempatan diperbarui tanpa mengubah jadwal PKL.');
    }
}
