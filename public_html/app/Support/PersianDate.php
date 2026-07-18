<?php

namespace App\Support;

use DateTimeImmutable;
use RuntimeException;

final class PersianDate
{
    public static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }

    public static function toGregorianDate(?string $value): ?string
    {
        $value = trim(self::normalizeDigits((string) $value));
        if ($value === '') return null;
        $value = str_replace(['-', '.'], '/', $value);
        if (!preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $value, $m)) {
            throw new RuntimeException('تاریخ را به صورت ۱۴۰۵/۰۴/۲۷ وارد کنید.');
        }
        $jy=(int)$m[1];$jm=(int)$m[2];$jd=(int)$m[3];
        if ($jy < 1200 || $jy > 1700 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31 || ($jm > 6 && $jd > 30)) {
            throw new RuntimeException('تاریخ شمسی معتبر نیست.');
        }
        [$gy,$gm,$gd] = self::d2g(self::j2d($jy,$jm,$jd));
        $date = sprintf('%04d-%02d-%02d',$gy,$gm,$gd);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new RuntimeException('تاریخ شمسی معتبر نیست.');
        return $date;
    }

    public static function fromGregorianDate(?string $value, bool $persianDigits = true): string
    {
        $value = trim((string)$value);
        if ($value === '') return '';
        $date = substr($value,0,10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$m)) return $value;
        [$jy,$jm,$jd] = self::d2j(self::g2d((int)$m[1],(int)$m[2],(int)$m[3]));
        $out=sprintf('%04d/%02d/%02d',$jy,$jm,$jd);
        return $persianDigits ? strtr($out,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']) : $out;
    }

    private static function div(int $a,int $b): int { return intdiv($a,$b); }
    private static function mod(int $a,int $b): int { return $a - self::div($a,$b)*$b; }

    private static function jalCal(int $jy): array
    {
        $breaks=[-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178];
        $bl=count($breaks);$gy=$jy+621;$leapJ=-14;$jp=$breaks[0];$jump=0;
        if($jy<$jp||$jy>=$breaks[$bl-1]) throw new RuntimeException('سال شمسی خارج از محدوده پشتیبانی است.');
        for($i=1;$i<$bl;$i++){ $jm=$breaks[$i];$jump=$jm-$jp;if($jy<$jm)break;$leapJ+=self::div($jump,33)*8+self::div(self::mod($jump,33),4);$jp=$jm; }
        $n=$jy-$jp;$leapJ+=self::div($n,33)*8+self::div(self::mod($n,33)+3,4);
        if(self::mod($jump,33)===4 && $jump-$n===4)$leapJ++;
        $leapG=self::div($gy,4)-self::div((self::div($gy,100)+1)*3,4)-150;
        $march=20+$leapJ-$leapG;
        if($jump-$n<6)$n=$n-$jump+self::div($jump+4,33)*33;
        $leap=self::mod(self::mod($n+1,33)-1,4);if($leap===-1)$leap=4;
        return ['leap'=>$leap,'gy'=>$gy,'march'=>$march];
    }

    private static function g2d(int $gy,int $gm,int $gd): int
    {
        $d=self::div(($gy+self::div($gm-8,6)+100100)*1461,4)+self::div(153*self::mod($gm+9,12)+2,5)+$gd-34840408;
        $d=$d-self::div(self::div($gy+100100+self::div($gm-8,6),100)*3,4)+752;
        return $d;
    }
    private static function d2g(int $jdn): array
    {
        $j=4*$jdn+139361631;$j=$j+ self::div(self::div(4*$jdn+183187720,146097)*3,4)*4-3908;
        $i=self::div(self::mod($j,1461),4)*5+308;$gd=self::div(self::mod($i,153),5)+1;$gm=self::mod(self::div($i,153),12)+1;$gy=self::div($j,1461)-100100+self::div(8-$gm,6);
        return [$gy,$gm,$gd];
    }
    private static function j2d(int $jy,int $jm,int $jd): int
    {
        $r=self::jalCal($jy);return self::g2d($r['gy'],3,$r['march'])+($jm-1)*31-self::div($jm,7)*($jm-7)+$jd-1;
    }
    private static function d2j(int $jdn): array
    {
        [$gy,$gm,$gd]=self::d2g($jdn);$jy=$gy-621;$r=self::jalCal($jy);$jdn1f=self::g2d($gy,3,$r['march']);$k=$jdn-$jdn1f;
        if($k>=0){if($k<=185){$jm=1+self::div($k,31);$jd=self::mod($k,31)+1;return[$jy,$jm,$jd];}$k-=186;}
        else{$jy--; $k+=179;if($r['leap']===1)$k++;}
        $jm=7+self::div($k,30);$jd=self::mod($k,30)+1;return[$jy,$jm,$jd];
    }
}
