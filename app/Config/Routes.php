<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// --- Rute Terbuka (Public Routes) ---
$routes->match(['GET', 'POST'], 'login', 'Auth::login');
$routes->get('/', 'Auth::login');
$routes->match(['GET', 'POST'], 'logout', 'Auth::logout');
$routes->match(['GET', 'POST'], 'auth/logout', 'Auth::logout');

// Rute Cron Job Otomatis Harian Backup (Hostinger Compatible)
$routes->match(['GET', 'POST'], 'backup/cron', 'Backup::cron');

// Background Queue Worker Cron (Hostinger cron, token secured)
$routes->get('queue/run', 'QueueWorker::run');

// Production Database DDL Auto-Migrate & Catalog Seeder Route (Publicly callable for deployment sync)
$routes->get('master-assets/auto-migrate', 'MigrateController::autoMigrate');
$routes->get('master-assets/auto-deploy', 'MigrateController::autoDeploy');
$routes->get('auto-deploy', 'MigrateController::autoDeploy');
$routes->get('master-assets/debug-json', 'MigrateController::debugJson');
$routes->get('api/debug-assets', 'Api::debugAssets');
$routes->get('api/debug-filter', 'Api::debugFilter');
$routes->get('api/forensic-asset-trace', 'Api::forensicTrace');
$routes->get('api/system/version', 'Api::version');

// Persistent Photo Streaming Route (Public)
$routes->get('foto/(:any)', 'PhotoController::show/$1');

// --- Rute Terproteksi Login (Protected Routes) ---
$routes->group('', ['filter' => 'auth'], function ($routes) {
    // Main Dashboard Web Route
    $routes->get('dashboard', 'Dashboard::index');
    $routes->match(['GET', 'POST'], 'dashboard', 'Dashboard::index');
    
    // AI Predictive Maintenance & AI Center Routes
    $routes->get('ai-center', 'AiPredictiveController::index');
    $routes->get('ai-predictive', 'AiPredictiveController::index');
    $routes->get('ai-predictive/api-data', 'AiPredictiveController::apiData');
    $routes->get('ai-predictive/export-dataset', 'AiPredictiveController::exportDataset');

    // SIDAK AI Copilot Routes (Phase 34)
    $routes->get('ai-copilot', 'AiCopilotController::index');
    $routes->get('sidak-ai', 'AiCopilotController::index');
    $routes->post('ai-copilot/ask', 'AiCopilotController::ask');

    // Smart Work Order Center Routes (Phase 35)
    $routes->get('smart-wo', 'SmartWoController::index');
    $routes->get('work-orders/smart', 'SmartWoController::index');

    // Smart AI Recommendation Route (Phase 38)
    $routes->match(['get', 'post'], 'ai/recommendation', 'AiPredictiveController::recommendation');

    // Digital Evidence & Audit Trail Routes (Phase 39)
    $routes->get('audit-log', 'AuditTrailController::index');
    $routes->get('digital-evidence/(:num)', 'AuditTrailController::evidence/$1');
    $routes->get('time-machine/(:num)', 'AuditTrailController::timeMachine/$1');

    // Smart Search — Global Multi-Table Search (Step 6)
    $routes->get('smart-search', 'SmartSearch::index');
    $routes->get('smart-search/api', 'SmartSearch::api');

    // Background Queue Worker Status (Step 4)
    $routes->get('queue/status', 'QueueWorker::status');

    // Phase 32 — Personal Dashboard & Gamification
    $routes->get('my-dashboard', 'PersonalDashboardController::index');
    $routes->get('my-dashboard/api-stats', 'PersonalDashboardController::apiStats');
    $routes->get('my-dashboard/timeline', 'PersonalDashboardController::timeline');
    $routes->get('ranking', 'PersonalDashboardController::ranking');

    // Asset Health Index & Predictive Maintenance Routes (Phase 40)
    $routes->get('asset-health', 'AssetHealthController::index');
    $routes->get('asset-health/explanation/(:num)', 'AssetHealthController::explanation/$1');
    $routes->post('field-observation/vegetation', 'FieldObservationController::storeVegetation');
    // Preventive Intelligence & Risk Radar (Phase CC-01, CC-02, CC-03)
    $routes->get('preventive-intelligence', 'PreventiveIntelligenceController::index');
    $routes->get('preventive-intelligence/queue', 'PreventiveIntelligenceController::reviewQueue');
    $routes->get('preventive-intelligence/workspace/(:num)', 'PreventiveIntelligenceController::workspace/$1');
    $routes->post('preventive-intelligence/lifecycle/transition', 'PreventiveIntelligenceController::transitionLifecycle');
    $routes->get('preventive-intelligence/timeline/(:num)', 'PreventiveIntelligenceController::timeline/$1');
    $routes->get('preventive-intelligence/correlate-finding/(:num)', 'PreventiveIntelligenceController::correlateFinding/$1');
    $routes->get('preventive-intelligence/feeder-risk-radar/(:num)', 'PreventiveIntelligenceController::feederRiskRadar/$1');
    $routes->get('preventive-intelligence/feeder/(:num)/map-data', 'PreventiveIntelligenceController::mapData/$1');
    $routes->get('preventive-intelligence/section/(:num)/intelligence', 'PreventiveIntelligenceController::sectionIntelligence/$1');
    $routes->get('preventive-intelligence/snapshot/(:num)/score-breakdown', 'PreventiveIntelligenceController::scoreBreakdown/$1');
    $routes->get('preventive-intelligence/case/(:segment)/detail', 'PreventiveIntelligenceController::caseDetail/$1');
    $routes->post('preventive-intelligence/supervisor-signoff', 'PreventiveIntelligenceController::supervisorSignoff');

    // Executive Intelligence & Decision Analytics (Phase CC-04 & CC-05)
    $routes->get('executive-intelligence', 'ExecutiveIntelligenceController::index');
    $routes->get('executive-intelligence/(:num)', 'ExecutiveIntelligenceController::index/$1');
    $routes->get('api/executive-intelligence/feeder/(:num)', 'ExecutiveIntelligenceController::apiFeeder/$1');
    $routes->post('api/executive-intelligence/approve-action', 'ExecutiveIntelligenceController::approveAction');
    $routes->post('api/executive-intelligence/verify-outcome', 'ExecutiveIntelligenceController::verifyOutcome');
    $routes->get('executive-intelligence/api/summary', 'ExecutiveIntelligenceController::apiSummary');
    $routes->get('executive-intelligence/export', 'ExecutiveIntelligenceController::exportSummary');
    $routes->get('executive-intelligence/export/evidence-pack', 'EvidenceExportController::downloadPack');
    $routes->get('executive-intelligence/export/print-report', 'EvidenceExportController::printReport');
    $routes->get('executive-intelligence/export/verify-hash', 'EvidenceExportController::verifyHash');

    // Held Records Resolution Workspace (Phase CR-02)
    $routes->get('held-records', 'HeldRecordsWorkspaceController::index');
    $routes->get('api/held-records', 'HeldRecordsWorkspaceController::apiList');
    $routes->post('api/held-records/dry-run', 'HeldRecordsWorkspaceController::apiDryRun');

    // Historical Pattern Intelligence & Recurrence Analytics (Phase CR-03)
    $routes->get('pattern-intelligence', 'HistoricalPatternController::index');
    $routes->get('api/pattern-intelligence/summary', 'HistoricalPatternController::apiSummary');
    $routes->get('api/pattern-intelligence/feeder/(:num)', 'HistoricalPatternController::apiFeeder/$1');

    // Operational Dispatch Workflow & Human Action Governance (Phase CR-04)
    $routes->get('operational-dispatch', 'OperationalDispatchController::index');
    $routes->get('api/operational-dispatch/queue', 'OperationalDispatchController::apiQueue');
    $routes->post('api/operational-dispatch/create-draft', 'OperationalDispatchController::apiCreateDraft');
    $routes->post('api/operational-dispatch/transition', 'OperationalDispatchController::apiTransition');

    // Physical Asset Truth Layer & GIS Asset Intelligence (Phase CR-05)
    $routes->get('asset-intelligence', 'AssetIntelligenceController::index');
    $routes->get('assets/truth-layer', 'AssetIntelligenceController::index');
    $routes->get('api/assets/summary', 'AssetIntelligenceController::apiSummary');
    $routes->get('api/assets/tree/(:num)', 'AssetIntelligenceController::apiTree/$1');
    $routes->post('api/assets/dry-run', 'AssetIntelligenceController::apiDryRun');
    $routes->post('api/assets/controlled-commit', 'AssetIntelligenceController::apiCommit');

    // Construction, Material & Network Configuration Intelligence (CR-06)
    $routes->get('construction-intelligence', 'ConstructionIntelligenceController::index');
    $routes->get('api/construction-intelligence/summary', 'ConstructionIntelligenceController::apiDataQuality');
    $routes->get('api/sld/section/(:num)', 'ConstructionIntelligenceController::apiSld/$1');
    $routes->get('api/feeder-health/(:num)', 'ConstructionIntelligenceController::apiFeederHealth/$1');

    // Network Configuration Operational Activation (Phase CR-06F)
    $routes->get('network-configuration', 'NetworkConfigurationController::index');
    $routes->post('network-configuration/upload', 'NetworkConfigurationController::upload');
    $routes->post('network-configuration/preview', 'NetworkConfigurationController::preview');
    $routes->get('network-configuration/template', 'NetworkConfigurationController::downloadTemplate');

    // Dynamic Single Line Diagram (Phase CR-06H)
    $routes->get('sld', 'DynamicSldController::view');
    $routes->get('sld/view', 'DynamicSldController::view');
    $routes->get('sld/view/(:num)', 'DynamicSldController::view/$1');
    $routes->get('sld/feeder/(:num)', 'DynamicSldController::getFeederGraph/$1');
    $routes->get('sld/section-detail/(:num)', 'DynamicSldController::getSectionDetail/$1');

    // Field Inspection & Living Asset Condition (Phase CR-06)
    $routes->get('inspections', 'FieldInspectionController::index');
    $routes->get('api/inspections/summary', 'FieldInspectionController::apiSummary');
    $routes->post('api/inspections/create-session', 'FieldInspectionController::apiCreateSession');
    $routes->post('api/inspections/transition-session', 'FieldInspectionController::apiTransitionSession');
    $routes->post('api/inspections/record-observation', 'FieldInspectionController::apiRecordObservation');
    $routes->post('api/inspections/record-material', 'FieldInspectionController::apiRecordMaterial');

    // JTM Construction Taxonomy & BOM Intelligence (Phase CR-07)
    $routes->get('bom', 'JtmConstructionBomController::index');
    $routes->get('api/bom/summary', 'JtmConstructionBomController::apiSummary');
    $routes->get('api/bom/materials', 'JtmConstructionBomController::apiMaterials');
    $routes->get('api/bom/constructions', 'JtmConstructionBomController::apiConstructions');
    $routes->get('api/bom/detail/(:segment)', 'JtmConstructionBomController::apiBomDetail/$1');
    $routes->post('api/bom/resolve-alias', 'JtmConstructionBomController::apiResolveAlias');
    $routes->post('api/bom/estimate', 'JtmConstructionBomController::apiEstimate');

    // Spatial BOM & Finding-to-Material Bridge (Phase CR-08)
    $routes->get('spatial-bom', 'SpatialPreventiveMaterialController::index');
    $routes->get('api/spatial-bom/summary', 'SpatialPreventiveMaterialController::apiSummary');
    $routes->get('api/spatial-bom/asset/(:num)', 'SpatialPreventiveMaterialController::apiAssetDetail/$1');
    $routes->post('api/spatial-bom/feeder-recommendation', 'SpatialPreventiveMaterialController::apiFeederRecommendation');

    // Executive Command Center & Material Readiness Suite (Phase CC-06)
    $routes->get('executive/command-center', 'ExecutiveReliabilityController::index');
    $routes->get('api/executive/summary', 'ExecutiveReliabilityController::apiSummary');
    $routes->get('api/executive/giri-feeders', 'ExecutiveReliabilityController::apiGiriFeeders');
    $routes->get('api/executive/asset-radar', 'ExecutiveReliabilityController::apiAssetRadar');
    $routes->get('api/executive/material-gap', 'ExecutiveReliabilityController::apiMaterialGap');
    $routes->post('api/executive/budget-estimation', 'ExecutiveReliabilityController::apiBudgetEstimation');

    // Shutdown Scope, SLD Work Planning & Material Allocation Evidence (Phase CC-06 Group G)
    $routes->get('planning/shutdown-workspace', 'ShutdownWorkPlanningController::index');
    $routes->get('api/planning/inspection-catalog', 'ShutdownWorkPlanningController::apiInspectionCatalog');
    $routes->get('api/planning/feeder-sections/(:num)', 'ShutdownWorkPlanningController::apiFeederSections/$1');
    $routes->post('api/planning/compose-scope', 'ShutdownWorkPlanningController::apiComposeScope');
    $routes->get('api/planning/plan/(:segment)', 'ShutdownWorkPlanningController::apiPlanDetail/$1');
    $routes->get('api/planning/summary', 'ShutdownWorkPlanningController::apiPlanningSummary');

    // Material Request Governance & Official SPM Voucher Suite (Phase MR-01 Group H - Tahap B)
    $routes->get('planning/material-requests', 'MaterialRequestGovernanceController::index');
    $routes->get('planning/spm-voucher/(:segment)', 'MaterialRequestGovernanceController::spmVoucher/$1');
    $routes->get('api/material-requests/packages', 'MaterialRequestGovernanceController::apiListPackages');
    $routes->get('api/material-requests/package/(:segment)', 'MaterialRequestGovernanceController::apiPackageDetail/$1');
    $routes->post('api/material-requests/create-package', 'MaterialRequestGovernanceController::apiCreatePackage');
    $routes->post('api/material-requests/technical-review', 'MaterialRequestGovernanceController::apiTechnicalReview');
    $routes->post('api/material-requests/management-approve', 'MaterialRequestGovernanceController::apiManagementApprove');
    $routes->get('api/material-requests/summary', 'MaterialRequestGovernanceController::apiGovernanceSummary');

    // Wave 2 Operational Planning Candidate Bridge (Phase OP-01 & OP-02)
    $routes->get('operational-planning/candidates', 'OperationalPlanningController::candidates');
    $routes->post('operational-planning/candidates/promote', 'OperationalPlanningController::promote');
    $routes->get('operational-planning/candidates/(:num)', 'OperationalPlanningController::detail/$1');
    $routes->post('operational-planning/candidates/(:num)/transition', 'OperationalPlanningController::transition/$1');
    $routes->get('operational-planning/api/candidates', 'OperationalPlanningController::apiCandidates');

    // Wave 2 Human Operational Planning Workspace (Phase OP-02)
    $routes->get('operational-planning/workspace', 'OperationalPlanningWorkspaceController::index');
    $routes->get('operational-planning/workspace/create/(:num)', 'OperationalPlanningWorkspaceController::create/$1');
    $routes->post('operational-planning/workspace/store', 'OperationalPlanningWorkspaceController::store');
    $routes->get('operational-planning/workspace/detail/(:num)', 'OperationalPlanningWorkspaceController::detail/$1');
    $routes->post('operational-planning/workspace/transition/(:num)', 'OperationalPlanningWorkspaceController::transition/$1');

    // Wave 2 Portfolio Governance & Prioritization Fabric (Phase OP-03)
    $routes->get('operational-planning/portfolios', 'OperationalPlanningPortfolioController::index');
    $routes->get('operational-planning/portfolios/create', 'OperationalPlanningPortfolioController::create');
    $routes->post('operational-planning/portfolios/store', 'OperationalPlanningPortfolioController::store');
    $routes->get('operational-planning/portfolios/detail/(:num)', 'OperationalPlanningPortfolioController::detail/$1');
    $routes->post('operational-planning/portfolios/set-item-tier/(:num)', 'OperationalPlanningPortfolioController::setItemTier/$1');
    $routes->post('operational-planning/portfolios/transition/(:num)', 'OperationalPlanningPortfolioController::transition/$1');

    // Wave 2 Governed Scheduling & Resource Capacity Planning Bridge (Phase OP-04)
    $routes->get('operational-planning/scheduling', 'OperationalSchedulingController::index');
    $routes->get('operational-planning/scheduling/create/(:num)', 'OperationalSchedulingController::create/$1');
    $routes->post('operational-planning/scheduling/store', 'OperationalSchedulingController::store');
    $routes->get('operational-planning/scheduling/detail/(:num)', 'OperationalSchedulingController::detail/$1');
    $routes->post('operational-planning/scheduling/slot/(:num)', 'OperationalSchedulingController::updateSlot/$1');
    $routes->post('operational-planning/scheduling/transition/(:num)', 'OperationalSchedulingController::transition/$1');
    $routes->post('operational-planning/scheduling/supersede/(:num)', 'OperationalSchedulingController::supersede/$1');

    // Wave 2 Execution Readiness Gate & Work Authorization Governance (Phase OP-05)
    $routes->get('operational-planning/authorizations', 'OperationalWorkAuthorizationController::index');
    $routes->get('operational-planning/authorizations/generate/(:num)', 'OperationalWorkAuthorizationController::generate/$1');
    $routes->get('operational-planning/authorizations/detail/(:num)', 'OperationalWorkAuthorizationController::detail/$1');
    $routes->post('operational-planning/authorizations/verify-readiness/(:num)', 'OperationalWorkAuthorizationController::verifyReadiness/$1');
    $routes->post('operational-planning/authorizations/transition/(:num)', 'OperationalWorkAuthorizationController::transition/$1');

    // Wave 2 Controlled Field Execution Record & Human Progress Governance (Phase OP-06)
    $routes->get('operational-planning/executions', 'OperationalFieldExecutionController::index');
    $routes->get('operational-planning/executions/initiate/(:num)', 'OperationalFieldExecutionController::initiate/$1');
    $routes->get('operational-planning/executions/detail/(:num)', 'OperationalFieldExecutionController::detail/$1');
    $routes->post('operational-planning/executions/start/(:num)', 'OperationalFieldExecutionController::startWork/$1');
    $routes->post('operational-planning/executions/progress/(:num)', 'OperationalFieldExecutionController::logProgress/$1');
    $routes->post('operational-planning/executions/materials/(:num)', 'OperationalFieldExecutionController::reconcileMaterials/$1');
    $routes->post('operational-planning/executions/hold/(:num)', 'OperationalFieldExecutionController::declareHold/$1');
    $routes->post('operational-planning/executions/resume/(:num)', 'OperationalFieldExecutionController::resumeHold/$1');
    $routes->post('operational-planning/executions/complete/(:num)', 'OperationalFieldExecutionController::declareCompletion/$1');
    $routes->post('operational-planning/executions/abort/(:num)', 'OperationalFieldExecutionController::abort/$1');

    // Wave 2 Work Acceptance, Quality Assurance & Closure Governance (Phase OP-07)
    $routes->get('operational-planning/acceptances', 'OperationalWorkAcceptanceController::index');
    $routes->get('operational-planning/acceptances/initiate/(:num)', 'OperationalWorkAcceptanceController::initiate/$1');
    $routes->get('operational-planning/acceptances/detail/(:num)', 'OperationalWorkAcceptanceController::detail/$1');
    $routes->post('operational-planning/acceptances/evaluate/(:num)', 'OperationalWorkAcceptanceController::evaluate/$1');
    $routes->post('operational-planning/acceptances/transition/(:num)', 'OperationalWorkAcceptanceController::transition/$1');

    $routes->get('command-center', 'CommandCenterController::index');
    $routes->get('command-center/api-data', 'CommandCenterController::apiData');
    $routes->get('command-center/api/summary', 'CommandCenterController::apiSummary');
    $routes->get('command-center/api/risk-radar', 'CommandCenterController::apiRiskRadar');
    $routes->get('command-center/api/priority-actions', 'CommandCenterController::apiPriorityActions');
    $routes->get('command-center/api/recurring-intelligence', 'CommandCenterController::apiRecurringIntelligence');
    $routes->get('command-center/api/explainability/(:num)', 'CommandCenterController::apiExplainability/$1');
    $routes->post('command-center/api/gangguan-import/dry-run', 'CommandCenterController::apiGangguanDryRun');
    $routes->post('command-center/api/gangguan-import/plan', 'CommandCenterController::apiGangguanPlan');
    $routes->post('command-center/api/gangguan-import/commit', 'CommandCenterController::apiGangguanCommit');
    $routes->get('command-center/api/gangguan-import/status/(:segment)', 'CommandCenterController::apiGangguanStatus/$1');
    $routes->get('command-center/geo-data', 'CommandCenterController::geoData');
    $routes->get('command-center/asset-impact/(:num)', 'CommandCenterController::assetImpact/$1');
    $routes->get('command-center/feeder-nri/(:segment)', 'CommandCenterController::feederNri/$1');
    $routes->get('command-center/asset-forecast/(:num)', 'CommandCenterController::assetForecast/$1');
    $routes->get('command-center/feeder-forecast/(:segment)', 'CommandCenterController::feederForecast/$1');
    $routes->get('command-center/asset-prescriptive/(:num)', 'CommandCenterController::assetPrescriptive/$1');
    $routes->get('command-center/asset-work-package/(:num)', 'CommandCenterController::assetWorkPackage/$1');
    $routes->get('command-center/execution-feedback/(:num)', 'CommandCenterController::executionFeedback/$1');
    $routes->get('command-center/production-hardening-status', 'CommandCenterController::productionHardeningStatus');
    $routes->get('command-center/operational-resilience-status', 'CommandCenterController::operationalResilienceStatus');
    $routes->get('command-center/system-observability-status', 'CommandCenterController::systemObservabilityStatus');
    $routes->get('control-plane/situation-model', 'OperationalControlPlaneController::situationModel');
    $routes->get('control-plane/decision-inbox', 'OperationalControlPlaneController::decisionInbox');
    $routes->post('control-plane/record-decision', 'OperationalControlPlaneController::recordDecision');
    $routes->get('workflow/event-fabric-status', 'OperationalWorkflowController::eventFabricStatus');
    $routes->post('workflow/publish-event', 'OperationalWorkflowController::publishEvent');
    $routes->post('workflow/execute-event-driven', 'OperationalWorkflowController::executeEventDriven');
    $routes->post('workflow/dispatch-notification', 'OperationalWorkflowController::dispatchNotification');
    $routes->get('authority/matrix-status', 'OperationalAuthorityController::matrixStatus');
    $routes->get('authority/delegation-rule/(:segment)', 'OperationalAuthorityController::delegationRule/$1');
    $routes->get('authority/shift-roster', 'OperationalAuthorityController::shiftRoster');
    $routes->get('authority/escalate-alert/(:num)', 'OperationalAuthorityController::escalateAlert/$1');
    $routes->get('knowledge/similar-cases/(:num)', 'OperationalKnowledgeController::similarCases/$1');
    $routes->get('knowledge/policy-status', 'OperationalKnowledgeController::policyStatus');
    $routes->get('knowledge/decision-outcomes', 'OperationalKnowledgeController::decisionOutcomes');
    $routes->get('simulation/digital-twin/(:num)', 'OperationalSimulationController::digitalTwin/$1');
    $routes->post('simulation/run-what-if', 'OperationalSimulationController::runWhatIf');
    $routes->get('simulation/compare-scenarios/(:num)', 'OperationalSimulationController::compareScenarios/$1');
    $routes->get('data-trust/quality-score/(:num)', 'DataTrustController::qualityScore/$1');
    $routes->get('data-trust/anomaly-audit/(:num)', 'DataTrustController::anomalyAudit/$1');
    $routes->get('data-trust/confidence-tree/(:num)', 'DataTrustController::confidenceTree/$1');
    $routes->get('enterprise-command-center', 'EnterpriseCommandCenterController::index');
    $routes->get('enterprise-command-center/api-feed', 'EnterpriseCommandCenterController::apiFeed');
    $routes->get('workspace/asset/(:num)', 'OperationalWorkspaceController::index/$1');
    $routes->get('workspace/asset/(:num)/explain', 'OperationalWorkspaceController::explain/$1');
    $routes->get('workspace/asset/(:num)/timeline', 'OperationalWorkspaceController::timeline/$1');
    $routes->post('workspace/asset/(:num)/action', 'OperationalWorkspaceController::recordAction/$1');
    $routes->get('experience/role-workspace/(:segment)', 'OperationalExperienceController::roleWorkspace/$1');
    $routes->get('experience/mobile-field/(:num)', 'OperationalExperienceController::mobileField/$1');
    $routes->post('experience/handoff', 'OperationalExperienceController::handoff');
    $routes->get('integration/cross-system-status', 'EnterpriseIntegrationController::index');
    $routes->get('integration/telemetry-sync/(:num)', 'EnterpriseIntegrationController::telemetrySync/$1');
    $routes->post('integration/field-event', 'EnterpriseIntegrationController::ingestFieldEvent');
    $routes->get('security/zero-trust-status', 'EnterpriseSecurityController::index');
    $routes->post('security/evaluate-access', 'EnterpriseSecurityController::evaluateAccess');
    $routes->post('security/step-up', 'EnterpriseSecurityController::stepUp');
    $routes->get('security/hardening-status', 'EnterpriseSecurityHardeningController::index');
    $routes->post('security/secret-audit', 'EnterpriseSecurityHardeningController::secretAudit');
    $routes->post('security/revoke-session', 'EnterpriseSecurityHardeningController::revokeSession');
    $routes->get('continuity/dr-status', 'EnterpriseContinuityController::index');
    $routes->post('continuity/create-recovery-point', 'EnterpriseContinuityController::createRecoveryPoint');
    $routes->post('continuity/verify-integrity', 'EnterpriseContinuityController::verifyIntegrity');
    $routes->get('compliance/retention-status', 'DataLifecycleController::index');
    $routes->post('compliance/archive/run', 'DataLifecycleController::runArchive');
    $routes->post('compliance/evidence/generate', 'DataLifecycleController::generateEvidence');
    $routes->get('deployment/release-status', 'EnterpriseDeploymentController::index');
    $routes->post('deployment/manifest/create', 'EnterpriseDeploymentController::createManifest');
    $routes->post('deployment/readiness-check', 'EnterpriseDeploymentController::readinessCheck');
    $routes->post('deployment/execute', 'EnterpriseDeploymentController::executeDeployment');
    $routes->get('operations/live-status', 'EnterpriseOperationsController::index');
    $routes->post('operations/verify-release', 'EnterpriseOperationsController::verifyRelease');
    $routes->post('operations/canary-check', 'EnterpriseOperationsController::canaryCheck');
    $routes->get('change/governance-status', 'EnterpriseChangeController::index');
    $routes->post('change/request/create', 'EnterpriseChangeController::createRequest');
    $routes->post('change/impact/assess', 'EnterpriseChangeController::assessImpact');
    $routes->post('change/approve', 'EnterpriseChangeController::approveRequest');
    $routes->get('capacity/performance-status', 'EnterpriseCapacityController::index');
    $routes->post('capacity/guardrail-check', 'EnterpriseCapacityController::guardrailCheck');
    $routes->post('capacity/stress-audit', 'EnterpriseCapacityController::stressAudit');
    $routes->get('acceptance/status', 'EnterpriseAcceptanceController::index');
    $routes->post('acceptance/sign-off', 'EnterpriseAcceptanceController::signOff');
    $routes->post('acceptance/certificate/generate', 'EnterpriseAcceptanceController::generateCertificate');
    $routes->get('audit/regulatory-report', 'EnterpriseAuditReportController::index');
    $routes->post('audit/report/generate', 'EnterpriseAuditReportController::generateReport');
    $routes->post('audit/bundle/export', 'EnterpriseAuditReportController::exportBundle');
    $routes->get('notification/dispatch', 'EnterpriseNotificationController::index');
    $routes->post('notification/send', 'EnterpriseNotificationController::sendNotification');
    $routes->get('notification/adapters', 'EnterpriseNotificationController::adapterStatus');
    $routes->get('analytics/executive-bi', 'EnterpriseAnalyticsController::index');
    $routes->get('analytics/snapshot', 'EnterpriseAnalyticsController::snapshot');
    $routes->get('analytics/drill-down', 'EnterpriseAnalyticsController::drillDown');
    $routes->get('lifecycle/capex-decision', 'EnterpriseLifecycleController::index');
    $routes->get('lifecycle/decision-snapshot', 'EnterpriseLifecycleController::decisionSnapshot');
    $routes->get('lifecycle/capex-matrix', 'EnterpriseLifecycleController::capexMatrix');
    $routes->get('mobility/offline-sync', 'EnterpriseMobilityController::index');
    $routes->post('mobility/sync-envelope', 'EnterpriseMobilityController::syncEnvelope');
    $routes->get('mobility/telemetry-status', 'EnterpriseMobilityController::telemetryStatus');
    $routes->get('integration/federated-gateway', 'EnterpriseIntegrationGatewayController::index');
    $routes->post('integration/inbound-request', 'EnterpriseIntegrationGatewayController::inboundRequest');
    $routes->get('integration/adapter-health', 'EnterpriseIntegrationGatewayController::adapterHealth');
    $routes->get('governance/data-stewardship', 'EnterpriseGovernanceController::index');
    $routes->get('governance/stewardship-snapshot', 'EnterpriseGovernanceController::stewardshipSnapshot');
    $routes->get('governance/reconciliation-result', 'EnterpriseGovernanceController::reconciliationResult');
    $routes->get('incident/command-center', 'EnterpriseIncidentCommandController::index');
    $routes->post('incident/declare', 'EnterpriseIncidentCommandController::declareIncident');
    $routes->get('incident/situation-board', 'EnterpriseIncidentCommandController::situationBoard');
    $routes->get('self-healing/control-center', 'EnterpriseSelfHealingController::index');
    $routes->get('self-healing/anomaly-snapshot', 'EnterpriseSelfHealingController::anomalySnapshot');
    $routes->post('self-healing/propose-recovery', 'EnterpriseSelfHealingController::proposeRecovery');
    $routes->get('financial-audit/control-center', 'EnterpriseFinancialAuditController::index');
    $routes->get('financial-audit/financial-snapshot', 'EnterpriseFinancialAuditController::financialSnapshot');
    $routes->get('financial-audit/recovery-proposal', 'EnterpriseFinancialAuditController::recoveryProposal');
    $routes->get('compliance-intelligence/control-center', 'EnterpriseComplianceIntelligenceController::index');
    $routes->get('compliance-intelligence/obligation-snapshot', 'EnterpriseComplianceIntelligenceController::obligationSnapshot');
    $routes->get('compliance-intelligence/readiness-bundle', 'EnterpriseComplianceIntelligenceController::readinessBundle');
    $routes->get('vendor-governance/control-center', 'EnterpriseVendorGovernanceController::index');
    $routes->get('vendor-governance/contractor-snapshot', 'EnterpriseVendorGovernanceController::contractorSnapshot');
    $routes->get('vendor-governance/vendor-rating-advisory', 'EnterpriseVendorGovernanceController::vendorRatingAdvisory');
    $routes->get('esg-analytics/control-center', 'EnterpriseEsgAnalyticsController::index');
    $routes->get('esg-analytics/esg-snapshot', 'EnterpriseEsgAnalyticsController::esgSnapshot');
    $routes->get('esg-analytics/decarbonization-advisory', 'EnterpriseEsgAnalyticsController::decarbonizationAdvisory');
    $routes->get('disaster-immunity/control-center', 'EnterpriseDisasterImmunityController::index');
    $routes->get('disaster-immunity/topology-snapshot', 'EnterpriseDisasterImmunityController::topologySnapshot');
    $routes->get('disaster-immunity/immunity-advisory', 'EnterpriseDisasterImmunityController::immunityAdvisory');
    $routes->get('federated-benchmarking/control-center', 'EnterpriseFederatedBenchmarkingController::index');
    $routes->get('federated-benchmarking/benchmarking-snapshot', 'EnterpriseFederatedBenchmarkingController::benchmarkingSnapshot');
    $routes->get('federated-benchmarking/knowledge-advisory', 'EnterpriseFederatedBenchmarkingController::knowledgeAdvisory');
    $routes->get('ev-grid-resilience/control-center', 'EnterpriseEvGridResilienceController::index');
    $routes->get('ev-grid-resilience/ev-impact-snapshot', 'EnterpriseEvGridResilienceController::evImpactSnapshot');
    $routes->get('ev-grid-resilience/flexibility-advisory', 'EnterpriseEvGridResilienceController::flexibilityAdvisory');
    $routes->get('forensic-audit/control-center', 'EnterpriseForensicAuditController::index');
    $routes->get('forensic-audit/provenance-snapshot', 'EnterpriseForensicAuditController::provenanceSnapshot');
    $routes->get('forensic-audit/forensic-bundle', 'EnterpriseForensicAuditController::forensicBundle');
    $routes->get('grid-stress-simulation/control-center', 'EnterpriseGridStressSimulationController::index');
    $routes->get('grid-stress-simulation/simulation-snapshot', 'EnterpriseGridStressSimulationController::simulationSnapshot');
    $routes->get('grid-stress-simulation/mitigation-advisory', 'EnterpriseGridStressSimulationController::mitigationAdvisory');
    $routes->get('risk-capital/control-center', 'EnterpriseRiskCapitalController::index');
    $routes->get('risk-capital/capital-snapshot', 'EnterpriseRiskCapitalController::capitalSnapshot');
    $routes->get('risk-capital/investment-advisory', 'EnterpriseRiskCapitalController::investmentAdvisory');
    $routes->get('cyber-security/control-center', 'EnterpriseCyberSecurityController::index');
    $routes->get('cyber-security/telemetry-snapshot', 'EnterpriseCyberSecurityController::telemetrySnapshot');
    $routes->get('cyber-security/security-advisory', 'EnterpriseCyberSecurityController::securityAdvisory');
    $routes->get('revenue-assurance/control-center', 'EnterpriseRevenueAssuranceController::index');
    $routes->get('revenue-assurance/revenue-snapshot', 'EnterpriseRevenueAssuranceController::revenueSnapshot');
    $routes->get('revenue-assurance/protection-advisory', 'EnterpriseRevenueAssuranceController::protectionAdvisory');
    $routes->get('reliability-assurance/control-center', 'EnterpriseReliabilityAssuranceController::index');
    $routes->get('reliability-assurance/reliability-snapshot', 'EnterpriseReliabilityAssuranceController::reliabilitySnapshot');
    $routes->get('reliability-assurance/improvement-advisory', 'EnterpriseReliabilityAssuranceController::improvementAdvisory');
    $routes->get('critical-infrastructure/control-center', 'EnterpriseCriticalInfrastructureController::index');
    $routes->get('critical-infrastructure/resilience-snapshot', 'EnterpriseCriticalInfrastructureController::resilienceSnapshot');
    $routes->get('critical-infrastructure/critical-advisory', 'EnterpriseCriticalInfrastructureController::criticalAdvisory');
    $routes->get('work-completion/control-center', 'EnterpriseWorkCompletionController::index');
    $routes->get('work-completion/completion-snapshot', 'EnterpriseWorkCompletionController::completionSnapshot');
    $routes->get('work-completion/quality-advisory', 'EnterpriseWorkCompletionController::qualityAdvisory');
    $routes->get('inspection-planning/control-center', 'EnterpriseInspectionPlanningController::index');
    $routes->get('inspection-planning/schedule-snapshot', 'EnterpriseInspectionPlanningController::scheduleSnapshot');
    $routes->get('inspection-planning/priority-advisory', 'EnterpriseInspectionPlanningController::priorityAdvisory');
    $routes->get('performance-scorecard/control-center', 'EnterprisePerformanceScorecardController::index');
    $routes->get('performance-scorecard/scorecard-snapshot', 'EnterprisePerformanceScorecardController::scorecardSnapshot');
    $routes->get('performance-scorecard/improvement-advisory', 'EnterprisePerformanceScorecardController::improvementAdvisory');
    $routes->get('operational-command-center', 'CommandCenterController::index');
    $routes->get('penyulang/health-index', 'AssetHealthController::index');
    $routes->get('executive-dashboard', 'ExecutiveDashboardController::index');
    $routes->get('dashboard/executive', 'ExecutiveDashboardController::index');
    $routes->get('dashboard/executive-api', 'Dashboard::executiveApi');
    $routes->get('dashboard/chart-data', 'ExecutiveDashboardController::getChartData');
    $routes->get('dashboard/dashboard-summary', 'ExecutiveDashboardController::getSummary');
    $routes->get('dashboard/dashboard-kpi', 'ExecutiveDashboardController::getKpiData');
    $routes->get('dashboard/toggle-view', 'Dashboard::toggleView');
    $routes->get('dashboard/analytics-data', 'Dashboard::analyticsData');
    $routes->get('auth/ping', 'Auth::ping');

    // Self Service Change Password & Announcement Ticker
    $routes->get('change-password', 'Auth::changePassword');
    $routes->post('change-password', 'Auth::changePassword');
    $routes->get('setting/announcement', 'Setting::index');
    $routes->post('setting/announcement', 'Setting::updateAnnouncement');
    $routes->match(['GET', 'POST'], 'setting/update-announcement', 'Setting::updateAnnouncement');

    // Phase 17 - Master Asset Management (master-assets route to avoid Apache 403 directory collision with public/assets/)
    $routes->get('master-assets', 'AssetController::index');
    $routes->get('assets', 'AssetController::index');
    $routes->get('master-assets/create', 'AssetController::create');
    $routes->get('assets/create', 'AssetController::create');
    $routes->post('master-assets/store', 'AssetController::store');
    $routes->post('assets/store', 'AssetController::store');
    $routes->get('master-assets/detail/(:num)', 'AssetController::detail/$1');
    $routes->get('assets/detail/(:num)', 'AssetController::detail/$1');
    $routes->get('assets/edit/(:num)', 'AssetController::edit/$1');
    $routes->post('assets/update/(:num)', 'AssetController::update/$1');
    $routes->post('assets/verify-pass/(:num)', 'AssetController::verifyPass/$1');
    $routes->post('assets/verify-fail/(:num)', 'AssetController::verifyFail/$1');
    $routes->get('assets/history/(:num)', 'AssetController::history/$1');
    $routes->post('assets/soft-delete/(:num)', 'AssetController::softDelete/$1');
    $routes->post('assets/restore/(:num)', 'AssetController::restore/$1');
    $routes->post('master-assets/bulk-delete', 'AssetController::bulkDelete');
    $routes->get('master-assets/import-batches', 'AssetController::importBatches');
    $routes->post('master-assets/rollback-batch/(:num)', 'AssetController::rollbackBatch/$1');
    $routes->get('master-assets/debug-sql', 'AssetController::debugSql');

    // Release v2.3.0.25 - Master Gardu Induk (GI) Management Routes
    $routes->get('master-gi', 'GarduIndukController::index');
    $routes->post('master-gi/store', 'GarduIndukController::store');
    $routes->post('master-gi/update/(:num)', 'GarduIndukController::update/$1');
    $routes->post('master-gi/delete/(:num)', 'GarduIndukController::delete/$1');

    // Release v2.3.0.30 - Inspection Planning & Assignment Layer Routes
    $routes->get('planning', 'InspectionPlanningController::index');
    $routes->get('planning/create', 'InspectionPlanningController::create');
    $routes->get('planning/ajax-assets', 'InspectionPlanningController::ajaxGetAssets');
    $routes->post('planning/store', 'InspectionPlanningController::store');
    $routes->post('planning/publish/(:num)', 'InspectionPlanningController::publish/$1');
    $routes->get('planning/detail/(:num)', 'InspectionPlanningController::detail/$1');
    $routes->get('my-inspections', 'InspectionPlanningController::myInspections');

    // Release v2.3.0.32 - Inspection Progress Monitoring & Inspector History Routes
    $routes->get('inspection-progress', 'InspectionProgressController::index');
    $routes->get('inspection-progress/detail/(:num)', 'InspectionProgressController::detail/$1');
    $routes->get('my-progress', 'InspectionProgressController::myProgress');
    $routes->get('my-history', 'InspectionProgressController::myHistory');

    // Release v2.3.0.29 - Network Cascading Selection APIs
    $routes->get('api/network/penyulang', 'Api::getNetworkPenyulangs');
    $routes->get('api/network/ulps', 'Api::getNetworkUlps');
    $routes->get('api/debug-assets', 'Api::debugAssets');

    // Release v2.1.0 - GIS GeoJSON & Network Topology APIs
    $routes->get('gis/api-penyulangs', 'GisController::apiPenyulangs');
    $routes->get('gis/api-network', 'GisController::apiNetwork');
    $routes->get('gis/api-translines', 'GisController::apiGetTranslines');
    $routes->get('gis/api-network-audit', 'GisController::apiNetworkAudit');
    $routes->get('gis/api-conductors', 'GisController::apiConductors');
    $routes->get('gis/api-generate-candidates', 'GisController::apiGenerateCandidates');
    $routes->post('gis/api-connect-topology', 'GisController::apiConnectTopology');
    $routes->post('gis/api-disconnect-topology', 'GisController::apiDisconnectTopology');
    $routes->post('gis/api-update-segment', 'GisController::apiUpdateSegmentGeometry');
    $routes->post('gis/api-update-conductor', 'GisController::apiUpdateConductorSpecification');
    $routes->get('gis/api-data', 'GisController::apiData');
    $routes->get('master-assets/geojson', 'GisController::geoJson');
    $routes->get('master-assets/feeder-network', 'GisController::feederNetwork');
    $routes->get('master-assets/feeder-assets', 'GisController::feederAssets');
    $routes->get('assets/network/(:num)', 'GisController::network/$1');
    $routes->get('assets/baseline/(:num)', 'GisController::baseline/$1');

    // Wave 3 Phase PH-AI-GIS-01 - Field Asset Lifecycle & Transline Editor APIs
    $routes->post('gis/api-propose-correction', 'GisController::apiProposeCorrection');
    $routes->post('gis/api-propose-new-asset', 'GisController::apiProposeNewAsset');
    $routes->post('gis/api-report-missing', 'GisController::apiReportMissingAsset');
    $routes->post('gis/api-propose-transline', 'GisController::apiProposeTransline');
    $routes->post('gis/api-apply-correction', 'GisController::apiApplyCorrection');
    $routes->post('gis/api-reject-correction', 'GisController::apiRejectCorrection');
    $routes->get('gis/api-next-code', 'GisController::apiNextCode');
    $routes->get('gis/api-pending-corrections', 'GisController::apiPendingCorrections');
    $routes->get('gis/api-asset-history/(:num)', 'GisController::apiAssetHistory/$1');

    // Release v2.2.0 - Guided Inspection Execution Engine Routes
    $routes->get('inspections', 'InspectionController::index');
    $routes->get('inspections/start', 'InspectionController::start');
    $routes->get('inspections/start-by-asset', 'InspectionController::startByAsset');
    $routes->post('inspections/start', 'InspectionController::storeStart');
    $routes->get('inspections/guided/(:num)', 'InspectionController::guided/$1');
    $routes->post('inspections/submit-point/(:num)', 'InspectionController::submitPoint/$1');

    // Release v2.3.0 - Intelligence & Analytics Layer Routes (100% Read-Only)
    $routes->get('executive-analytics', 'ExecutiveAnalyticsController::index');

    // Master Asset PLN Import & Export Routes
    $routes->get('master-assets/debug-vendor', 'AssetImportController::debugVendor');
    $routes->get('master-assets/debug-runtime', 'AssetImportController::debugRuntime');
    $routes->get('master-assets/template', 'AssetImportController::downloadTemplate');
    $routes->get('master-assets/penyulang-by-ulp/(:num)', 'AssetImportController::getPenyulangByUlp/$1');
    $routes->get('penyulang-by-ulp/(:num)', 'AssetImportController::getPenyulangByUlp/$1');
    $routes->get('master-assets/import', 'AssetImportController::importView');
    $routes->post('master-assets/import-process', 'AssetImportController::processImport');
    $routes->get('master-assets/download-error-report', 'AssetImportController::downloadErrorReport');
    $routes->get('master-assets/export-excel', 'AssetImportController::exportExcel');
    $routes->get('master-assets/export-csv', 'AssetImportController::exportCsv');
    $routes->get('master-assets/export-pdf', 'AssetImportController::exportPdf');

    // Phase 17 - Work Order Enterprise
    $routes->get('work-orders', 'WorkOrderController::index');
    $routes->get('work-orders/create', 'WorkOrderController::create');
    $routes->post('work-orders/store', 'WorkOrderController::store');
    $routes->get('work-orders/detail/(:num)', 'WorkOrderController::detail/$1');
    $routes->post('work-orders/update-status/(:num)', 'WorkOrderController::updateStatus/$1');
    $routes->post('work-orders/toggle-checklist/(:num)', 'WorkOrderController::toggleChecklist/$1');
    $routes->post('work-orders/add-material/(:num)', 'WorkOrderController::addMaterial/$1');

    // Phase 18 - Smart GIS & Network Mapping Enterprise
    $routes->get('gis', 'GisController::index');
    $routes->get('peta-jaringan', 'GisController::index');
    $routes->get('gis/api-data', 'GisController::apiData');
    $routes->post('gis/checkin', 'GisController::checkin');

    // Phase 19 - AI Predictive Maintenance & Decision Support
    $routes->get('ai-predictive', 'AiPredictiveController::index');
    $routes->get('ai-predictive/api-data', 'AiPredictiveController::apiData');
    $routes->get('ai-predictive/export-dataset', 'AiPredictiveController::exportDataset');

    // Phase 21 - Smart Notification Center & Automation
    $routes->get('notifications', 'Notification::index');
    $routes->get('notifications/read-all', 'Notification::markAllAsRead');
    $routes->get('notifications/templates', 'Notification::templates');
    $routes->get('notifications/rules', 'Notification::rules');
    $routes->get('notifications/preferences', 'Notification::preferences');
    $routes->get('notifications/api-unread', 'Notification::apiUnread');
    $routes->get('notifications/api-unread-list', 'Notification::apiUnreadList');
    $routes->get('notifications/trigger-escalation', 'Notification::triggerEscalation');

    // Phase 22 - Executive Command Center (ECC)
    $routes->get('ecc', 'ExecutiveCommandCenter::index');
    $routes->get('ecc/tv-mode', 'ExecutiveCommandCenter::tvMode');
    $routes->get('ecc/api-data', 'ExecutiveCommandCenter::apiData');
    $routes->get('ecc/sse-stream', 'ExecutiveCommandCenter::sseStream');

    // Phase 23 - Digital Document Intelligence
    $routes->get('documents', 'DocumentCenter::index');
    $routes->get('documents/create', 'DocumentCenter::create');
    $routes->post('documents/store', 'DocumentCenter::store');
    $routes->get('documents/detail/(:num)', 'DocumentCenter::detail/$1');
    $routes->post('documents/approve/(:num)', 'DocumentCenter::approve/$1');

    // Modul Backup & Restore Database Hostinger (Admin Saja - Terproteksi Role)
    $routes->group('backup-database', ['filter' => 'role:administrator,admin_pusat'], function ($routes) {
        $routes->get('/', 'DatabaseBackup::index');
        $routes->post('create', 'DatabaseBackup::create');
        $routes->get('download/(:segment)', 'DatabaseBackup::download/$1');
        $routes->get('delete/(:segment)', 'DatabaseBackup::delete/$1');
        $routes->get('clean-old', 'DatabaseBackup::cleanOldBackups');
        $routes->post('restore', 'DatabaseBackup::restore');
    });

    // Import CSV (Admin saja)
    $routes->group('import', ['filter' => 'role:administrator,admin_ulp'], function ($routes) {
        $routes->get('/', 'Import::index');
        $routes->get('template/(:segment)', 'Import::template/$1');
        $routes->get('template-section', 'Import::templateSectionDynamic');
        $routes->get('template-penyulang', 'Import::templatePenyulangDynamic');
        $routes->get('ajax-penyulang', 'Import::ajaxGetPenyulang');
        $routes->get('export-penyulang', 'Import::exportPenyulang');
        $routes->get('export-section', 'Import::exportSection');
        $routes->post('process', 'Import::process');
    });

    // Temuan & AJAX Cascades
    $routes->get('temuan', 'Temuan::index');
    $routes->get('temuan/terdekat', 'Temuan::terdekat');
    $routes->get('temuan/ajax-terdekat', 'Temuan::ajaxTerdekat');
    $routes->get('temuan/ajax-detail/(:num)', 'Temuan::ajaxDetail/$1');
    $routes->get('temuan/create', 'Temuan::create', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->post('temuan/store', 'Temuan::store', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->get('temuan/lookup', 'Temuan::lookup');
    $routes->get('temuan/detail/(:num)', 'Temuan::detail/$1');
    $routes->post('temuan/tindak-lanjut/(:num)', 'Temuan::tindakLanjut/$1', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->match(['GET', 'POST'], 'temuan/delete/(:num)', 'Temuan::delete/$1', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->get('temuan/edit/(:num)', 'Temuan::edit/$1', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->post('temuan/update/(:num)', 'Temuan::update/$1', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->get('temuan/update-pekerjaan', 'Temuan::updatePekerjaan');
    $routes->post('temuan/ajax-update-pekerjaan', 'Temuan::ajaxUpdatePekerjaan');
    
    // AJAX data loading
    $routes->get('temuan/ajax-penyulang/(:num)', 'Temuan::ajaxGetPenyulang/$1');
    $routes->get('temuan/ajax-penyulang', 'Temuan::ajaxGetPenyulang');
    $routes->get('temuan/ajax-get-penyulang/(:num)', 'Temuan::ajaxGetPenyulang/$1');
    $routes->get('temuan/ajax-get-penyulang', 'Temuan::ajaxGetPenyulang');

    $routes->get('temuan/ajax-section/(:num)', 'Temuan::ajaxGetSection/$1');
    $routes->get('temuan/ajax-section', 'Temuan::ajaxGetSection');
    $routes->get('temuan/ajax-get-section/(:num)', 'Temuan::ajaxGetSection/$1');
    $routes->get('temuan/ajax-get-section', 'Temuan::ajaxGetSection');
    $routes->get('temuan/ajax-material-picker', 'Temuan::ajaxMaterialPicker');
    $routes->post('temuan/ajax-material-transaction', 'Temuan::ajaxMaterialTransaction');
    $routes->get('temuan/material-recap', 'Temuan::materialRecap');
    $routes->get('temuan/ajax-material-recap', 'Temuan::ajaxMaterialRecap');

    // MNF-01: Shared Master Network Fabric Lookup API (Canonical 4-Level Master Fabric)
    $routes->group('ajax/network', static function ($routes) {
        $routes->get('ulp', 'Ajax\NetworkLookup::ulp');
        $routes->get('penyulang/(:num)', 'Ajax\NetworkLookup::penyulang/$1');
        $routes->get('penyulang', 'Ajax\NetworkLookup::penyulang');
        $routes->get('section/(:num)', 'Ajax\NetworkLookup::section/$1');
        $routes->get('section', 'Ajax\NetworkLookup::section');
        $routes->get('asset/(:num)', 'Ajax\NetworkLookup::asset/$1');
        $routes->get('asset', 'Ajax\NetworkLookup::asset');
        $routes->get('assets', 'Ajax\NetworkLookup::assets');
        // MAP-02: Read-Only Asset Context Drawer API
        $routes->get('asset-context/(:num)', 'Ajax\NetworkLookup::assetContext/$1');
    });

    // MAP-02: Canonical API Alias for Read-Only Asset Context
    $routes->get('api/asset-context/(:num)', 'Ajax\NetworkLookup::assetContext/$1');

    $routes->group('api/master-network', static function ($routes) {
        $routes->get('ulps', 'Ajax\NetworkLookup::ulp');
        $routes->get('penyulangs', 'Ajax\NetworkLookup::penyulang');
        $routes->get('penyulangs/(:num)', 'Ajax\NetworkLookup::penyulang/$1');
        $routes->get('sections', 'Ajax\NetworkLookup::section');
        $routes->get('sections/(:num)', 'Ajax\NetworkLookup::section/$1');
        $routes->get('assets', 'Ajax\NetworkLookup::assets');
        $routes->get('assets/(:num)', 'Ajax\NetworkLookup::asset/$1');
    });

    $routes->post('temuan/ajax-datatables', 'Temuan::ajaxDataTables');

    // Master Data ULP (Admin & Admin ULP saja)
    $routes->group('ulps', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'Ulp::index');
        $routes->get('create', 'Ulp::create');
        $routes->post('store', 'Ulp::store');
        $routes->get('edit/(:num)', 'Ulp::edit/$1');
        $routes->post('update/(:num)', 'Ulp::update/$1');
        $routes->match(['GET', 'POST'], 'delete/(:num)', 'Ulp::delete/$1');
    });

    // Master Data Penyulang (Admin & Admin ULP)
    $routes->group('penyulang', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'Penyulang::index');
        $routes->get('create', 'Penyulang::create');
        $routes->post('store', 'Penyulang::store');
        $routes->get('edit/(:num)', 'Penyulang::edit/$1');
        $routes->post('update/(:num)', 'Penyulang::update/$1');
        $routes->match(['GET', 'POST'], 'delete/(:num)', 'Penyulang::delete/$1');
    });

    // Master Data Section (Admin & Admin ULP)
    $routes->group('sections', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'Section::index');
        $routes->get('create', 'Section::create');
        $routes->post('store', 'Section::store');
        $routes->get('edit/(:num)', 'Section::edit/$1');
        $routes->post('update/(:num)', 'Section::update/$1');
        $routes->match(['GET', 'POST'], 'delete/(:num)', 'Section::delete/$1');
    });

    // Master Data User (Admin & Admin ULP)
    $routes->group('users', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'User::index');
        $routes->get('create', 'User::create');
        $routes->post('store', 'User::store');
        $routes->get('edit/(:num)', 'User::edit/$1');
        $routes->post('update/(:num)', 'User::update/$1');
        $routes->match(['GET', 'POST'], 'delete/(:num)', 'User::delete/$1');
        $routes->post('reset-password/(:num)', 'User::resetPassword/$1');
    });

    // Pusat Laporan
    $routes->group('laporan', function ($routes) {
        $routes->get('/', 'Laporan::index');
        $routes->get('temuan', 'Laporan::temuan');
        $routes->post('preview', 'Laporan::preview');
        $routes->post('print', 'Laporan::print');
        $routes->post('excel', 'Laporan::excel');
        $routes->post('csv', 'Laporan::csv');
        $routes->post('pptx', 'Laporan::pptx');

        // Laporan Eviden
        $routes->get('eviden', 'Laporan::eviden');
        $routes->post('ajax-eviden-data', 'Laporan::ajaxEvidenData');
        $routes->post('export-eviden-pdf', 'Laporan::exportEvidenPdf');
        $routes->post('export-eviden-excel', 'Laporan::exportEvidenExcel');
        $routes->post('export-eviden-csv', 'Laporan::exportEvidenCsv');
        $routes->post('export-eviden-ppt', 'Laporan::exportEvidenPpt');

        // Laporan Management Trafo
        $routes->get('management', 'Laporan::management');
        $routes->post('ajax-management-data', 'Laporan::ajaxManagementData');
        $routes->post('export-management-pdf', 'Laporan::exportManagementPdf');
        $routes->post('export-management-excel', 'Laporan::exportManagementExcel');
        $routes->post('export-management-csv', 'Laporan::exportManagementCsv');
    });

    // Identifikasi Gangguan Penyulang
    $routes->get('identifikasi', 'Identifikasi::index');
    $routes->post('identifikasi/analisis', 'Identifikasi::analisis');
    $routes->post('identifikasi/export-pdf', 'Identifikasi::exportPdf');
    $routes->post('identifikasi/export-excel', 'Identifikasi::exportExcel');
    $routes->post('identifikasi/export-csv', 'Identifikasi::exportCsv');
    $routes->post('identifikasi/export-ppt', 'Identifikasi::exportPpt');

    // Eviden Lapangan (Kubikel & Trafo) - HAR Gardu, PDKB, Admin ULP & Admin
    $routes->group('eviden', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,har_gardu,har_konstruksi,har_row,har_crane,pdkb,yantek,inspeksi,supervisor_ulp,supervisor_up3'], function ($routes) {
        // Kubikel
        $routes->get('kubikel', 'Eviden::kubikel');
        $routes->get('kubikel/create', 'Eviden::kubikelCreate');
        $routes->post('kubikel/store', 'Eviden::kubikelStore');
        $routes->get('kubikel/edit/(:num)', 'Eviden::kubikelEdit/$1');
        $routes->post('kubikel/update/(:num)', 'Eviden::kubikelUpdate/$1');
        $routes->match(['GET', 'POST'], 'kubikel/delete/(:num)', 'Eviden::kubikelDelete/$1');

        // Trafo
        $routes->get('trafo', 'Eviden::trafo');
        $routes->get('trafo/create', 'Eviden::trafoCreate');
        $routes->post('trafo/store', 'Eviden::trafoStore');
        $routes->get('trafo/edit/(:num)', 'Eviden::trafoEdit/$1');
        $routes->post('trafo/update/(:num)', 'Eviden::trafoUpdate/$1');
        $routes->match(['GET', 'POST'], 'trafo/delete/(:num)', 'Eviden::trafoDelete/$1');

        // Management Trafo
        $routes->get('management', 'Eviden::management');
        $routes->get('management/create', 'Eviden::managementCreate');
        $routes->post('management/store', 'Eviden::managementStore');
        $routes->get('management/edit/(:num)', 'Eviden::managementEdit/$1');
        $routes->post('management/update/(:num)', 'Eviden::managementUpdate/$1');
        $routes->match(['GET', 'POST'], 'management/delete/(:num)', 'Eviden::managementDelete/$1');

        // Delete Single Photo
        $routes->match(['GET', 'POST'], 'delete-foto/(:num)', 'Eviden::deleteFoto/$1');

        // Dynamic AJAX gallery & CSV export
        $routes->get('ajax-get-fotos', 'Eviden::ajaxGetFotos');
        $routes->get('export-kubikel', 'Eviden::exportKubikel');
        $routes->get('export-trafo', 'Eviden::exportTrafo');
        $routes->get('export-management', 'Eviden::exportManagement');
        $routes->post('download-pdf', 'Eviden::downloadPdf');
        $routes->post('download-foto', 'Eviden::downloadFoto');
    });
});

// --- Rute REST API v1 (Flutter Mobile App Backend with JWT) ---
$routes->group('api/v1', function ($routes) {
    
    // Auth & Sync (Unprotected / Direct API)
    $routes->post('auth/login', 'Api\AuthController::login');
    $routes->post('voice-ai/process', 'Api\VoiceAIApiController::process');
    $routes->get('voice-ai/summary', 'Api\VoiceAIApiController::summary');
    $routes->get('voice-ai/notifications', 'Api\VoiceAIApiController::notifications');
    $routes->get('voice-ai/logs', 'Api\VoiceAIApiController::logs');
    $routes->post('sync/bulk-records', 'Api\SyncApiController::bulkRecords');
    $routes->post('sync/upload-photo', 'Api\SyncApiController::uploadPhoto');

    // Protected via JWT Bearer Token Filter ('filter' => 'jwt')
    $routes->group('', ['filter' => 'jwt'], function ($routes) {
        
        // Auth Self Service
        $routes->get('auth/me', 'Api\AuthController::me');
        $routes->post('auth/change-password', 'Api\AuthController::changePassword');

        // Master Data Dropdowns
        $routes->get('master/ulps', 'Api\MasterApiController::ulps');
        $routes->get('master/penyulangs', 'Api\MasterApiController::penyulangs');
        $routes->get('master/sections', 'Api\MasterApiController::sections');

        // Temuan REST API CRUD
        $routes->get('temuan', 'Api\TemuanApiController::index');
        $routes->get('temuan/terdekat', 'Api\TemuanApiController::terdekat');
        $routes->get('temuan/(:num)', 'Api\TemuanApiController::show/$1');
        $routes->post('temuan', 'Api\TemuanApiController::create');
        $routes->post('temuan/update/(:num)', 'Api\TemuanApiController::update/$1');
        $routes->delete('temuan/delete/(:num)', 'Api\TemuanApiController::delete/$1');
        $routes->post('temuan/tindak-lanjut/(:num)', 'Api\TemuanApiController::tindakLanjut/$1');

        // Eviden REST API
        $routes->get('eviden/kubikel', 'Api\EvidenApiController::kubikelList');
        $routes->get('eviden/trafo', 'Api\EvidenApiController::trafoList');

        // Machine Learning & AI Integration REST API
        $routes->get('ai/dataset', 'Api\AiApiController::dataset');
        $routes->get('ai/summary', 'Api\AiApiController::summary');
        // AI Assistant REST API
        $routes->post('ai/query', 'Api\AiApiController::query');
    });
});

// --- Clean REST API Endpoints (/api/...) ---
$routes->group('api', function ($routes) {
    // 1. POST /api/login
    $routes->post('login', 'Api\AuthController::login');
    
    // 2. GET /api/sync
    $routes->get('sync', 'Api\SyncApiController::getSyncMeta');

    // 3. GET /api/user
    $routes->get('user', 'Api\AuthController::me');

    // 4. GET /api/temuan
    $routes->get('temuan', 'Api\TemuanApiController::index');

    // 5. POST /api/temuan
    $routes->post('temuan', 'Api\TemuanApiController::create');

    // 6. PUT /api/temuan/{id}
    $routes->put('temuan/(:num)', 'Api\TemuanApiController::update/$1');
    $routes->post('temuan/(:num)', 'Api\TemuanApiController::update/$1');

    // 7. DELETE /api/temuan/{id}
    $routes->delete('temuan/(:num)', 'Api\TemuanApiController::delete/$1');

    // 8. GET /api/history
    $routes->get('history', 'Api\TemuanApiController::history');

    // 9. GET /api/dashboard
    $routes->get('dashboard', 'Api\TemuanApiController::dashboard');

    // 10. GET /api/chart
    $routes->get('chart', 'Api\TemuanApiController::chart');

    // 11. GET /api/notifikasi
    $routes->get('notifikasi', 'Api\TemuanApiController::notifikasi');
});

// --- Rute REST API Legacy (Kompatibilitas Sistem PLN) ---
$routes->group('api', function ($routes) {
    $routes->post('auth/login', 'Api::login');
    $routes->post('auth/change-password', 'Api::changePassword');
    $routes->get('options', 'Api::getOptions');
    $routes->get('penyulangs/(:num)', 'Api::getPenyulangsByUlp/$1');
    $routes->get('penyulang-by-ulp/(:num)', 'Api::getPenyulangsByUlp/$1');
    $routes->get('sections/(:num)', 'Api::getSectionsByPenyulang/$1');
    $routes->get('section-by-penyulang/(:num)', 'Api::getSectionsByPenyulang/$1');
    $routes->get('temuan', 'Api::getTemuan');
    $routes->get('temuan/terdekat', 'Api::getTemuanTerdekat');
    $routes->get('temuan/(:num)', 'Api::detailTemuan/$1');
    $routes->post('temuan/create', 'Api::createTemuan');
    $routes->post('temuan/tindak-lanjut', 'Api::tindakLanjut');
});

// Phase 23: Public QR Code Document Verification (No Login Required)
$routes->get('documents/verify/(:segment)', 'DocumentCenter::verify/$1');

// Phase 24: Enterprise Integration Platform (EIP) Routes
$routes->group('integration', ['filter' => 'role:administrator,admin,admin_pusat'], function ($routes) {
    $routes->get('/', 'IntegrationCenter::index');
    $routes->post('generate-key', 'IntegrationCenter::generateApiKey');
    $routes->post('register-webhook', 'IntegrationCenter::registerWebhook');
    $routes->get('test-webhook/(:num)', 'IntegrationCenter::testWebhook/$1');
    $routes->get('export', 'IntegrationCenter::exportData');
});

// Phase 24: EIP OpenAPI / Health / Multi-version REST API
$routes->get('api/health', 'Api\HealthController::index');
$routes->get('api/docs/json', 'Api\DocsController::json');
$routes->get('api/docs/ui', 'Api\DocsController::ui');

// Phase 25 & 31.2: Production Hardening, Live Operations & Health Endpoints
$routes->get('health', 'StatusController::health');
$routes->get('status', 'StatusController::status');
$routes->get('status/live-metrics', 'StatusController::liveMetrics');
$routes->get('status/optimize-database', 'StatusController::optimizeDatabase');

// API Versioning: v1, v2, v3
foreach (['v1', 'v2', 'v3'] as $version) {
    $routes->group("api/{$version}", function ($routes) {
        $routes->post('auth/login', 'Api\v1\ApiController::login');
        $routes->post('auth/refresh', 'Api\v1\ApiController::refreshToken');
        $routes->get('temuan', 'Api\v1\ApiController::getTemuan');
        $routes->get('temuan/(:num)', 'Api\v1\ApiController::getTemuanDetail/$1');
        $routes->get('work-orders', 'Api\v1\ApiController::getWorkOrders');
        $routes->get('work-orders/(:num)', 'Api\v1\ApiController::getWorkOrderDetail/$1');
        $routes->get('assets', 'Api\v1\ApiController::getAssets');
        $routes->get('users', 'Api\v1\ApiController::getUsers');
        $routes->get('dashboard', 'Api\v1\ApiController::getDashboardStats');
        $routes->get('notifications', 'Api\v1\ApiController::getNotifications');
        $routes->get('documents', 'Api\v1\ApiController::getDocuments');
    });
}

