<?php

namespace DepsTest;

use \Lum\Meta\HasDeps;

require_once 'vendor/autoload.php';

const C = 'construct';

const T = 'deps_';

const C_PRE = '__construct_';

const ANON = 'anonymous';
const NONA = 'suomynona';
const HI = ' says hi to ';

const NO = 0; // Don't care about the output of the method.
const OK = 1; // Expect the value to be something.
const EX = 2; // Expect an exception to be thrown.

function dbg($key, $is=null)
{
  return \Lum\Plugins\Debug::is($key, $is);
}

abstract class Base
{
  public bool $debug = false;
  public function __construct ()
  {
    $this->debug = dbg(T.'groups');
  }
}

abstract class Base1 extends Base
{
  use HasDeps;

  public function __construct($opts=[])
  {
    parent::__construct();
    $dep_opts = ['prefix' => C_PRE];
    $this->_dep_group(C, $dep_opts, [$opts]);
  }
}

abstract class Base2 extends Base
{
  use HasDeps;

  public function __construct($opts=[])
  {
    parent::__construct();
    $dep_opts = 
    [
      'prefix' => C_PRE,
      'deps_prop' => 'constructors', 
      'args' => [$opts],
    ];
    $this->_dep_group(C, $dep_opts);
  }
}

interface Person {}

trait Foo
{
  public string $name = ANON;

  protected function __construct_foo($opts=[])
  {
    if (isset($opts['name']))
    {
      $this->name = $opts['name'];
    }
  }

  public function greet(Person $person)
  {
    return $this->name . HI . $person->name;
  }

}

trait Bar
{
  public string $bar_id;

  protected function __construct_bar($opts=[])
  {
    $this->needs('foo'); // Make sure 'foo' is loaded first.
    $this->bar_id = strrev($this->name);
  }
}

trait Zap
{
  protected array $zapped = [];

  protected function zap_your_mom(Person $person)
  {
    $dbg = dbg(T.'zap_your_mom');
    $name = $person->name;
    if (dbg(T.'zap_your_mom'))
    {
      error_log("zap_your_mom($name)");
    }
    if (isset($this->zapped[$name]))
    {
      $this->zapped[$name]++;
    }
    else
      $this->zapped[$name] = 1;
  }

  protected function zap_yourself(string $name, int $times)
  {
    if (isset($this->zapped[$name]))
      $this->zapped[$name] += $times;
    else
      $this->zapped[$name] = $times;
  }

  public function getZapped(): array
  {
    return $this->zapped;
  }
}

trait Shit
{
  protected array $shit = [];

  public function shit_yourself(string $name, int $times)
  {
    if (isset($this->shit[$name]))
      $this->shit[$name] += $times;
    else
      $this->shit[$name] = $times;
  }

  public function shat(): array
  {
    return $this->shit;
  }
}

class FooBar1 extends Base1 implements Person { use Foo, Bar; }

class FooBarZap1 extends FooBar1
{
  use Zap;

  public function your_mom(Person $person, ?array $deps=null)
  {
    $dep_opts = ['postfix'=>'_your_mom'];
    if (isset($deps))
      $dep_opts['deps'] = $deps;
    $this->_dep_group('your_mom', $dep_opts, [$person]);
  }
}

class FooBar2 extends Base2 implements Person
{ 
  use Foo, Bar; 
  protected array $constructors = ['foo'];
  public function __construct($opts=[])
  {
    if (isset($opts['more_deps']))
    {
      $this->constructors = array_merge($this->constructors, $opts['more_deps']);
    }
    parent::__construct($opts);
  }
}

class FooZapShit2 extends Base2 implements Person
{
  use Foo, Zap, Shit;

  public function yourself(int $times)
  {
    $dep_opts = ['postfix'=>'_yourself'];
    $this->_dep_group('yourself', $dep_opts, [$this->name, $times]);
  }
}

// This class will explode on attempt to create an instance.
class ZapBar1 extends Base1
{
  use Bar, Zap;
}

// This class won't explode on creation, but it will on yourself().
class Shit2 extends Base2
{
  use Shit;
}

$t = new \Lum\Test();

$o = 
[
  new FooBar1(),
  new FooBar1(["name"=>"Bob"]),
  new FooBarZap1(),
  new FooBarZap1(["name"=>"Lisa"]),
  new FooBar2(),
  new FooBar2(['more_deps'=>['bar'], 'name'=>'Sarah']),
  new FooZapShit2(),
  new FooZapShit2(['name'=>'Mike']),
  fn() => new ZapBar1(),
  new Shit2(['name'=>'Will']),
];

$p =
[
  ['name' => ANON,     'bar_id' => NONA],      // FooBar1()
  ['name' => 'Bob',    'bar_id' => 'boB'],     // FooBar1(Bob)
  ['name' => ANON,     'bar_id' => NONA],      // FooBarZap1()
  ['name' => 'Lisa',   'bar_id' => 'asiL'],    // FooBarZap1(Lisa)
  ['name' => ANON,     'bar_id' => null],      // FooBar2()
  ['name' => 'Sarah',  'bar_id' => 'haraS'],   // FooBar2(Sarah, ['bar'])
  ['name' => ANON,     'bar_id' => null],      // FooZapShit2()
  ['name' => 'Mike',   'bar_id' => null],      // FooZapShit2(Mike)
  null,                                        // ZapBar2()
  ['name' => null,     'bar_id' => null],      // Shit2(Will)
];

$m =
[
  [ // FooBar1()
    [OK, 'greet',     [$o[1]],      ANON . HI . 'Bob'],
    [EX, 'getZapped', [],           null],
    [EX, 'your_mom',  [$o[2], 1],   null],
    [EX, 'yourself',  [10],         null],
  ],
  [ // FooBar1(Bob)
    [OK, 'greet',     [$o[3]],      'Bob' . HI . 'Lisa'],
  ],
  [ // FooBarZap1()
    [OK, 'greet',     [$o[3]],      ANON . HI . 'Lisa'],
    [OK, 'getZapped', [],           []],
    [NO, 'your_mom',  [$o[7]],      null],
    [OK, 'getZapped', [],           ['Mike'=>1]],
  ],
  [ // FooBarZap1(Lisa)
    [OK, 'greet',     [$o[5]],      'Lisa' . HI . 'Sarah'],
    [NO, 'your_mom',  [$o[5]],      null],
    [NO, 'your_mom',  [$o[5]],      null],
    [OK, 'getZapped', [],           ['Sarah'=>2]],
  ],
  [ // FooBar2()
    [OK, 'greet',     [$o[1]],      ANON . HI . 'Bob'],
  ],
  [ // FooBar2(Sarah, ['bar'])
    [OK, 'greet',     [$o[1]],      'Sarah' . HI . 'Bob'],
  ],
  [ // FooZapShit2()
    [OK, 'greet',     [$o[5]],      ANON . HI . 'Sarah'],
    [NO, 'yourself',  [5],          null],
    [OK, 'getZapped', [],           [ANON=>5]],
    [OK, 'shat',      [],           [ANON=>5]],
  ],
  [ // FooZapShit2(Mike)
    [OK, 'greet',     [$o[5]],     'Mike' . HI . 'Sarah'],
    [NO, 'yourself',  [9],         null],
    [OK, 'getZapped', [],          ['Mike'=>9]],
    [OK, 'shat',      [],          ['Mike'=>9]],
  ],
  null, // ZapBar2()
  [ // Shit2(Will)
    [EX, 'greet',     [$o[1]],     null],
    [EX, 'yourself',  [99],        null],
  ],
];

foreach ($o as $i => $O)
{
  if (is_object($O))
  { // Now let's run a shit-tonne of tests.
    $oname = get_class($O);
    foreach ($p[$i] as $prop => $expected)
    {
      if (is_null($expected))
      { // We expect the property will not exist or be null.
        $t->ok(!isset($O->$prop), "$oname->$prop does not exist or is null");
      }
      else
      { // We exect the property will exist and be a valid value.
        $t->is($O->$prop, $expected, "$oname->$prop is correct");
      }
    }
    foreach ($m[$i] as [$use, $meth, $args, $expected])
    {
      $callable = [$O, $meth];
      if ($use === EX)
      { // The callable shouldn't exist, or should throw an exception.
        $t->dies(fn() => call_user_func_array($callable, $args), 
          "$oname->$meth() dies properly");
      }
      else
      {
        try
        {
          $res = call_user_func_array($callable, $args);
          if ($use === OK)
          {
            $t->is($res, $expected, "$oname->$meth() returns correct value");
          }
        }
        catch (\Exception $e)
        {
          if ($use === OK)
          {
            $t->fail("$oname->$meth() dies unexpectedly");
          }
        }
      }
    }
  }
  elseif (is_callable($O))
  { // Only one test to run...
    $t->dies($O, "Invalid class throws exception");
  }
}

echo $t->tap();
return $t;

