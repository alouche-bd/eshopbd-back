<?php

namespace App\Traits;

use GuzzleHttp\Client;

trait ConsumeExternalService
{
    /**
     * Send request to any service
     * @param $method
     * @param $requestUrl
     * @param array $formParams
     * @param array $headers
     * @param string $body
     * @return string
     */
    public function performRequest($method, $requestUrl, $body = '', $headers = [])
    {
        $client = new Client([
            'base_uri'  =>  $this->baseUri,
            'verify' => false
        ]);

        if (isset($this->secret)) {
            $headers['Authorization'] = 'Bearer ' . $this->secret;
        }

        $promise = $client->requestAsync($method, $requestUrl, [
            'body' => $body,
            'headers'     => $headers,
        ]);

        $response = $promise->wait();

        return $response->getBody()->getContents();
    }
}
