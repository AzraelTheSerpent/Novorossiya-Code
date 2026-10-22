<?php
namespace App\Services;

class Totp
{
    private const ALPHABET='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public static function secret(): string
    {
        $out='';
        for($i=0;$i<32;$i++) $out.=self::ALPHABET[random_int(0,31)];
        return $out;
    }
    public static function code(string $secret, int $counter): string
    {
        $bits='';
        foreach(str_split(strtoupper($secret)) as $char) {
            $value=strpos(self::ALPHABET,$char);
            if($value===false) throw new \InvalidArgumentException('Invalid TOTP secret');
            $bits.=str_pad(decbin($value),5,'0',STR_PAD_LEFT);
        }
        $key='';
        for($i=0;$i+8<=strlen($bits);$i+=8) $key.=chr(bindec(substr($bits,$i,8)));
        $hash=hash_hmac('sha1',pack('N2',0,$counter),$key,true);
        $offset=ord($hash[19])&15;
        $number=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;
        return str_pad((string)($number%1000000),6,'0',STR_PAD_LEFT);
    }
    public static function verify(string $secret,string $code,?int $last=null,?int $time=null): ?int
    {
        if(!preg_match('/^\d{6}$/',$code)) return null;
        $counter=intdiv($time ?? time(),30);
        foreach([$counter,$counter-1,$counter+1] as $step) {
            if(($last===null || $step>$last) && hash_equals(self::code($secret,$step),$code)) return $step;
        }
        return null;
    }
}
