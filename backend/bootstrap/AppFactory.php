<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Controllers\AuthController;
use App\Middleware\CorsMiddleware;
use App\Middleware\SessionMiddleware;
use App\Routes\ApiRoutes;
use App\Support\GoogleOAuthService;
use App\Support\SessionManager;
use App\Support\UserRepository;
use DI\Container;
use Dotenv\Dotenv;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Throwable;

class AppFactory
{
    public static function create(): App
    {
        self::loadEnvironment();

        $container = new Container();
        self::registerSettings($container);
        self::registerDependencies($container);

        SlimAppFactory::setContainer($container);
        $app = SlimAppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');

        $app->add(new SessionMiddleware($settings['session']));
        $app->add(new CorsMiddleware($settings['cors']));

        $errorMiddleware = $app->addErrorMiddleware(
            (bool) $settings['displayErrorDetails'],
            true,
            true
        );

        $errorMiddleware->setDefaultErrorHandler(
            static function (
                ServerRequestInterface $request,
                Throwable $exception,
                bool $displayErrorDetails,
                bool $logErrors,
                bool $logErrorDetails
            ) use ($app): ResponseInterface {
                error_log(sprintf(
                    '[%s] %s %s',
                    (new \DateTimeImmutable())->format(DATE_ATOM),
                    get_class($exception),
                    $exception->getMessage()
                ));

                $response = $app->getResponseFactory()->createResponse(500);
                $payload = [
                    'error' => 'server_error',
                    'message' => $exception->getMessage(),
                ];

                $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

                return $response->withHeader('Content-Type', 'application/json');
            }
        );

        ApiRoutes::register($app);

        return $app;
    }

    private static function loadEnvironment(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();
    }

    private static function registerSettings(Container $container): void
    {
        $config = require __DIR__ . '/../config/settings.php';
        $container->set('settings', $config['settings']);
    }

    private static function registerDependencies(Container $container): void
    {
        $container->set(PDO::class, static function (Container $c): PDO {
            /** @var array<string, mixed> $settings */
            $settings = $c->get('settings');
            $database = $settings['database'];

            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $database['driver'],
                $database['host'],
                $database['port'],
                $database['database'],
                $database['charset']
            );

            $pdo = new PDO($dsn, $database['username'], $database['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return $pdo;
        });

        $container->set(UserRepository::class, static function (Container $c): UserRepository {
            return new UserRepository($c->get(PDO::class));
        });

        $container->set(GoogleOAuthService::class, static function (Container $c): GoogleOAuthService {
            /** @var array<string, mixed> $settings */
            $settings = $c->get('settings');
            $google = $settings['google_oauth'];

            return new GoogleOAuthService(
                $google['client_id'],
                $google['client_secret'],
                $google['redirect_uri'],
                $google['scopes'],
                $google['prompt'],
                $google['access_type']
            );
        });

        $container->set(SessionManager::class, static fn (): SessionManager => new SessionManager());

        $container->set(AuthController::class, static function (Container $c): AuthController {
            /** @var array<string, mixed> $settings */
            $settings = $c->get('settings');

            return new AuthController(
                $c->get(GoogleOAuthService::class),
                $c->get(UserRepository::class),
                $c->get(SessionManager::class),
                $settings['frontend_app_url']
            );
        });
    }
}
