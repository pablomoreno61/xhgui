<?php

\XH::autoStart();

class XH
{
    private static $profileType;
    private static $mongoConfig = [
        'db.host' => 'mongodb://mongodb:27017',
        'db.db' => 'xhprof',
        'db.options' => []
    ];

    static function start()
    {
        if (self::$profileType === 'tideways') {
            tideways_enable(
                TIDEWAYS_FLAGS_CPU | TIDEWAYS_FLAGS_MEMORY | TIDEWAYS_FLAGS_NO_SPANS | TIDEWAYS_FLAGS_NO_BUILTINS
            );
        } elseif (self::$profileType === 'uprofiler') {
            uprofiler_enable(UPROFILER_FLAGS_CPU | UPROFILER_FLAGS_MEMORY);
        } elseif (self::$profileType === xhprof) {
            if (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION > 4) {
                xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY | XHPROF_FLAGS_NO_BUILTINS);
            } else {
                xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
            }
        }
    }

    static function stop()
    {
        $disable = self::$profileType.'_disable';
        $data['profile'] = $disable();

        ignore_user_abort(true);
        flush();

        $uri = array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : null;
        if (empty($uri) && isset($_SERVER['argv'])) {
            $cmd = basename($_SERVER['argv'][0]);
            $uri = $cmd . ' ' . implode(' ', array_slice($_SERVER['argv'], 1));
        }

        $time = empty($_SERVER['REQUEST_TIME']) ? (new DateTime())->getTimestamp() : $_SERVER['REQUEST_TIME'];
        $milliseconds = empty($_SERVER['REQUEST_TIME_FLOAT']) ? (new DateTime())->getTimestamp() : $_SERVER['REQUEST_TIME_FLOAT'];

        $requestTs = new MongoDB\BSON\UTCDateTime(DateTime::createFromFormat('U', $time));
        $requestTsMicro = new MongoDB\BSON\UTCDateTime(preg_replace('/\D/', '', $milliseconds));

        $data['meta'] = [
            'url' => $uri,
            'SERVER' => $_SERVER,
            'get' => $_GET,
            'env' => $_ENV,
            'simple_url' => preg_replace('/\=\d+/', '', $uri),
            'request_ts' => $requestTs,
            'request_ts_micro' => $requestTsMicro,
            'request_date' => date('Y-m-d', $time),
        ];

        try {
            $manager = new MongoDB\Driver\Manager(self::$mongoConfig['db.host']);
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert($data);
            $cursor = $manager->executeBulkWrite(self::$mongoConfig['db.db'].'.results', $bulk);
        } catch (Exception $e) {
            error_log('xhgui - ' . $e->getMessage());
        }
    }

    static function autoStart() {
        if (extension_loaded('tideways')) {
            self::$profileType = 'tideways';
        } elseif (extension_loaded('uprofiler')) {
            self::$profileType = 'uprofiler';
        } elseif (extension_loaded('xhprof')) {
            self::$profileType = 'xhprof';
        }
        if (empty(self::$profileType)) {
            error_log('xhgui - either extension xhprof, uprofiler or tideways must be loaded');
            return;
        }
        if (!extension_loaded('mongo') && !extension_loaded('mongodb')) {
            error_log('xhgui - extension mongo not loaded');
            return;
        }
        \XH::start();
        register_shutdown_function(
            function () {
                \XH::stop();
            }
        );
    }
}
