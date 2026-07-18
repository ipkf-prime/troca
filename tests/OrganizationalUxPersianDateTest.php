<?php
require_once __DIR__ . '/../public_html/app/Support/PersianDate.php';
use App\Support\PersianDate;
assert(PersianDate::toGregorianDate('۱۴۰۵/۰۴/۲۷') === '2026-07-18');
assert(PersianDate::fromGregorianDate('2026-07-18', false) === '1405/04/27');
echo "OK\n";
