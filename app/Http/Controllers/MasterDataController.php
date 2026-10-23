<?php
namespace App\Http\Controllers;
use App\Models\{User,Student,Company,InternshipPeriod,InternshipRoster,Placement};
use App\Support\{Audit,Access};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class MasterDataController {
 public function students(Request $r){
  $r->validate(['academic_year'=>['nullable','regex:/^\d{4}\/\d{4}$/'],'q'=>'nullable|string|max:100']);
  $academicYear=$r->academic_year ?: InternshipRoster::currentAcademicYear();
  $majors=collect(InternshipRoster::officialMajors());
  $items=Student::with(['user','rosters'=>fn($q)=>$q->orderByDesc('academic_year')])->when($r->q,fn($q)=>$q->where('nis','like','%'.$r->q.'%')->orWhereHas('user',fn($q)=>$q->where('name','like','%'.$r->q.'%')))->latest()->paginate(15)->withQueryString();
  return view('masters.students',compact('items','majors','academicYear'));
 }
 public function storeStudent(Request $r){
  $d=$r->validate(['name'=>'required|string|max:150','email'=>'nullable|email|unique:users,email',
   'birth_date'=>'required|date_format:Y-m-d|before:today','nis'=>'required|string|max:30|unique:students,nis','nisn'=>'nullable|max:30','class_name'=>'required|max:70',
   'academic_year'=>['nullable','regex:/^\d{4}\/\d{4}$/'],'major'=>['required',Rule::in(InternshipRoster::officialMajors())]]);
  app(\App\Support\StudentEnrollment::class)->register($d,$r->user()->id);
  return back()->with('success','Data dan akun siswa dibuat. Login dengan NIS; password awal tanggal lahir (DDMMYYYY).');
 }
 public function updateStudent(Request $r,Student $student){
  $d=$r->validate(['name'=>'required|string|max:150',
   'email'=>['nullable','email',Rule::unique('users','email')->ignore($student->user_id)],
   'birth_date'=>'nullable|date_format:Y-m-d|before:today',
   'nis'=>['required','max:30',Rule::unique('students','nis')->ignore($student->id)],
   'nisn'=>'nullable|max:30','class_name'=>'required|max:70','major'=>['required',Rule::in(InternshipRoster::officialMajors())]]);
  DB::transaction(function()use($d,$student){
   $student=Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
   if ($d['nis'] !== $student->nis && InternshipRoster::where('nis',$d['nis'])->exists()) {
    throw \Illuminate\Validation\ValidationException::withMessages(['nis'=>'NIS tujuan sudah digunakan di Kontrol PKL. Periksa identitas terlebih dahulu.']);
   }
   $old=['user'=>$student->user->only('name','email'),'student'=>$student->only('nis','nisn','class_name','major')];
   $student->user->update(['name'=>$d['name'],'email'=>$d['email']??null]);
   InternshipRoster::where('nis',$student->nis)->update(['nis'=>$d['nis'],'nisn'=>$d['nisn']??null,'name'=>$d['name'],'email'=>$d['email']??null,'student_id'=>$student->id,'updated_by'=>auth()->id()]);
   $student->update(collect($d)->only(['nis','nisn','class_name','major','birth_date'])->all());
   $latest=$student->rosters()->orderByDesc('academic_year')->lockForUpdate()->first();
   if ($latest) {
    $latest->update(['class_name'=>$d['class_name'],'major'=>$d['major']]);
   } else {
    app(\App\Support\StudentEnrollment::class)->register($d,auth()->id());
   }
   Audit::record('student.updated',$student,['before'=>$old,'after'=>collect($d)->except(['email','birth_date'])->all()]);
  });
  return back()->with('success','Data siswa berhasil diperbarui.');
 }
 public function companies(Request $r){
  $items=Company::when($r->q,fn($q)=>$q->where('name','like','%'.$r->q.'%'))->latest()->paginate(15)->withQueryString();
  return view('masters.companies',compact('items'));
 }
 public function storeCompany(Request $r){
  $d=$r->validate(['name'=>'required|max:180','address'=>'nullable|max:2000','contact'=>'nullable|max:150']);
  $item=Company::create($d);Audit::record('company.created',$item);
  return back()->with('success','Perusahaan berhasil ditambahkan.');
 }
 public function updateCompany(Request $r,Company $company){
  $d=$r->validate(['name'=>'required|string|max:180','address'=>'nullable|string|max:2000','contact'=>'nullable|string|max:150']);
  $old=$company->only(array_keys($d));$company->update($d);
  Audit::record('company.updated',$company,['before'=>$old,'after'=>$d]);
  return back()->with('success','Perusahaan berhasil diperbarui.');
 }
 public function periods(){return view('masters.periods',['items'=>InternshipPeriod::withCount(['rosters','placements'])->orderByDesc('academic_year')->paginate(15)]);}
 public function storePeriod(Request $r){
  $d=$r->validate(['name'=>['required','regex:/^\d{4}\/\d{4}$/',Rule::unique('internship_periods','academic_year')],'starts_at'=>'prohibited','ends_at'=>'prohibited']);
  $p=InternshipPeriod::forAcademicYear($d['name']); Audit::record('period.tag_created',$p);
  return back()->with('success','Tag tahun ajaran dibuat. Tanggal PKL tetap diatur per siswa di Kontrol PKL.');
 }
 public function updatePeriod(Request $r,InternshipPeriod $period){
  $d=$r->validate(['name'=>['required','regex:/^\d{4}\/\d{4}$/',Rule::unique('internship_periods','academic_year')->ignore($period->id)],
   'is_active'=>'required|boolean','starts_at'=>'prohibited','ends_at'=>'prohibited']);
  if((int)substr($d['name'],5)!==(int)substr($d['name'],0,4)+1) return back()->withErrors(['name'=>'Tahun ajaran harus berurutan.']);
  DB::transaction(function()use($period,$d){
   $locked=InternshipPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
   if($d['name']!==$locked->academic_year && ($locked->rosters()->exists() || $locked->placements()->exists())){
    throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Tag yang sudah dipakai tidak dapat diganti tahunnya. Buat tag baru untuk tahun lain.']);
   }
   $locked->update(['name'=>$d['name'],'academic_year'=>$d['name'],'is_active'=>$d['is_active']]);
   Audit::record('period.tag_updated',$locked,$d);
  });
  return back()->with('success','Tag diperbarui. Ketersediaan tag tidak mengubah status PKL siswa.');
 }
}

