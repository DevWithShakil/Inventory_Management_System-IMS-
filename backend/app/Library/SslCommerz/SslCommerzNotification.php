<?php

namespace App\Library\SslCommerz;
use App\Library\SslCommerz\SslCommerzNotification;

class SslCommerzNotification
{
    protected $data;
    protected $config;

    public function __construct()
    {
        $this->config = config('sslcommerz');
    }

    public function makePayment($requestData, $type = 'checkout', $pattern = 'json')
    {
        if (empty($requestData)) {
            return "Please provide a valid information list about transaction with transaction id, amount, success url, fail url, cancel url, store id and pass at least";
        }

        $header = [];

        $this->setApiUrl($this->config['apiDomain'] . $this->config['apiUrl']['make_payment']);

        // Set Store Credentials
        $requestData['store_id'] = $this->config['apiCredentials']['store_id'];
        $requestData['store_passwd'] = $this->config['apiCredentials']['store_password'];

        $response = $this->callToApi($requestData, $header, false);

        $formattedResponse = json_decode($response, true);

        if ($pattern == 'json') {
            return $formattedResponse;
        } else {
            if (isset($formattedResponse['GatewayPageURL']) && $formattedResponse['GatewayPageURL'] != "") {
                echo "<meta http-equiv='refresh' content='0;url=" . $formattedResponse['GatewayPageURL'] . "'>";
                exit;
            } else {
                return "No redirect URL found!";
            }
        }
    }

    public function orderValidate($post_data, $trx_id = '', $amount = 0, $currency = "BDT")
    {
        if ($post_data == '' && $trx_id == '' && $amount == 0) {
            return false;
        }

        $this->setApiUrl($this->config['apiDomain'] . $this->config['apiUrl']['order_validate']);

        $handle = curl_init();
        $validationData = [
            'val_id' => $post_data['val_id'],
            'store_id' => $this->config['apiCredentials']['store_id'],
            'store_passwd' => $this->config['apiCredentials']['store_password'],
            'v' => 1,
            'format' => 'json'
        ];

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $this->getApiUrl() . "?" . http_build_query($validationData));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        $content = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if ($code == 200 && !(curl_errno($handle))) {
            $result = json_decode($content, true);

            // Validate Logic
            if (
                isset($result['status']) && $result['status'] == 'VALID' || $result['status'] == 'VALIDATED'
            ) {
                if($amount > 0 && $result['amount'] != $amount) {
                    return false; // Amount mismatch
                }
                return true;
            }
        }

        return false;
    }

    protected $apiUrl;

    protected function setApiUrl($url)
    {
        $this->apiUrl = $url;
    }

    protected function getApiUrl()
    {
        return $this->apiUrl;
    }

    public function callToApi($data, $header = [], $setIp = true)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->getApiUrl());
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErrorNo = curl_errno($curl);
        curl_close($curl);

        if ($code == 200 & !($curlErrorNo)) {
            return $response;
        } else {
            return "FAILED TO CONNECT WITH SSLCOMMERZ API";
        }
    }
}
