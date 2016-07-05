<?php

namespace AtaneNL\PortaBase;

use anlutro\cURL\cURL;
use anlutro\cURL\Request;
use CURLFile;

class Client
{
    const QUALIFICATION_FIRSTAID = 'ehbo';
    const QUALIFICATION_PASSPORT = 'psp';
    const QUALIFICATION_IDCARD = 'idk';
    const QUALIFICATION_DRIVERSLICENSE = 'rbw';
    const QUALIFICATION_VOG = 'vog';
    const QUALIFICATION_RIE = 'rie';

    private $curl;
    private $portabase_url;
    private $portabase_key;

    /**
     * Initialize a PortaBase Client
     *
     * @param string $portabase_url PortaBase protocol and domain, e.g. https://awesome.portabase.nl
     * @param string $portabase_key PortaBase API key
     */
    public function __construct($portabase_url, $portabase_key)
    {
        $this->portabase_url = $portabase_url;
        $this->portabase_key = $portabase_key;
        $this->curl = new cURL();
        $this->curl->setDefaultHeaders([
            'api-key' => $portabase_key
            ]);
    }

    /**
     * Retrieve all host parents
     *
     * @throws \anlutro\cURL\cURLException Thrown by the underlying cURL library
     * @throws Exceptions\RemoteException Thrown if the response was not a 200 OK
     *
     * @return array Array of hosts
     */
    public function getHosts()
    {
        $response = $this->curl->jsonGet("{$this->portabase_url}/api/1.0/gastouders");
        return $this->parseResponse($response);
    }

    /**
     * @param $hostId
     * @return array Array with one host
     * @throws Exceptions\InvalidParameterException
     * @throws Exceptions\InvalidRequestException
     * @throws Exceptions\RemoteException
     * @throws Exceptions\UnauthorizedException
     */
    public function getHost($hostId)
    {
        if (!is_numeric($hostId)) {
            throw new Exceptions\InvalidParameterException('Invalid host ID');
        }
        $response = $this->curl->jsonGet("{$this->portabase_url}/api/1.0/gastouders/${hostId}");
        return $this->parseResponse($response);
    }

    /**
     * Retrieve all managers
     *
     * @throws \anlutro\cURL\cURLException Thrown by the underlying cURL library
     * @throws Exceptions\RemoteException Thrown if the response was not a 200 OK
     */
    public function getManagers()
    {
        $response = $this->curl->jsonGet("{$this->portabase_url}/api/1.0/managers");
        return $this->parseResponse($response);
    }

    /**
     * @param $hostId
     * @param $date
     * @param $expireDate
     * @param $type
     * @param CURLFile $attachment
     * @param null $comments
     * @param null $lrkp
     * @param null $actionPlanApprovalDate
     * @param CURLFile|null $actionPlan
     * @param bool $actionPlanExecuted
     * @return mixed
     * @throws Exceptions\InvalidParameterException
     * @throws Exceptions\InvalidRequestException
     * @throws Exceptions\RemoteException
     * @throws Exceptions\UnauthorizedException
     */
    public function postQualification($hostId, $date, $expireDate, $type, CURLFile $attachment, $comments = null, $lrkp = null, $actionPlanApprovalDate = null, CURLFile $actionPlan = null, $actionPlanExecuted = false)
    {
        if (!in_array($type, [self::QUALIFICATION_FIRSTAID, self::QUALIFICATION_PASSPORT, self::QUALIFICATION_IDCARD, self::QUALIFICATION_DRIVERSLICENSE, self::QUALIFICATION_VOG, self::QUALIFICATION_RIE])) {
            throw new Exceptions\InvalidParameterException('Invalid type');
        }
        if ($type == self::QUALIFICATION_RIE && is_null($lrkp)) {
            throw new Exceptions\InvalidParameterException('Missing parameter lrkp');
        }

        $params = [
            'gastouderId' => $hostId,
            'kwalificatieType' => $type,
            'datumAfgifte' => $date,
            'verloopDatum' => $expireDate,
            'bijlage1' => $attachment
        ];
        if ($type == self::QUALIFICATION_RIE) {
            $params['lrkpNummer'] = $lrkp;
        }
        if (!is_null($actionPlanApprovalDate)) {
            $params['datumAkkoordActieplan'] = $actionPlanApprovalDate;
        }
        if (!is_null($actionPlan)) {
            $params['actieplan'] = 1;
            $params['bijlage2'] = $actionPlan;
            if ($actionPlanExecuted) {
                $params['actieplanUitgevoerd'] = 1;
            }
        }
        if (!is_null($comments)) {
            $params['opmerkingen'] = $comments;
        }

        $request = $this->curl->newRequest('POST', "{$this->portabase_url}/api/1.0/kwalificatie", $params, Request::ENCODING_RAW);
        $response = $request->send();
        return $this->parseResponse($response);
    }

    private function parseResponse($response)
    {
        if ($response->statusCode == 200 || $response->statusCode == 201) {
            return json_decode($response->body);
        } elseif ($response->statusCode == 400) {
            throw new Exceptions\InvalidRequestException($response->body, $response->statusCode);
        } elseif ($response->statusCode == 403) {
            throw new Exceptions\UnauthorizedException($response->body, $response->statusCode);
        } else {
            throw new Exceptions\RemoteException($response->body, $response->statusCode);
        }
    }
}
