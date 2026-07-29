<?php

namespace App\Controllers;

use App\Repositories\IntegrationRepository;
use App\Services\IntegrationService;

class IntegrationCenter extends BaseController
{
    private IntegrationRepository $repo;
    private IntegrationService $service;

    public function __construct()
    {
        $this->repo = new IntegrationRepository();
        $this->service = new IntegrationService();
    }

    public function index()
    {
        $data = [
            'title'     => 'Integration Center & Enterprise Integration Platform',
            'stats'     => $this->repo->getApiDashboardStats(),
            'logs'      => $this->repo->getApiLogs(30),
            'api_keys'  => $this->repo->getApiKeys(),
            'webhooks'  => $this->repo->getWebhooks(),
            'health'    => $this->service->healthCheck(),
            'drivers'   => ['REST', 'SOAP', 'MQTT', 'FTP', 'SFTP', 'CSV', 'JSON', 'XML'],
        ];

        return view('integration/index', $data);
    }

    public function generateApiKey()
    {
        $userId = (int)(session()->get('user_id') ?? 1);
        $res = $this->service->generateApiKey($userId);

        return redirect()->to(site_url('integration'))->with('success', 'API Key berhasil dibuat: ' . $res['api_key']);
    }

    public function registerWebhook()
    {
        $url = $this->request->getPost('url') ?? '';
        $event = $this->request->getPost('event') ?? 'Temuan Baru';

        if (!empty($url)) {
            $this->service->registerWebhook($url, $event);
            return redirect()->to(site_url('integration'))->with('success', 'Webhook berhasil didaftarkan');
        }

        return redirect()->to(site_url('integration'))->with('error', 'URL Webhook wajib diisi');
    }

    public function testWebhook($id)
    {
        $this->service->fireWebhook('Test Event', [
            'message' => 'Tes pengiriman webhook dari SIDAK TEJO Integration Platform',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        return redirect()->to(site_url('integration'))->with('success', 'Webhook test event berhasil ditembakkan');
    }

    public function exportData()
    {
        $format = $this->request->getGet('format') ?? 'json';
        $sampleData = [
            'system'    => 'SIDAK TEJO Enterprise Integration Platform',
            'timestamp' => date('Y-m-d H:i:s'),
            'stats'     => $this->repo->getApiDashboardStats(),
        ];

        $output = $this->service->exportData($sampleData, $format);

        $mime = match($format) {
            'json' => 'application/json',
            'xml'  => 'application/xml',
            'csv'  => 'text/csv',
            default => 'text/plain',
        };

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'attachment; filename="export_integration_' . date('Ymd_His') . '.' . $format . '"')
            ->setBody($output);
    }
}
