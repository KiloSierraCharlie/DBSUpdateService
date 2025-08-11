<?php

/*
 *
 * (c) Kieran Cross
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KiloSierraCharlie\DisclosureBarringService;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use KiloSierraCharlie\DisclosureBarringService\Exceptions\AccessDeniedException;
use KiloSierraCharlie\DisclosureBarringService\Exceptions\CertificateNotFoundException;
use KiloSierraCharlie\DisclosureBarringService\Exceptions\ConnectionFailureException;
use KiloSierraCharlie\DisclosureBarringService\Exceptions\MalformedDataException;
use KiloSierraCharlie\DisclosureBarringService\Exceptions\UnconfiguredException;
use KiloSierraCharlie\DisclosureBarringService\Models\StatusCheckResult;

final class UpdateServiceAPI
{
    private $baseURL = 'https://secure.crbonline.gov.uk';
    private $client;

    public function __construct(
        private string $organisationName,
        private string $requesterForename,
        private string $requesterSurname,
    ) {
        $this->client = new Client([
            'base_uri' => $this->baseURL, 'timeout' => 60,
        ]);
    }

    private function validateConfiguration()
    {
        return null !== $this->organisationName && null !== $this->requesterForename && null !== $this->requesterSurname;
    }

    private function validParsedDate(\DateTimeInterface|bool $dt): bool
    {
        if (!$dt) {
            return false;
        }
        $err = \DateTime::getLastErrors();

        return ($err['warning_count'] ?? 0) === 0 && ($err['error_count'] ?? 0) === 0;
    }

    private function normalizeDateOfBirth(mixed $input): \DateTime
    {
        switch (gettype($input)) {
            case 'DateTimeInterface':
            case 'DateTime':
                return $input;

            case 'integer':
            case 'double':
            case 'string':
                $s = trim((string) $input);

                if ('' !== $s && ctype_digit($s)) {
                    if (8 === strlen($s)) {
                        foreach (['Ymd', 'dmY'] as $fmt) {
                            $dt = \DateTime::createFromFormat($fmt, $s);
                            if ($this->validParsedDate($dt)) {
                                return $dt;
                            }
                        }
                        throw new MalformedDataException('Invalid 8-digit numeric DOB.');
                    }
                    throw new MalformedDataException('Unsupported numeric DOB length.');
                }

                $formats = [
                    'Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y',
                    \DateTime::RFC3339_EXTENDED, \DateTime::RFC3339, 'c',
                ];

                foreach ($formats as $fmt) {
                    $dt = \DateTime::createFromFormat($fmt, $s);
                    if ($this->validParsedDate($dt)) {
                        return $dt;
                    }
                }

                try {
                    $dt = new \DateTime($s);
                    if ($dt > new \DateTime('now')) {
                        throw new MalformedDataException('DOB cannot be in the future.');
                    }

                    return $dt;
                } catch (\Throwable $e) {
                    throw new MalformedDataException('Invalid DOB string.', 0, $e);
                }

            default:
                throw new MalformedDataException('Unsupported type for DOB.');
        }
    }

    public function getCertificateStatus(mixed $certificateId, string $surname, mixed $dateOfBirth): StatusCheckResult
    {
        if (!$this->validateConfiguration()) {
            throw new UnconfiguredException();
        }

        try {
            $response = $this->client->request(
                'GET',
                '/crsc/api/status/'.$certificateId,
                [
                    'query' => [
                        'hasAgreedTermsAndConditions' => 'true',
                        'organisationName' => $this->organisationName,
                        'employeeForename' => $this->requesterForename,
                        'employeeSurname' => $this->requesterSurname,
                        'surname' => $surname,
                        'dateOfBirth' => $this->normalizeDateOfBirth($dateOfBirth)->format('d/m/Y'),
                    ],
                ]
            );

            return StatusCheckResult::fromXml($response->getBody());
        } catch (ClientException $e) {
            if (401 === $e->getCode() || 403 === $e->getCode()) {
                throw new AccessDeniedException(null, $e->getCode());
            }

            if (404 === $e->getCode()) {
                throw new CertificateNotFoundException(null, $e->getCode());
            }

            throw new ConnectionFailureException($e->getMessage());
        } catch (TransferException $e) {
            throw new ConnectionFailureException($e->getMessage());
        }
    }
}
