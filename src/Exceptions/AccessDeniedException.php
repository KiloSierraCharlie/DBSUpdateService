<?php

/*
 *
 * (c) Kieran Cross
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KiloSierraCharlie\DisclosureBarringService\Exceptions;

class AccessDeniedException extends \Exception
{
    public function __construct($message = '', $code = 0, ?\Exception $previous = null)
    {
        $this->message = 'Access to the API was denied by the server.'.$message.PHP_EOL;
        parent::__construct($this->message, $code, $previous);
    }

    public function __toString()
    {
        return __CLASS__.': ['.$this->code."] {$this->message}\n";
    }
}
