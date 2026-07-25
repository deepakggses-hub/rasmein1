<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Exceptions as ExceptionsConfig;
use Throwable;

/**
 * Gives non-HTML clients one predictable error shape.
 *
 * CodeIgniter's own handler returns an empty body to any client that did not
 * ask for text/html, which leaves a fetch() call with nothing to read. This
 * wrapper answers those requests with the same envelope our controllers use:
 *
 *   {"status":"error","message":"...","errors":{}}
 *
 * The message is a fixed human phrase chosen from the status code — never
 * `Throwable::getMessage()`, which can carry SQL, file paths or class names.
 * Browser requests are delegated untouched to the framework handler so the
 * branded HTML error views still render.
 *
 * Composition rather than inheritance: CodeIgniter\Debug\ExceptionHandler is
 * declared final.
 */
class ApiExceptionHandler implements ExceptionHandlerInterface
{
    /** Safe, non-revealing messages. Anything unlisted gets the 500 text. */
    private const MESSAGES = [
        400 => 'That request could not be understood.',
        401 => 'You need to sign in to do that.',
        403 => 'You do not have access to that.',
        404 => 'That resource could not be found.',
        405 => 'That method is not allowed here.',
        409 => 'That conflicts with something that already exists.',
        422 => 'Some of the details supplied were not valid.',
        429 => 'Too many requests. Please slow down and try again shortly.',
    ];

    public function __construct(
        private readonly ExceptionsConfig $config
    ) {
    }

    /**
     * Should this failure be answered with JSON?
     *
     * Only for a real HTTP request that did not ask for HTML. Everything else —
     * and the command line above all — goes to the framework handler.
     *
     * The CLI check is not a nicety. A `spark` command that throws must print
     * the actual error; if it prints a JSON envelope instead, migrations,
     * seeders and cron jobs become undebuggable.
     */
    private function wantsJson(RequestInterface $request): bool
    {
        if (is_cli() || ! $request instanceof IncomingRequest) {
            return false;
        }

        return ! str_contains($request->getHeaderLine('accept'), 'text/html');
    }

    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        if (! $this->wantsJson($request)) {
            (new ExceptionHandler($this->config))
                ->handle($exception, $request, $response, $statusCode, $exitCode);

            return;
        }

        // The real reason goes to the log, where staff can read it.
        // It does not go to the client.
        log_message('error', '[{code}] {class}: {message} @ {file}:{line}', [
            'code'    => $statusCode,
            'class'   => $exception::class,
            'message' => $exception->getMessage(),
            'file'    => $exception->getFile(),
            'line'    => (string) $exception->getLine(),
        ]);

        try {
            $response->setStatusCode($statusCode);
        } catch (Throwable) {
            $statusCode = 500;
            $response->setStatusCode(500);
        }

        $body = [
            'status'  => 'error',
            'message' => self::MESSAGES[$statusCode] ?? 'Something went wrong on our side.',
            'errors'  => [],
        ];

        $response
            ->setContentType('application/json')
            ->setBody((string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->send();

        exit($exitCode);
    }
}
