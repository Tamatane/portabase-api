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
        if($response->statusCode != 200) {
            throw new Exceptions\RemoteException($response->body, $response->statusCode);
        }
        return json_decode($response->body);
    }
}
