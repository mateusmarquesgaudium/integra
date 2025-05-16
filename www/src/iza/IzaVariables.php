<?php

namespace src\iza;

// TODO: Implementar enum em uma possível atualização futura para o PHP 8.1
class IzaVariables {

    public const CONTRACT_PER_REQUEST = 'per_request';
    public const CONTRACT_PER_STOP = 'per_stop';

    public const KEY_MONITOR_CREATE_PERSON = 'it:iza:mt:cs';
    public const KEY_MONITOR_SEARCH_CONTRACT = 'it:iza:mt:sc';
    public const KEY_MONITOR_CREATE_PERIOD = 'it:iza:mt:cp';
    public const KEY_MONITOR_CREATE_STOP_PERIOD = 'it:iza:mt:csp';
    public const KEY_MONITOR_CANCEL_PERIOD = 'it:iza:mt:clp';
    public const KEY_MONITOR_CANCEL_STOP_PERIOD = 'it:iza:mt:clsp';
    public const KEY_MONITOR_FINISH_PERIOD = 'it:iza:mt:fp';
    public const KEY_MONITOR_FINISH_STOP_PERIOD = 'it:iza:mt:fsp';
    public const KEY_MONITOR_SEND_POSITION = 'it:iza:mt:sp';
    public const KEY_MONITOR_WEBHOOK_PERSON = 'it:iza:mt:wpn';
    public const KEY_MONITOR_WEBHOOK_PERIOD = 'it:iza:mt:wpd';
    public const KEY_MONITOR_WEBHOOK_NOTIFICATION_PENDING = 'it:iza:mt:npd';
    public const KEY_MONITOR_VERIFY_PERIOD_IN_PROGRESS = 'it:iza:mt:vpip';
    public const KEY_MONITOR_DISABLED_CENTRAL = 'it:iza:mt:dc';
    public const KEY_MONITOR_CHECK_COMPANIES_FOR_DISABLE = 'it:iza:mt:ccfd';

    public const KEY_EVENTS_CREATE_PERSON = 'it:iza:ev:cs';
    public const KEY_EVENTS_CREATE_PERIOD = 'it:iza:ev:cp';
    public const KEY_EVENTS_CREATE_STOP_PERIOD = 'it:iza:ev:csp';
    public const KEY_EVENTS_CANCEL_PERIOD = 'it:iza:ev:cl';
    public const KEY_EVENTS_CANCEL_STOP_PERIOD = 'it:iza:ev:cls';
    public const KEY_EVENTS_FINISH_PERIOD = 'it:iza:ev:fp';
    public const KEY_EVENTS_FINISH_STOP_PERIOD = 'it:iza:ev:fsp';
    public const KEY_EVENTS_SEARCH_CONTRACT = 'it:iza:ev:sc';
    public const KEY_EVENTS_IN_PROGRESS_PERIOD = 'it:iza:ev:ip:';
    public const KEY_EVENTS_IN_PROGRESS_STOP_PERIOD = 'it:iza:ev:ipsp:';
    public const KEY_EVENTS_FORBIDDEN_REQUEST = 'it:iza:ev:fr';
    public const KEY_EVENTS_DISABLED_CENTRAL = 'it:iza:ev:dc';
    public const KEY_EVENTS_POSITION_PERSON = 'it:iza:ev:pos';

    public const KEY_EVENTS_ERROR_CREATE_PERSON = 'it:iza:ev:err:cs:';
    public const KEY_EVENTS_ERROR_CREATE_PERIOD = 'it:iza:ev:err:cp';
    public const KEY_EVENTS_ERROR_CREATE_STOP_PERIOD = 'it:iza:ev:err:csp';
    public const KEY_EVENTS_ERROR_CANCEL_PERIOD = 'it:iza:ev:err:cl';
    public const KEY_EVENTS_ERROR_CANCEL_STOP_PERIOD = 'it:iza:ev:err:cls';
    public const KEY_EVENTS_ERROR_FINISH_PERIOD = 'it:iza:ev:err:fp';
    public const KEY_EVENTS_ERROR_FINISH_STOP_PERIOD = 'it:iza:ev:err:fsp';
    public const KEY_EVENTS_ERROR_CREATE_CONTRACT = 'it:iza:ev:err:ct';
    public const KEY_EVENTS_ERROR_SEARCH_CONTRACT = 'it:iza:ev:err:sc';

    public const KEY_DATE_MONITOR_ERRORS = 'it:iza:mt:err:date';

    public const KEY_WEBHOOK_CREATE_PERSON = 'it:iza:hook:cs';
    public const KEY_WEBHOOK_CREATE_PERIOD = 'it:iza:hook:cp';
    public const KEY_WEBHOOK_ERROR_CREATE_PERSON = 'it:iza:hook:ecs';

    public const ERROR_NOT_FOUND = 'Not Found';
    public const ERROR_CANCELATION_OUT_OF_TIME_LIMIT = 'cancelation_out_of_time_limit';
    public const ERROR_FINISHED_AT_MUST_COME_AFTER_STARTED_AT = 'finished_at must come after started_at';

    public const DAYS_FOR_EXPIRE_EVENTS_RETRY = 2;

    public const AGE_CODE_ERROR = 'unsupported_age';
    public const CPF_CODE_ERROR = 'invalid_verifier';
    public const CPF_NULL_CODE_ERROR = 'cpf_null';
    public const EMAIL_CODE_ERROR = 'email';
    public const PHONE_CODE_ERROR = 'main_cell_phone';
    public const INVALID_CODE_ERROR = 'invalid_length';
    public const DETAILS_CODE_ERROR = 'details';

    public const IZA_ERRORS_ORIGIN = 0;
    public const BD_ERRORS_ORIGIN = 1;

    public const MAX_FORBIDDEN_REQUESTS = 5;
}