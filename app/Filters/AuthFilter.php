<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session()->get('logged_in')) {
            $path = (string)$request->getUri()->getPath();
            $isJsonRequest = $request->isAJAX() 
                || str_contains($path, 'ajax') 
                || str_contains($path, 'api')
                || str_contains((string)$request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest')
                || str_contains((string)$request->getHeaderLine('Accept'), 'application/json');
            if ($isJsonRequest) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Sesi Anda telah berakhir. Silakan login kembali.'
                    ]);
            }
            return redirect()->to(site_url('login'))->with('error', 'Silakan login terlebih dahulu.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // ...
    }
}
