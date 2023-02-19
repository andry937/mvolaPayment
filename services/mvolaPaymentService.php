<?php

use GuzzleHttp\Client;

class MvolaPaymentService
{
    private $apiUrl;
    private $client;
    private $mvola_number;
    private $client_id;
    private $client_secret;

    public function configure()
    {
        $this->mvola_number = Configuration::get('MVOLA_CLIENT_NUMBER');
        $this->client_id = Configuration::get('MVOLA_PAYMENT_CLIENT_ID');
        $this->client_secret = Configuration::get('MVOLA_PAYMENT_CLIENT_SECRET');

        $sandbox =  Configuration::get('MVOLA_PAYMENT_DEBUG');
        if ($sandbox) {
            $this->apiUrl = 'https://devapi.mvola.mg';
        } else {
            $this->apiUrl = 'https://api.mvola.mg';
        }
    }

    public function __construct()
    {
        $this->client = new Client();
        $this->configure();
    }

    public function initiateTransaction($data)
    {
        $correlationId = $this->generateCorrelationId();
        $currentDate = date("Y-m-d\TH:i:s.000\Z");
        $callbackUrl = Context::getContext()->link->getModuleLink('mvolapayment', 'MvolaPaymentController', array(), true);

        $headers = [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Version' => '1.0',
            'X-CorrelationID' => $correlationId,
            'UserLanguage' => 'FR',
            'UserAccountIdentifier' => 'msisdn;' . $this->mvola_number,
            'partnerName' => Configuration::get('PS_SHOP_NAME'),
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'X-Callback-URL' => $callbackUrl
        ];

        $body = [
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'descriptionText' => $data['descriptionText'],
            'requestDate' => $currentDate,
            'debitParty' => [
                [
                    'key' => 'msisdn',
                    'value' =>  $data['client_reference']
                ]
            ],
            'creditParty' => [
                [
                    'key' => 'msisdn',
                    'value' => $this->mvola_number
                ]
            ],
            'metadata' => [
                [
                    'key' => 'partnerName',
                    'value' => Configuration::get('PS_SHOP_NAME')
                ]
            ],
            'requestingOrganisationTransactionReference' => $correlationId,
            'originalTransactionReference' => $correlationId
        ];

        if (isset($data['metadata']['fc'])) {
            array_push($body['metadata'], [
                'key' => 'fc',
                'value' => $data['metadata']['fc']
            ]);
        }

        if (isset($data['metadata']['amountFc'])) {
            array_push($body['metadata'], [
                'key' => 'amountFc',
                'value' => $data['metadata']['amountFc']
            ]);
        }
        try {

            $response = $this->client->post($this->apiUrl . '/mvola/mm/transactions/type/merchantpay/1.0.0', [
                'headers' => $headers,
                'json' => $body
            ]);

            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    private function generateCorrelationId()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }

    private function getAccessToken()
    {
        if (!isset($_SESSION['mvola_access_token']) || !isset($_SESSION['mvola_token_expiry']) || $_SESSION['mvola_token_expiry'] < time()) {
            if (isset($_SESSION['mvola_refresh_token'])) {
                $authorization = base64_encode($this->client_id . ':' . $this->client_secret);
                $options = [
                    'body' => [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $_SESSION['mvola_refresh_token'],
                        'scope' => 'EXT_INT_MVOLA_SCOPE'
                    ],
                    'verify' => false,
                    'headers' => [
                        'Authorization' => 'Basic ' . $authorization
                    ]
                ];
                $response = $this->client->post($this->apiUrl . '/token', $options);
                $json = $response->json();
                $_SESSION['mvola_access_token'] = $json['access_token'];
                $_SESSION['mvola_refresh_token'] = $json['refresh_token'];
                $_SESSION['mvola_token_expiry'] = time() + $json['expires_in'];
            } else {
                $authorization = base64_encode($this->client_id . ':' . $this->client_secret);
                $options = [
                    'body' => [
                        'grant_type' => 'client_credentials',
                        'scope' => 'EXT_INT_MVOLA_SCOPE'
                    ],
                    'verify' => false,
                    'headers' => [
                        'Authorization' => 'Basic ' . $authorization
                    ]
                ];
                $response = $this->client->post($this->apiUrl . '/token', $options);
                $json = $response->json();
                $_SESSION['mvola_access_token'] = $json['access_token'];
                $_SESSION['mvola_refresh_token'] = $json['refresh_token'];
                $_SESSION['mvola_token_expiry'] = time() + $json['expires_in'];
            }
        }
        return $_SESSION['mvola_access_token'];
    }


    public function getTransactionDetails($transactionId)
    {
        // Use Guzzle to send a GET request to the Transaction Details API endpoint
        // with the transaction ID as part of the URL
        // Return the response body as an array
    }

    public function getTransactionStatus($serverCorrelationId)
    {
        // Use Guzzle to send a GET request to the Transaction Status API endpoint
        // with the server correlation ID as part of the URL
        // Return the response body as an array
    }
}
