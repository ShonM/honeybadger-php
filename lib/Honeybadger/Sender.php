<?php

namespace Honeybadger;

use \Guzzle\Http\Client;

class Sender
{
    const NOTICES_URI = '/v1/notices/';

    protected $default_headers = array(
        'Accept'       => 'application/json',
        'Content-Type' => 'application/json; charset=utf-8',
    );

    public function send_to_honeybadger(Honeybadger $honeybadger, $notice)
    {
        if ($notice instanceof Notice) {
            $data = $notice->to_json();
        } else {
            $data = (string) $notice;
        }

        $headers = array();
        if ($api_key = $honeybadger->config->api_key) {
            $headers['X-API-Key'] = $api_key;
        }

        $response = $this->setup_http_client($honeybadger)
                         ->post(self::NOTICES_URI, $headers, $data)
                         ->send();

        $body = $response->json();

        return $body['id'];
    }

    private function setup_http_client(Honeybadger $honeybadger)
    {
        // Fetch a copy of the configuration.
        $config = $honeybadger->config;

        $options = array(
            'curl.options' => array(
                // Timeouts
                'CURLOPT_CONNECTTIMEOUT' => $config->http_open_timeout,
                'CURLOPT_TIMEOUT'        => $config->http_read_timeout,
                // Location redirects
                'CURLOPT_AUTOREFERER'    => TRUE,
                'CURLOPT_FOLLOWLOCATION' => TRUE,
                'CURLOPT_MAXREDIRS'      => 10,
            ),
        );

        if ($config->proxy_host) {
            $options['curl.options']['CURLOPT_HTTPPROXYTUNNEL'] = TRUE;
            $options['curl.options']['CURLOPT_PROXY']           = $config->proxy_host;
            $options['curl.options']['CURLOPT_PROXYPORT']       = $config->proxy_user.':'.$config->proxy_pass;
        }

        if ($config->is_secure()) {
            $options['ssl.certificate_authority'] = $config->certificate_authority;
        }

        try {
            $client = new Client($config->base_url(), $options);
            $client->setDefaultHeaders($this->default_headers);
            $client->setUserAgent($this->user_agent());

            return $client;
        } catch (Exception $e) {
            // Rethrow the exception
            throw $e;
        }
    }

    private function user_agent()
    {
        return sprintf('%s v%s (%s)', Honeybadger::NOTIFIER_NAME,
            Honeybadger::VERSION, Honeybadger::NOTIFIER_URL);
    }
}
