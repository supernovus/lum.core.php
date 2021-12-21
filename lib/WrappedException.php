<?php

namespace Lum;

use \Throwable;

/**
 * A base class for an Exception that wraps the passed message.
 * Used to make specialized 'template' Exceptions.
 */
abstract class WrappedException extends Exception
{
  /**
   * Generate the wrapped message.
   *
   * @param string $msg  The message from the constructor.
   *
   * @return string  The wrapped message for the Exception.
   */
  abstract protected function wrap($msg);

  public function __construct (
    string $message = "",
    int $code = 0,
    ?Throwable $previous = null)
  {
    $wrapped = $this->wrap($message);
    parent::__construct($wrapped, $code, $previous);
  }

}