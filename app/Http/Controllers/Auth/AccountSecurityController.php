<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Verified;
use Illuminate\Validation\ValidationException;

class AccountSecurityController extends Controller {
    public function confirmForm() {return view('auth.confirm-password');}
    public function confirm(Request $r) {
        if(!Hash::check($r->string('password'),$r->user()->password)) throw ValidationException::withMessages(['password'=>'Неверный пароль.']);
        $r->session()->passwordConfirmed();return redirect('/platform/workspace');
    }
    public function password(Request $r) {
        $v=$r->validate(['current_password'=>'required|current_password','password'=>'required|string|min:8|confirmed']);
        $r->user()->update(['password'=>$v['password']]);return back();
    }
    public function notice() {return view('auth.verify-email');}
    public function verify(Request $r,string $id,string $hash) {
        abort_unless((string)$r->user()->id===$id && hash_equals(sha1($r->user()->getEmailForVerification()),$hash),403);
        if(!$r->user()->hasVerifiedEmail()) {$r->user()->markEmailAsVerified();event(new Verified($r->user()));}
        return redirect('/platform/workspace?verified=1');
    }
    public function send(Request $r) {$r->user()->sendEmailVerificationNotification();return back()->with('success','Ссылка отправлена.');}
}
