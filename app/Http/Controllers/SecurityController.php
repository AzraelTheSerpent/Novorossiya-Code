<?php
namespace App\Http\Controllers;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class SecurityController extends Controller {
    public function show(Request $r) {
        $u=$r->user();
        $secret=null;
        if(!$u->two_factor_secret) {
            $secret=$r->session()->get('totp_pending') ?? Totp::secret();
            $r->session()->put('totp_pending',$secret);
        }
        return Inertia::render('Platform/Security',['secret'=>$secret,'enabled'=>(bool)$u->two_factor_secret,
            'verified'=>(bool)$r->session()->get('staff_2fa')]);
    }
    public function verify(Request $r) {
        $r->validate(['code'=>'required|digits:6']);
        $u=$r->user();
        $secret=$u->two_factor_secret ? Crypt::decryptString($u->two_factor_secret) : $r->session()->get('totp_pending');
        if(!$secret || ($step=Totp::verify($secret,$r->string('code')->toString(),$u->two_factor_counter))===null) {
            return back()->withErrors(['code'=>'Неверный или уже использованный код. Дождитесь следующего кода в приложении.']);
        }
        $updated=DB::table('users')->where('id',$u->id)->where(function($q)use($step){$q->whereNull('two_factor_counter')->orWhere('two_factor_counter','<',$step);})
            ->update(['two_factor_secret'=>Crypt::encryptString($secret),'two_factor_counter'=>$step]);
        if(!$updated) return back()->withErrors(['code'=>'Этот код уже использован.']);
        $r->session()->forget('totp_pending'); $r->session()->put('staff_2fa',true);
        return redirect('/platform/workspace');
    }
}
