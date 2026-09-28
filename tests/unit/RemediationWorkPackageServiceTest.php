<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\RemediationWorkPackageService;
use App\Exceptions\UnprocessableEntityException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\MethodNotAllowedException;

/**
 * @internal
 */
final class RemediationWorkPackageServiceTest extends CIUnitTestCase
{
    protected RemediationWorkPackageService $service;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('default');
        $this->service = new RemediationWorkPackageService($this->db);
    }

    public function testWorkPackageCreationIdempotency(): void
    {
        $opUuid = 'TEST-UNIT-UUID-' . bin2hex(random_bytes(8));
        $res1 = $this->service->createWorkPackage([
            'client_operation_uuid' => $opUuid,
            'title'                 => 'Unit Test Package',
            'test_mode'             => 1,
        ], 101);

        $this->assertTrue($res1['success']);
        $this->assertSame('CREATED', $res1['status']);
        $this->assertFalse($res1['is_replay']);

        // Repeat creation with identical client_operation_uuid
        $res2 = $this->service->createWorkPackage([
            'client_operation_uuid' => $opUuid,
            'title'                 => 'Unit Test Package (Retry)',
            'test_mode'             => 1,
        ], 101);

        $this->assertTrue($res2['success']);
        $this->assertSame('EXISTING', $res2['status']);
        $this->assertTrue($res2['is_replay']);
        $this->assertSame($res1['package_id'], $res2['package_id']);
    }

    public function testFsmShortcutIllegalThrowsConflict(): void
    {
        $opUuid = 'TEST-UNIT-SHORTCUT-' . bin2hex(random_bytes(8));
        $res = $this->service->createWorkPackage([
            'client_operation_uuid' => $opUuid,
            'title'                 => 'Unit Test FSM Shortcut',
            'test_mode'             => 1,
        ], 101);

        $this->expectException(ConflictException::class);
        $this->service->approvePackage($res['package_id'], 201, 'SUPERVISOR');
    }

    public function testPhysicalDeleteThrowsMethodNotAllowed(): void
    {
        $opUuid = 'TEST-UNIT-DELETE-' . bin2hex(random_bytes(8));
        $res = $this->service->createWorkPackage([
            'client_operation_uuid' => $opUuid,
            'title'                 => 'Unit Test Delete Prohibition',
            'test_mode'             => 1,
        ], 101);

        $this->expectException(MethodNotAllowedException::class);
        $this->service->deleteWorkPackage($res['package_id'], 101);
    }

    public function testTopologyMutationForbidden(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->service->mutateTopology('DROP TABLE gis_translines');
    }

    public function testSubmitWithoutFindingsThrowsUnprocessable(): void
    {
        $opUuid = 'TEST-UNIT-EMPTY-' . bin2hex(random_bytes(8));
        $res = $this->service->createWorkPackage([
            'client_operation_uuid' => $opUuid,
            'title'                 => 'Unit Test Empty Package',
            'test_mode'             => 1,
        ], 101);

        $this->expectException(UnprocessableEntityException::class);
        $this->service->submitPackage($res['package_id'], 101);
    }
}
