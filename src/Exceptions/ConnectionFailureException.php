<?php

/*
 *
 * (c) Kieran Cross
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KiloSierraCharlie\DBSUpdateService\Exceptions;

class ConnectionFailureException extends \Exception
{
    public function __construct($message = '', $code = 0, ?\Exception $previous = null)
    {
        $this->message = 'An error occurred whilst connecting to the API. '.$message.PHP_EOL;
        parent::__construct($this->message, $code, $previous);
    }

    public function __toString()
    {
        return __CLASS__.': ['.$this->code."] {$this->message}\n";
    }
}
