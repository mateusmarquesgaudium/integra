<?php

return [
    //Dados para conexão com o Redis
    'redis' => [
        'hostname' => 'redis',
        'port' => 6379
    ],
    'params' => [
        'ifood' => [
            'client_id' => '53d63cc8-8749-4ef1-b033-1d046cb8c580',
            'client_secret' => 'o81wjwc0xiklp2xrakpwloe2of6r17ol64owtn68jeqla5k9tv7mqeu8dadkakujdpx6rcb20cozwgyzw3oidrvsdhcxz8jk33a',
            'webhook_client_id' => '53d63cc8-8749-4ef1-b033-1d046cb8c580',
            'webhook_client_secret' => 'o81wjwc0xiklp2xrakpwloe2of6r17ol64owtn68jeqla5k9tv7mqeu8dadkakujdpx6rcb20cozwgyzw3oidrvsdhcxz8jk33a',
            'grant_type' => 'client_credentials',
            'uri' => 'https://merchant-api.ifood.com.br',
            'taximachine_ifood_disponivel' => 'external-lb/api/integracaoIntegra/integracaoIfoodDisponivel',
            'taximachine_ifood_empresa_status' => 'external-lb/api/integracaoIntegra/statusIntegracaoIfood',
            'max_polling_try' => 3,
            'time_minutes_error_notification' => 10, // Em minutos
            'expire_order_code_confirmation' => 172800, // Em segundos
            'expire_order_code_invalid' => 300, // Em segundos
        ],
        'Iza' => [
            'url' => 'https://izacorehmg.iza.com.vc/api/integrations',
            'maxInstancesCreatePerson' => 1,
            'maxInstancesSearchContract' => 1,
            'maxInstancesCreatePeriod' => 1,
            'maxInstancesSendPosition' => 1,
            'maxInstancesCancelPeriod' => 1,
            'maxInstancesFinishPeriod' => 1,
            'maxInstancesWebhookPerson' => 1,
            'maxInstancesWebhookPeriod' => 1,
            'maxEventsAtATime' => 200,
            'maxEventsAtATimePerson' => 100,
            'maxEventsAtATimePosition' => 200,
            'maxAttemptsRequest' => 5, // 5 in production
            'minutesIntervalForEventsRetry' => 3, // minutes
            'maxInstancesCheckCompaniesForDisable' => 1,
            'minutesIntervalForDisableCompany' => 43200, // Intervalo de 30 dias em minutos
            'maxEventsAtATimeCheckCompaniesForDisable' => 100,
            'webhook' => [
                'url_person' => 'external-lb/api/integracaoIntegra/webhookIzaPerson',
                'token_person' => '3823aa8cd5fcf69401cc259173b76324',
                'url_period' => 'external-lb/api/integracaoIntegra/webhookIzaPeriod',
                'token_period' => '634104cdcd87e6865f0bd86a096fd3f2',
                'url_error' => 'external-lb/api/integracaoIntegra/webhookIzaErrors',
                'token_error' => '89341034cdcd87e6215f0bd86a096fd5f',
                'url_notification' => 'external-lb/api/integracaoIntegra/webhookIzaPendencias',
                'token_notification' => 'c343896eebcde8085724b00d06dd60b7',
                'url_disabled' => 'external-lb/api/integracaoIntegra/webhookIzaDisabledCentrals',
                'token_disabled' => 'de2077e2780a02bef11437ae418d2953',
            ],
            'url_verify_period' => 'external-lb/api/integracaoIntegra/verifyPeriodsIza',
            'token_verify_period' => 'd1c88f66eb373fd6f71b42df6d4cf9a5',
        ],
        'fcm' => [
            'baseUrl' => 'https://fcm.googleapis.com',
        ],
        'delivery' => [
            'url_webhook_orders' => 'external-lb/api/integracaoIntegra/receiveOrdersEvents',
            'jwt_secret' => 'c343896eebcde8085724b00d06dd60b7',
            'providers_allowed_jwt' => ['anotaai', 'neemo', 'aiqfome'],
            'urlWebhookEvents' => 'internal-lb:80/integra/delivery/webhook',
        ],
        'delivery_direto' => [
            'urlStoreAdmin' => 'https://deliverydireto.com.br/admin-api/v1',
            'urlStoreAdminToken' => 'https://deliverydireto.com.br/admin-api/token',
            'client_id' => '71dfe531-1dbe-4de3-8cb4-75c59a8ef0a4',
            'client_secret' => 'rBtphD1RkuCrBEK8DEGYoPWHWiLh9I2rhTVT',
        ],
        'anotaai' => [
            'urlBase' => 'https://api-parceiros.anota.ai/partnerauth',
            'urlOauth' => 'https://oauth-public-order-api.anota.ai/authentication/v1.0/oauth/token',
            'urlCreateWebhook' => 'https://public-api.anota.ai/developers-portal/v1.0/linkpage-by-token',
            'client_id' => '23c1305b-ffcd-44de-9f60-5d5922311bd6',
            'client_secret' => '42494812-fca7-409d-9cfa-c193e67354b9',
        ],
        'neemo' => [
            'urlBase' => 'https://deliveryapp.neemo.com.br/api/integration/v1',
            'orderModeActived' => true,
        ],
        'machine' => [
            'client_secret' => '9714347cca0af8803f254e49e393775c',
        ],
        'openDelivery' => [
            'signature' => '9714347cca0af8803f254e49e393775c',
            'secret' => '2d88c2a94b74408b8f1c1c68b5b4c93c5068d2d4a8da5f0ec7ef5241e8d1474b',
            'url_get_order_details' => 'external-lb/api/integracaoIntegra/getOrderDetails',
            'url_get_orders_tracking' => 'external-lb/api/integracaoIntegra/getOrdersTracking',
            'url_verify_credentials' => 'external-lb/api/integracaoIntegra/verifyCredentials',
            'maxAttemptsRequest' => 3,
            'maxEvents' => 20,
            'timeToRetry' => 10, // Tempo em segundos
            'provider' => [
                'jotaja' => [
                    'url' => 'https://webhook.site/9bab1203-c0fd-4412-a79b-678373b8584b',
                    'X-App-Id' => '6151cc5a-9f31-4491-8763-b42b0c9314c2',
                ],
                'saipos' => [
                    'url' => 'https://logistics-api-homolog.saipos.com',
                    'X-App-Id' => '6151cc5a-9f31-4491-8763-b42b0c9314c2',
                ],
                'cardapioweb' => [
                    'url' => 'https://integracao.sandbox.cardapioweb.com/api/open_delivery',
                    'client_id' => 'e2c366f1-e290-4e39-b24c-29391892cf42',
                    'client_secret' => 'c13e3f4e-ebc5-431e-8ce8-6e6b21426b05'
                ],
                'ecletica' => [
                    'url' => 'https://logistics-api-homolog.saipos.com',
                    'X-App-Id' => '6151cc5a-9f31-4491-8763-b42b0c9314c2',
                ],
                'promokit' => [
                    'url' => 'https://logistics-api-homolog.saipos.com',
                    'X-App-Id' => '6151cc5a-9f31-4491-8763-b42b0c9314c2',
                ],
            ],
            'credentials' => [
                '1d86e953-61b2-4bda-8ad2-cde8836d62b9' => 'cardapioweb',
            ]
        ],
        'cacheIntegra' => [
            'url_batch_entities' => 'external-lb/api/integracaoIntegra/batchEntities',
            'token' => 'c343896eebcde8085724b00d06dd60b7',
        ],
        'aiqfome' => [
            'signature' => '9714347cca0af8803f254e49e393775c',
            'url' => 'https://homolog-alfredo.aiqfome.com/alfredo',
            'url_send_merchants_invalid' => '{urlAmbiente}/api/integracaoIntegra/sendMerchantsInvalid',
            'credentials' => [
                'client_id' => 'gaudium',
                'client_secret' => 'o0jOTGQRAtgZzdTzrvpg8BKXGSDJa693',
                'aiq-client-authorization' => 'YWlxLWI6OEV1b0pwWjVWZjR1ZWVvdTNmVkNpcFl5UGlBdzR6R3BrT2RNZEFIT3hOTExrdDh4YzJjaWFDZW1RCg==',
                'aiq-user-agent' => 'aiq',
            ],
            'timeToRetry' => 10,
            'maxOrdersAtATime' => 20,
            'maxMerchantsInvalid' => 100,
            'maxAttemptsRequest' => 3,
        ],
        'delivery_much' => [
            'urlCreateIntegration' => 'https://bffweb-webhook-dev.devmuch.io/dispatcher/partner/machine',
        ],
        'discord' => [
            'payments_webhook' => 'https://discord.com/api/webhooks/1266082550112190535/L6Q-mKhX7AyzsxfRVb7EvDMLEIcyrizt0BYeimxD2kZscuTaikIdbujj8uAUPKGRVCBV',
        ],
        'pagarMe' => [
            'url' => 'external-lb/api/integracaoPagarmeV5',
            'logo_url' => 'https://avatars.githubusercontent.com/u/3846050?s=200',
        ],
        'pagZoop' => [
            'url' => 'external-lb/api/integracaoPagzoop',
            'logo_url' => 'https://www.zoop.com.br/apple-touch-icon.png',
        ],
    ]
];