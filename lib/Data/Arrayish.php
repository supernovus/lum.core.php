<?php

namespace Lum\Data;

/**
 * Arrayish Data Structure.
 *
 * Supports iteration, countability and array-like access.
 */
abstract class Arrayish extends Obj
                        implements \Iterator, \ArrayAccess, \Countable
{
  use Arraylike;
}

