<?php

namespace App\Utils;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;

// Journalisation applicative structurée (JSON), séparée du suivi d'erreurs
// Sentry (voir ErrorTracking) : ce logger sert à consulter/grepper les logs
// localement, Sentry sert à l'alerting et l'agrégation.
class Logger
{
    private static ?Monolog $instance = null;

    public static function init(): void
    {
        if (self::$instance !== null) {
            return;
        }

        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $level = $debug ? Level::Debug : Level::Info;

        $handler = new StreamHandler(__DIR__ . '/../../../logs/api.log', $level);
        $handler->setFormatter(new JsonFormatter());

        $logger = new Monolog('api');
        $logger->pushHandler($handler);

        self::$instance = $logger;
    }

    public static function get(): Monolog
    {
        self::init();

        return self::$instance;
    }
}
