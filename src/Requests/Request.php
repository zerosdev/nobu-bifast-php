<?php

namespace Esyede\NobuBifast\Requests;

class Request
{
    private $config;
    private $accessToken;
    private $uniqueRefDaily;
    private $serverIpv4;
    private $dateAtom;

    public function __construct(Config $config, $accessToken, $uniqueRefDaily)
    {
        $this->config = $config;
        $this->accessToken = $accessToken;
        $this->uniqueRefDaily = $uniqueRefDaily;
        $this->serverIpv4 = gethostbyname(gethostname());
        $this->dateAtom = Helper::getDateAtom();
    }

    private function send($method, $endpoint, array $payloads = [])
    {
        if (count($payloads) > 0) {
            ksort($payloads);
        }

        $method = strtoupper($method);
        $signature = Helper::makeSignature(
            $this->config->getClientSecret(),
            $method,
            $endpoint,
            $this->accessToken,
            $this->dateAtom,
            $payloads
        );

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'X-TIMESTAMP: ' . $this->dateAtom,
            'X-EXTERNAL-ID: ' . $this->uniqueRefDaily,
            'X-PARTNER-ID: ' . $this->config->getPartnerId(),
            'X-IP-ADDRESS: ' . $this->serverIpv4,
        ];

        $endpoint = $this->config->getBaseUrl() . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FAILONERROR => false,
        ]);

        if ('POST' === $method) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloads));
        }

        $raw = curl_exec($ch);
        $errors = curl_error($ch);

        curl_close($ch);

        $decoded = json_decode($raw);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = false;
            $errors = 'Unable to decode json response.';
        }

        $results = [
            'endpoint' => $endpoint,
            'method' => $method,
            'headers' => $headers,
            'body' => $payloads,
            'response' => [
                'decoded' => $decoded,
                'raw' => $raw,
                'errors' => $errors,
            ],
        ];

        return json_encode($results);
    }

    public function get($endpoint, array $payloads = [])
    {
        return $this->send('GET', $endpoint, $payloads);
    }

    public function post($endpoint, array $payloads = [])
    {
        return $this->send('POST', $endpoint, $payloads);
    }
}
