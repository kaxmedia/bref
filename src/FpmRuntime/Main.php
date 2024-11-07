<?php declare(strict_types=1);

namespace Bref\FpmRuntime;

use Bref\Bref;
use Bref\LazySecretsLoader;
use Bref\Runtime\LambdaRuntime;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
class Main
{
    public static function run(): void
    {
        $brefInitStartTime = microtime(true);

        echo "BREF: initialising " . date('h:i:s', (int) $brefInitStartTime) . PHP_EOL;

        // In the FPM runtime process (our process) we want to log all errors and warnings
        ini_set('display_errors', '1');
        error_reporting(E_ALL);

        LazySecretsLoader::loadSecretEnvironmentVariables();

        Bref::triggerHooks('beforeStartup');
        Bref::events()->beforeStartup();

        $lambdaRuntime = LambdaRuntime::fromEnvironmentVariable('fpm');

        $appRoot = getenv('LAMBDA_TASK_ROOT');
        $handlerFile = $appRoot . '/' . getenv('_HANDLER');
        if (! is_file($handlerFile)) {
            $lambdaRuntime->failInitialization("Handler `$handlerFile` doesn't exist", 'Runtime.NoSuchHandler');
        }

        $phpFpm = new FpmHandler($handlerFile);
        try {
            $phpFpm->start();
        } catch (Throwable $e) {
            $lambdaRuntime->failInitialization(new RuntimeException('Error while starting PHP-FPM: ' . $e->getMessage(), 0, $e));
        }

        Bref::events()->afterStartup();

        $brefEndInitTime = microtime(true);
        $round = round($brefInitStartTime - $brefEndInitTime, 4);
        echo "BREF: initialised $round" . PHP_EOL;

        /** @phpstan-ignore-next-line */
        while (true) {
            $lambdaRuntime->processNextEvent($phpFpm);
            $brefEndEventTime = microtime(true);
            $round = round($brefInitStartTime - $brefEndEventTime, 4);
            echo "BREF: Ended Event $round" . PHP_EOL;
        }
    }
}
