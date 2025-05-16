<?php

namespace src\Aiqfome\Http;

use src\Aiqfome\Entities\AuthenticateStoreCache;
use src\Aiqfome\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;

abstract class BaseAuthenticate
{
    protected Request $request;
    protected Custom $custom;
    protected RedisService $redisService;
    protected object $response;

    public function __construct(Request $request, Custom $custom, RedisService $redisService)
    {
        $this->request = $request;
        $this->custom = $custom;
        $this->redisService = $redisService;
    }

    public function handle(string $username, string $password, string $empresaId): void
    {
        $customAiQFome = $this->custom->getParams('aiqfome');

        $this->callApiAiqFome($customAiQFome['credentials'], $username, $password);
        $this->checkResponse();

        $response = json_decode($this->response->content, true);

        $authenticate = new AuthenticateStoreCache($this->redisService, $response['data']);
        $key = str_replace('{enterprise_id}', $empresaId, RedisSchema::KEY_AUTHENTICATE_AIQFOME);
        $authenticate->saveToken($key);
    }

    protected function callApiAiqFome(array $credentials, string $username, string $password): void
    {
        $headers = [
            'Content-Type: application/json',
            'aiq-client-authorization: ' . $credentials['aiq-client-authorization'],
            'aiq-user-agent: ' . $credentials['aiq-user-agent'],
            'User-Agent: curl/7.68.0',
        ];

        $additionalHeaders = $this->getAdditionalHeaders();
        if (!empty($additionalHeaders)) {
            $headers = array_merge($headers, $additionalHeaders);
        }

        $this->request
            ->setHeaders($headers)
            ->setBasicAuth($credentials['client_id'], $credentials['client_secret'])
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setSaveLogs(true)
            ->setPostFields([
                'username' => $username,
                'password' => $password,
            ]);

        $this->response = $this->request->execute();
    }

    protected function checkResponse(): void
    {
        if (empty($this->response) || $this->response->http_code !== RequestConstants::HTTP_OK) {
            throw new \Exception('Error on authenticate Aiqfome');
        }
    }

    abstract public function setAdditionalHeaders(string $refreshToken): void;
    abstract protected function getAdditionalHeaders(): array;
}
