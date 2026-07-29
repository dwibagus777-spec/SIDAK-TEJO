<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class DocsController extends BaseController
{
    public function json()
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title'       => 'SIDAK TEJO Enterprise Integration Platform API',
                'description' => 'Dokumentasi Resmi REST API & Webhook Service SIDAK TEJO PT PLN (Persero) UP3 Sidoarjo',
                'version'     => '1.0.0',
                'contact'     => [
                    'name'  => 'Tim IT UP3 Sidoarjo',
                    'url'   => 'https://sidaktejo.site',
                    'email' => 'admin@sidaktejo.site',
                ],
            ],
            'servers' => [
                ['url' => site_url('api/v1'), 'description' => 'Production Server (v1)'],
                ['url' => site_url('api/v2'), 'description' => 'Staging Server (v2)'],
                ['url' => site_url('api/v3'), 'description' => 'Next-Gen Server (v3)'],
            ],
            'paths' => [
                '/auth/login' => [
                    'post' => [
                        'summary'     => 'Authentikasi User & Generate Token/API Key',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'username' => ['type' => 'string'],
                                            'password' => ['type' => 'string'],
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'responses'   => ['200' => ['description' => 'Login Berhasil']],
                    ]
                ],
                '/health' => [
                    'get' => [
                        'summary'   => 'System Health Check & Resource Diagnostics',
                        'responses' => ['200' => ['description' => 'System Healthy']],
                    ]
                ],
                '/temuan' => [
                    'get' => [
                        'summary'    => 'Dapatkan Daftar Temuan Terfilter',
                        'security'   => [['BearerAuth' => []], ['ApiKeyAuth' => []]],
                        'parameters' => [
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'jenis_temuan', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ],
                        'responses'  => ['200' => ['description' => 'Daftar Temuan']],
                    ]
                ],
                '/work-orders' => [
                    'get' => [
                        'summary'   => 'Dapatkan Daftar Work Order',
                        'security'  => [['BearerAuth' => []], ['ApiKeyAuth' => []]],
                        'responses' => ['200' => ['description' => 'Daftar Work Order']],
                    ]
                ],
                '/assets' => [
                    'get' => [
                        'summary'   => 'Dapatkan Daftar Asset Jaringan',
                        'security'  => [['BearerAuth' => []], ['ApiKeyAuth' => []]],
                        'responses' => ['200' => ['description' => 'Daftar Asset']],
                    ]
                ],
                '/documents' => [
                    'get' => [
                        'summary'   => 'Dapatkan Daftar Dokumen Resmi & Checksum Verification',
                        'security'  => [['BearerAuth' => []], ['ApiKeyAuth' => []]],
                        'responses' => ['200' => ['description' => 'Daftar Dokumen']],
                    ]
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in'   => 'header',
                        'name' => 'X-API-Key',
                    ],
                ]
            ]
        ];

        return $this->response->setJSON($spec);
    }

    public function ui()
    {
        $html = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>OpenAPI 3 - SIDAK TEJO API Documentation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.9.0/swagger-ui.min.css" />
    <style>body { margin:0; padding:0; background:#f8fafc; }</style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.9.0/swagger-ui-bundle.min.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "' . site_url('api/docs/json') . '",
                dom_id: "#swagger-ui",
                deepLinking: true,
                presets: [SwaggerUIBundle.presets.apis],
            });
        };
    </script>
</body>
</html>';

        return $this->response->setBody($html);
    }
}
