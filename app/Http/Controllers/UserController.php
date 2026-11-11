<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class UserController {
 public function index(Request $r){
  $filters=$r->validate(['q'=>'nullable|string|max:100','view'=>['nullable',Rule::in(['available','archived'])]]);
  $teacherOnly=$r->user()->isRole('koordinator');
  $archiveMode=!$teacherOnly&&($filters['view']??'available')==='archived';
  $items=User::when($teacherOnly,fn($q)=>$q->where('role','guru'))
   ->when($archiveMode,fn($q)=>$q->whereNotNull('archived_at'),fn($q)=>$q->whereNull('archived_at'))
   ->when($r->q,fn($q)=>$q->where(function($search)use($r){$search->where('name','like','%'.$r->q.'%')->orWhere('email','like','%'.$r->q.'%')->orWhere('nip','like','%'.$r->q.'%');}))
   ->with('archiver')->orderBy('role')->orderBy('name')->paginate(20)->withQueryString();
  $archivedCount=$teacherOnly?0:User::whereNotNull('archived_at')->count();
  return view('users.index',compact('items','teacherOnly','archiveMode','archivedCount'));
 }
 public function store(Request $r){
  $allowedRoles=$r->user()->isRole('koordinator')?['guru']:array_keys(User::staffRoleLabels());
  $d=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|unique:users,email',
   'nip'=>'nullable|string|max:40|unique:users,nip','password'=>'required|string|min:10','role'=>['required',Rule::in($allowedRoles)]]);
  $u=User::create($d);Audit::record('user.created',$u,['role'=>$u->role]);
  return back()->with('success',$u->role==='guru'?'Akun guru pendamping berhasil ditambahkan dan sudah tersedia pada Penempatan.':'Pengguna berhasil ditambahkan.');
 }
 public function toggle(Request $r,User $user){
  $this->ensureManageable($r,$user);
  abort_if($user->isArchived(),422,'Akun sedang diarsipkan. Pulihkan melalui Arsip Akun sebelum mengubah status.');
  abort_if($user->id===$r->user()->id,422,'Tidak bisa menonaktifkan akun sendiri.');
  abort_if(str_starts_with($user->role,'legacy_'),422,'Akun dari role yang sudah dihentikan hanya disimpan sebagai arsip dan tidak dapat diaktifkan kembali.');
  $user->update(['is_active'=>!$user->is_active]);Audit::record('user.status_changed',$user,['is_active'=>$user->is_active]);
  return back()->with('success','Status pengguna berhasil diperbarui.');
 }
 public function identity(Request $r,User $user){
  $this->ensureManageable($r,$user);
  abort_if($user->isArchived(),422,'Akun sedang diarsipkan. Pulihkan terlebih dahulu sebelum mengubah identitas.');
  abort_unless(in_array($user->role,['admin','koordinator','guru'],true),422,'NIP hanya digunakan untuk akun staf.');
  $d=$r->validate(['nip'=>['nullable','string','max:40',Rule::unique('users','nip')->ignore($user->id)]]);
  $old=$user->nip;$user->update(['nip'=>$d['nip']??null]);Audit::record('user.identity_updated',$user,['nip_before'=>$old,'nip_after'=>$user->nip]);
  return back()->with('success','NIP pengguna berhasil diperbarui.');
 }
 public function archive(Request $request,User $user){
  abort_unless($request->user()->isRole('admin'),403);
  abort_if($user->id===$request->user()->id,422,'Akun sendiri tidak dapat diarsipkan. Gunakan akun Admin lain bila pergantian petugas diperlukan.');
  DB::transaction(function()use($request,$user):void{
   $locked=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
   abort_if($locked->isArchived(),422,'Akun sudah berada di arsip.');
   $before=['is_active'=>$locked->is_active,'role'=>$locked->role];
   $locked->update(['is_active'=>false,'archived_at'=>now(),'archived_by'=>$request->user()->id]);
   Audit::record('user.archived',$locked,['before'=>$before,'recoverable'=>true]);
  });
  return redirect()->route('users.index')->with('success','Akun disembunyikan dan dinonaktifkan. Seluruh data historis tetap tersimpan dan dapat dipulihkan melalui Arsip Akun.');
 }
 public function restore(Request $request,User $user){
  abort_unless($request->user()->isRole('admin'),403);
  DB::transaction(function()use($user):void{
   $locked=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
   abort_unless($locked->isArchived(),422,'Akun tidak berada di arsip.');
   $archive=['archived_at'=>$locked->archived_at?->toIso8601String(),'archived_by'=>$locked->archived_by];
   $locked->update(['is_active'=>false,'archived_at'=>null,'archived_by'=>null]);
   Audit::record('user.restored',$locked,['archive'=>$archive,'is_active'=>false]);
  });
  return redirect()->route('users.index')->with('success','Akun dipulihkan ke daftar utama dalam keadaan nonaktif. Periksa identitas lalu aktifkan secara terpisah bila akun boleh login kembali.');
 }
 public function bulk(Request $request){
  abort_unless($request->user()->isRole('admin'),403);
  $data=$request->validate([
   'user_ids'=>'required|array|min:1|max:200','user_ids.*'=>'required|integer|distinct|exists:users,id',
   'action'=>['required',Rule::in(['activate','deactivate','archive','restore'])],
  ]);
  $count=DB::transaction(function()use($request,$data):int{
   $users=User::whereIn('id',$data['user_ids'])->orderBy('id')->lockForUpdate()->get();
   if($users->count()!==count($data['user_ids'])) throw ValidationException::withMessages(['user_ids'=>'Pilihan akun berubah. Muat ulang daftar sebelum menjalankan aksi massal.']);
   if($users->contains(fn(User $user)=>$user->id===$request->user()->id)) throw ValidationException::withMessages(['user_ids'=>'Akun Anda sendiri tidak boleh masuk aksi massal.']);
   foreach($users as $user){
    $invalid=match($data['action']){
     'archive'=>$user->isArchived(),
     'restore'=>!$user->isArchived(),
     'activate','deactivate'=>$user->isArchived()||str_starts_with($user->role,'legacy_'),
    };
    if($invalid) throw ValidationException::withMessages(['user_ids'=>'Akun '.$user->name.' tidak kompatibel dengan aksi '.str_replace('_',' ',$data['action']).'. Tidak ada akun yang diubah.']);
   }
   foreach($users as $user){
    $before=['is_active'=>$user->is_active,'archived_at'=>$user->archived_at?->toIso8601String()];
    match($data['action']){
     'activate'=>$user->update(['is_active'=>true]),
     'deactivate'=>$user->update(['is_active'=>false]),
     'archive'=>$user->update(['is_active'=>false,'archived_at'=>now(),'archived_by'=>$request->user()->id]),
     'restore'=>$user->update(['is_active'=>false,'archived_at'=>null,'archived_by'=>null]),
    };
    Audit::record('user.bulk_'.$data['action'],$user,['before'=>$before,'after'=>['is_active'=>$user->is_active,'archived_at'=>$user->archived_at?->toIso8601String()]]);
   }
   return $users->count();
  });
  $labels=['activate'=>'diaktifkan','deactivate'=>'dinonaktifkan','archive'=>'diarsipkan dan disembunyikan','restore'=>'dipulihkan dalam status nonaktif'];
  $target=$data['action']==='restore'?route('users.index'):($data['action']==='archive'?route('users.index'):url()->previous());
  return redirect($target)->with('success',$count.' akun berhasil '.$labels[$data['action']].'.');
 }
 private function ensureManageable(Request $request,User $user):void {
  abort_if($request->user()->isRole('koordinator') && $user->role!=='guru',403,'Koordinator hanya dapat mengelola akun guru pendamping.');
 }
}
