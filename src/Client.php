<?php

namespace AtaneNL\PortaBase;

use AtaneNL\PortaBase\Exceptions\InvalidParameterException;
use AtaneNL\PortaBase\Exceptions\InvalidRequestException;
use AtaneNL\PortaBase\Exceptions\RemoteException;
use AtaneNL\PortaBase\Exceptions\UnauthorizedException;
use CURLFile;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

class Client
{
    const QUALIFICATION_FIRSTAID = 'ehbo';
    const QUALIFICATION_PASSPORT = 'psp';
    const QUALIFICATION_IDCARD = 'idk';
    const QUALIFICATION_DRIVERSLICENSE = 'rbw';
    const QUALIFICATION_VOG = 'vog';
    const QUALIFICATION_RIE = 'rie';

    private \GuzzleHttp\Client $client;
    private string $portabase_url;
    private string $portabase_key;

    /**
     * Initialize a PortaBase Client
     *
     * @param string $portabase_url PortaBase protocol and domain, e.g. https://awesome.portabase.nl
     * @param string $portabase_key PortaBase API key
     */
    public function __construct(string $portabase_url, string $portabase_key)
    {
        $this->portabase_url = $portabase_url;
        $this->portabase_key = $portabase_key;
        $this->client = new \GuzzleHttp\Client([
            'http_errors' => false,
            'headers' => [
                'accept' => 'application/json',
                'api-key' => $portabase_key,
            ]
        ]);
    }

    /**
     * Retrieve all host parents
     * @return array Array of hosts
     * @throws GuzzleException Thrown by the underlying guzzle client
     * @throws Exceptions\InvalidRequestException
     * @throws Exceptions\RemoteException
     * @throws Exceptions\UnauthorizedException
     */
    public function getHosts(): array
    {
        $response = $this->client->get("{$this->portabase_url}/api/1.0/gastouders");
        return $this->parseResponse($response);
    }

    /**
     * @param int $hostId
     * @return array Array with one host
     * @throws GuzzleException Thrown by the underlying guzzle client
     * @throws InvalidRequestException
     * @throws RemoteException
     * @throws UnauthorizedException
     */
    public function getHost(int $hostId): array
    {
        $response = $this->client->get("{$this->portabase_url}/api/1.0/gastouders/${hostId}");
        return $this->parseResponse($response);
    }

    /**
     * Retrieve all managers
     * @return array Array of managers
     * @throws GuzzleException
     * @throws InvalidRequestException
     * @throws RemoteException
     * @throws UnauthorizedException
     */
    public function getManagers(): array
    {
        $response = $this->client->get("{$this->portabase_url}/api/1.0/managers");
        return $this->parseResponse($response);
    }

    /**
     * @param int $hostId Id of host in Portabase
     * @param string $date Receive date of qualification formatted as YYYY-MM-DD
     * @param string $expireDate Expiry date of qualification formatted as YYYY-MM-DD
     * @param string $type One of the defined qualification types
     * @param CURLFile $attachment Attachment file
     * @param string|null $comments
     * @param string|null $lrkp
     * @param string|null $actionPlanApprovalDate Approval date formatted as YYYY-MM-DD
     * @param CURLFile|null $actionPlan Null or a file containing the action plan
     * @param bool $actionPlanExecuted
     * @throws GuzzleException
     * @throws InvalidParameterException
     * @throws InvalidRequestException
     * @throws RemoteException
     * @throws UnauthorizedException
     */
    public function postQualification(int $hostId, string $date, string $expireDate, string $type, CURLFile $attachment, string $comments = null, string $lrkp = null, string $actionPlanApprovalDate = null, CURLFile $actionPlan = null, bool $actionPlanExecuted = false): array
    {
        if (!in_array($type, [self::QUALIFICATION_FIRSTAID, self::QUALIFICATION_PASSPORT, self::QUALIFICATION_IDCARD, self::QUALIFICATION_DRIVERSLICENSE, self::QUALIFICATION_VOG, self::QUALIFICATION_RIE])) {
            throw new Exceptions\InvalidParameterException('Invalid type');
        }
        if ($type == self::QUALIFICATION_RIE && is_null($lrkp)) {
            throw new Exceptions\InvalidParameterException('Missing parameter lrkp');
        }

        $multipart = [
            ['name' => 'gastouderId', 'contents' => $hostId],
            ['name' => 'kwalificatieType', 'contents' => $type],
            ['name' => 'datumAfgifte', 'contents' => $date],
            ['name' => 'verloopDatum', 'contents' => $expireDate],
            [
                'name' => 'bijlage1',
                'contents' => Utils::tryFopen($attachment->getFilename(), 'r'),
                'filename' => $attachment->getPostFilename(),
                'headers' => ['content-type' => $attachment->getMimeType()],
            ],
        ];

        if ($type == self::QUALIFICATION_RIE) {
            $multipart[] = ['name' => 'lrkpNummer', 'contents' => $lrkp];
        }
        if (!is_null($actionPlanApprovalDate)) {
            $multipart[] = ['name' => 'datumAkkoordActieplan', 'contents' => $actionPlanApprovalDate];
        }
        if (!is_null($actionPlan)) {
            $multipart[] = ['name' => 'actieplan', 'contents' => 1];
            $multipart[] = [
                'name' => 'bijlage2',
                'contents' => Utils::tryFopen($actionPlan->getFilename(), 'r'),
                'filename' => $actionPlan->getPostFilename(),
                'headers' => ['content-type' => $actionPlan->getMimeType()],
            ];
            if ($actionPlanExecuted) {
                $multipart[] = ['name' => 'actieplanUitgevoerd', 'contents' => 1];
            }
        }
        if (!is_null($comments)) {
            $multipart[] = ['name' => 'opmerkingen', 'contents' => $comments];
        }

        $response = $this->client->post("{$this->portabase_url}/api/1.0/kwalificatie", [
            'multipart' => $multipart,
        ]);

        return $this->parseResponse($response);
    }

    /**
     * @throws InvalidRequestException Thrown if the request is deemed invalid by the server
     * @throws UnauthorizedException Thrown if we are not allowed to call an api endpoint
     * @throws RemoteException Thrown for other non-200 status codes
     */
    private function parseResponse(Response $response): array
    {
        $statusCode = $response->getStatusCode();
        $body = $response->getBody();
        if ($statusCode == 200 || $statusCode == 201) {
            return (array)json_decode($body);
        } elseif ($statusCode == 400) {
            throw new Exceptions\InvalidRequestException($body, $statusCode);
        } elseif ($statusCode == 401) {
            throw new Exceptions\UnauthorizedException($body, $statusCode);
        } else {
            throw new Exceptions\RemoteException($body, $statusCode);
        }
    }
}
