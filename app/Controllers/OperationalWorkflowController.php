<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\EnterpriseEventFabricService;
use App\Services\WorkflowOrchestrationService;
use App\Services\NotificationOrchestrationService;
use CodeIgniter\HTTP\ResponseInterface;

class OperationalWorkflowController extends BaseController
{
    protected EnterpriseEventFabricService $eventFabricService;
    protected WorkflowOrchestrationService $workflowService;
    protected NotificationOrchestrationService $notificationService;

    public function __construct()
    {
        $this->eventFabricService  = new EnterpriseEventFabricService();
        $this->workflowService     = new WorkflowOrchestrationService();
        $this->notificationService = new NotificationOrchestrationService();
    }

    /**
     * GET /workflow/event-fabric-status
     * Enterprise Event Fabric Status & Schema Registry API (Phase 3E)
     */
    public function eventFabricStatus(): ResponseInterface
    {
        $status = $this->eventFabricService->getEventFabricStatus();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $status,
        ]);
    }

    /**
     * POST /workflow/publish-event
     * Publish Domain Event API (Phase 3E)
     */
    public function publishEvent(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $eventName     = $json['event_name'] ?? 'ObservationSubmittedEvent';
        $payload       = $json['payload'] ?? ['asset_id' => 1, 'severity' => 'HIGH'];
        $correlationId = $json['correlation_id'] ?? null;

        $result = $this->eventFabricService->publishDomainEvent($eventName, $payload, $correlationId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /workflow/execute-event-driven
     * Execute Event-Driven Workflow Automation API (Phase 3E)
     */
    public function executeEventDriven(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $assetId      = (int)($json['asset_id'] ?? 1);
        $triggerEvent = $json['trigger_event'] ?? 'ObservationSubmittedEvent';

        $result = $this->workflowService->executeEventDrivenWorkflow($assetId, $triggerEvent);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /workflow/dispatch-notification
     * Dispatch Alert & Multi-Channel Notification API (Phase 3E)
     */
    public function dispatchNotification(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $channel       = $json['channel'] ?? 'COMMAND_CENTER_BROADCAST';
        $recipientRole = $json['recipient_role'] ?? 'SUPERVISOR_ULP';
        $message       = $json['message'] ?? 'Peringatan SLA: Kasus Darurat memerlukan penanganan segera.';
        $correlationId = $json['correlation_id'] ?? null;

        $result = $this->notificationService->dispatchNotificationAlert(
            $channel,
            $recipientRole,
            $message,
            $correlationId
        );

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
