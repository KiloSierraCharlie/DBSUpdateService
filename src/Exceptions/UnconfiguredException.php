<?php

/*
 *
 * (c) Kieran Cross
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KiloSierraCharlie\DisclosureBarringService\Exceptions;

class UnconfiguredException extends \Exception
{
    public function __construct($message = '', $code = 0, ?\Exception $previous = null)
    {
        $this->message = 'The API service has not been configured correctly.'.$message.PHP_EOL;
        parent::__construct($this->message, $code, $previous);
    }

    public function __toString()
    {
        return __CLASS__.': ['.$this->code."] {$this->message}\n";
    }
}
