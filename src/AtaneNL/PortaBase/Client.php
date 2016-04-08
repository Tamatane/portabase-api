<?php

namespace AtaneNL\PortaBase;

use anlutro\cURL\cURL;

class Client {
    private $curl;
    private $portabase_url;
    private $portabase_key;

    /**
     * Initialize a PortaBase Client
     *
     * @param string $portabase_url PortaBase protocol and domain, e.g. https://awesome.portabase.nl
     * @param string $portabase_key PortaBase API key
     */
    public function __construct($portabase_url, $portabase_key) {
        $this->portabase_url = $portabase_url;
        $this->portabase_key = $portabase_key;
        $this->curl = new cURL();
        $this->curl->setDefaultHeaders([
            "api-key" => $portabase_key
            ]);
    }

    /**
     * Retrieve all host parents
     *
     * @throws anlutro\cURL\cURLException Thrown by the underlying cURL library
     * @throws AtaneNL\PortaBase\Exceptions\RemoteException Thrown if the response was not a 200 OK
     *
     * @return Array of hosts
     */
    public function getHosts() {
        $response = $this->curl->jsonGet("{$this->portabase_url}/api/1.0/gastouders");
        return $this->parseResponse($response);
    }

    /**
     * Retrieve all managers
     *
     * @throws anlutro\cURL\cURLException Thrown by the underlying cURL library
     * @throws AtaneNL\PortaBase\Exceptions\RemoteException Thrown if the response was not a 200 OK
     */
    public function getManagers() {
        $response = $this->curl->jsonGet("{$this->portabase_url}/api/1.0/managers");
        return $this->parseResponse($response);
    }

    private function parseResponse($response) {
        if($response->statusCode == 200 || $response->statusCode == 201) {
            return json_decode($response->body);
        } else if($response->statusCode == 400) {
            throw new Exceptions\InvalidRequestException($reponse->body, $response->statusCode);
        } else if($response->statusCode == 403) {
            throw new Exceptions\UnauthorizedException($reponse->body, $response->statusCode);
        } else {
            throw new Exceptions\RemoteException($reponse->body, $response->statusCode);
        }
    }
}
