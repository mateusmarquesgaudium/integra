<?php

namespace src\Cache\Enums;

abstract class CacheKeys {
    const COMPANY_KEY = 'ch:co:{companyId}';
    const ENTERPRISE_KEY = 'ch:ep:{enterpriseId}';
    const ENTERPRISE_INTEGRATION_KEY = 'ch:int:{integrationId}';
    const PROVIDER_MERCHANTS_KEY = 'ch:pro:{provider}:{merchantId}';
}