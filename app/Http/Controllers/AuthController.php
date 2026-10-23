<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
class AuthController {
 public function loginForm(){return view('auth.login');}
 public function login(Request $request){
  $request->merge(['login'=>$request->input('login',$request->input('email'))]);
  $data=$request->validate(['login'=>'required|string|max:255','password'=>'required|string']);
  $login=trim($data['login']);
  $key=strtolower($login).'|'.$request->ip();
  if(RateLimiter::tooManyAttempts($key,5)) throw ValidationException::withMessages(['login'=>'Terlalu banyak percobaan. Coba kembali dalam '.RateLimiter::availableIn($key).' detik.']);
  $account=str_contains($login,'@') ? User::where('email',$login)->first()
   : User::where('role','siswa')->whereHas('student',fn($q)=>$q->where('nis',$login))->first();
  if(!$account || !Auth::attempt(['id'=>$account->id,'password'=>$data['password'],'is_active'=>true])){
   RateLimiter::hit($key,60);throw ValidationException::withMessages(['login'=>'NIS/email, kata sandi, atau status akun tidak valid.']);
  }
  RateLimiter::clear($key);$request->session()->regenerate();
  return redirect()->intended(route('dashboard'));
 }
 public function logout(Request $request){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('login');}
 public function profile(){return view('profile.password');}
 public function updatePassword(Request $request){
  $data=$request->validate(['current_password'=>'required|current_password','password'=>'required|string|min:10|confirmed']);
  $request->user()->update(['password'=>$data['password']]);
  return back()->with('success','Kata sandi telah diperbarui.');
 }
}
