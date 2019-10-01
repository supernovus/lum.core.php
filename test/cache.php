<?php

require_once 'vendor/autoload.php';

class CacheTest
{
  use \Lum\Meta\Cache;
}

$t = new \Lum\Test();
$cache = new CacheTest();

$sampledata =
[
  ['b', 123],
  ['t', 321],
  [['z','a'], ["hello"=>"world"]],
  [['z','b'], ["goodbye"=>"universe"]],
  [['f','a','r','t'], 'fart'],
];

$t->plan(count($sampledata)*2);

foreach ($sampledata as $i => $s)
{
  $t->is($cache->cache($s[0]), null, "sample $i empty at start");
  $cache->cache($s[0], $s[1]);
  $t->is($cache->cache($s[0]), $s[1], "sample $i has correct value");
}

echo $t->tap();
return $t;
