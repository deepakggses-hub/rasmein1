<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SettingsService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Rasmein;
use Psr\Log\LoggerInterface;

/**
 * Shared base for every controller in the application.
 *
 * Controllers stay thin: they read input, delegate to a Model or Service, and
 * choose a view. Business rules do not live here.
 */
abstract class BaseController extends Controller
{
    /** @var list<string> */
    protected $helpers = ['form', 'url', 'text', 'rasmein'];

    protected SettingsService $settings;
    protected Rasmein $brand;

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);

        $this->settings = service('settings');
        $this->brand    = config(Rasmein::class);
    }

    /**
     * A JSON envelope with one fixed shape, so a client never has to guess
     * and an exception message never leaks into a response body.
     */
    protected function jsonOk(array $data = [], string $message = ''): ResponseInterface
    {
        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => $message,
            'data'    => $data,
        ]);
    }

    protected function jsonFail(
        string $message = 'Something went wrong',
        int $status = 400,
        array $errors = []
    ): ResponseInterface {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'status'  => 'error',
                'message' => $message,
                'errors'  => $errors,
            ]);
    }
}
