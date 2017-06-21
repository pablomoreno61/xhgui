<?php

\XH::autoStart();

class XH
{
    private static $profileType;

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

        $time = array_key_exists('REQUEST_TIME', $_SERVER) ? $_SERVER['REQUEST_TIME'] : time();
        $requestTimeFloat = explode('.', $_SERVER['REQUEST_TIME_FLOAT']);
        if (!isset($requestTimeFloat[1])) {
            $requestTimeFloat[1] = 0;
        }

        $requestTs = new MongoDate($time);
        $requestTsMicro = new MongoDate($requestTimeFloat[0], $requestTimeFloat[1]);

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
            $config = [
                'db.host' => 'mongodb://127.0.0.1:27017',
                'db.db' => 'xhprof',
                'db.options' => []
            ];

            $mongo = new MongoClient($config['db.host'], $config['db.options']);
//            $mongo->{$config['db.db']}->results->findOne();
            $mongo->{$config['db.db']}->results->insert($data, array('w' => 0));
        } catch (Exception $e) {
            error_log('xhgui - ' . $e->getMessage());
        }
    }

    static function autoStart() {
        if (extension_loaded('tideways')) {
            self::$profileType = 'tideways';
        } elseif (extension_loaded('uprofiler')) {
            self::$profileType = 'tideways';
        } elseif (extension_loaded('xhprof')) {
            self::$profileType = 'tideways';
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
