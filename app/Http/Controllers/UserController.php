<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class UserController {
 public function index(Request $r){
  $items=User::when($r->q,fn($q)=>$q->where(function($search)use($r){$search->where('name','like','%'.$r->q.'%')->orWhere('email','like','%'.$r->q.'%')->orWhere('nip','like','%'.$r->q.'%');}))
   ->orderBy('role')->paginate(20)->withQueryString();return view('users.index',compact('items'));
 }
 public function store(Request $r){
  $d=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|unique:users,email',
   'nip'=>'nullable|string|max:40|unique:users,nip','password'=>'required|string|min:10','role'=>['required',Rule::in(array_keys(User::staffRoleLabels()))]]);
  $u=User::create($d);Audit::record('user.created',$u,['role'=>$u->role]);
  return back()->with('success','Pengguna berhasil ditambahkan.');
 }
 public function toggle(Request $r,User $user){
  abort_if($user->id===$r->user()->id,422,'Tidak bisa menonaktifkan akun sendiri.');
  abort_if(str_starts_with($user->role,'legacy_'),422,'Akun dari role yang sudah dihentikan hanya disimpan sebagai arsip dan tidak dapat diaktifkan kembali.');
  $user->update(['is_active'=>!$user->is_active]);Audit::record('user.status_changed',$user,['is_active'=>$user->is_active]);
  return back()->with('success','Status pengguna berhasil diperbarui.');
 }
 public function identity(Request $r,User $user){
  abort_unless(in_array($user->role,['admin','koordinator','guru'],true),422,'NIP hanya digunakan untuk akun staf.');
  $d=$r->validate(['nip'=>['nullable','string','max:40',Rule::unique('users','nip')->ignore($user->id)]]);
  $old=$user->nip;$user->update(['nip'=>$d['nip']??null]);Audit::record('user.identity_updated',$user,['nip_before'=>$old,'nip_after'=>$user->nip]);
  return back()->with('success','NIP pengguna berhasil diperbarui.');
 }
}
