<?php

namespace KiloSierraCharlie\DisclosureBarringService\Models;

use KiloSierraCharlie\DisclosureBarringService\Exceptions\InvalidResponseException;

enum StatusCheckResultType: string
{
    case SUCCESS = 'SUCCESS';
    case FAILURE = 'FAILURE';
}

enum StatusCode: string
{
    case BLANK_NO_NEW_INFO = 'BLANK_NO_NEW_INFO';
    case NON_BLANK_NO_NEW_INFO = 'NON_BLANK_NO_NEW_INFO';
    case NEW_INFO = 'NEW_INFO';
}

class StatusCheckResult
{
    public function __construct(
        public StatusCheckResultType $resultType,
        public StatusCode $status,
        public string $forename,
        public string $surname,
        public \DateTimeImmutable $printDate,
    ) {
    }

    public static function fromXml(string $xml): self
    {
        $prev = libxml_use_internal_errors(true);
        try {
            $sx = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA);
            if (false === $sx) {
                $msg = self::libxmlErrorsToMessage();
                throw new InvalidResponseException('Invalid XML: '.$msg);
            }

            if ('statusCheckResult' !== $sx->getName()) {
                throw new InvalidResponseException('Unexpected root element: '.$sx->getName());
            }

            $type = isset($sx->statusCheckResultType) ? (string) $sx->statusCheckResultType : null;
            $status = isset($sx->status) ? (string) $sx->status : null;
            $fname = isset($sx->forename) ? trim((string) $sx->forename) : null;
            $sname = isset($sx->surname) ? trim((string) $sx->surname) : null;
            $pdate = isset($sx->printDate) ? (string) $sx->printDate : null;

            foreach (['statusCheckResultType' => $type, 'status' => $status, 'forename' => $fname, 'surname' => $sname, 'printDate' => $pdate] as $field => $val) {
                if (null === $val || '' === $val) {
                    throw new InvalidResponseException("Missing or empty <$field>.");
                }
            }

            $resultType = StatusCheckResultType::tryFrom($type);
            if (!$resultType) {
                throw new InvalidResponseException("Unknown statusCheckResultType: '$type'.");
            }

            $statusCode = StatusCode::tryFrom($status);
            if (!$statusCode) {
                throw new InvalidResponseException("Unknown status: '$status'.");
            }

            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $pdate);
            $errors = \DateTime::getLastErrors();
            if (!$dt || ($errors['warning_count'] ?? 0) || ($errors['error_count'] ?? 0)) {
                throw new InvalidResponseException("Invalid printDate (expected Y-m-d): '$pdate'.");
            }

            return new self(
                resultType: $resultType,
                status: $statusCode,
                forename: $fname,
                surname: $sname,
                printDate: $dt,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    private static function libxmlErrorsToMessage(): string
    {
        $errs = array_map(
            fn (\LibXMLError $e) => trim($e->message)." at line {$e->line}, col {$e->column}",
            libxml_get_errors() ?: []
        );

        return $errs ? implode('; ', $errs) : 'Unknown parse error';
    }

    public function isCurrent(): bool
    {
        return StatusCheckResultType::SUCCESS == $this->resultType && StatusCode::NEW_INFO !== $this->status;
    }

    public function isClear(): bool
    {
        return StatusCheckResultType::SUCCESS == $this->resultType && StatusCode::BLANK_NO_NEW_INFO === $this->status;
    }
}
