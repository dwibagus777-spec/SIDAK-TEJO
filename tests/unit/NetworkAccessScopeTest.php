<?php

namespace Tests\Unit;

use App\Services\NetworkAccessScope;
use CodeIgniter\Test\CIUnitTestCase;

class NetworkAccessScopeTest extends CIUnitTestCase
{
    public function testInspectionRoleIsRestrictedToAssignedUlp(): void
    {
        $scope = new NetworkAccessScope('inspeksi', 7);

        $this->assertTrue($scope->allowsUlp(7));
        $this->assertFalse($scope->allowsUlp(8));
    }

    public function testRestrictedRoleWithoutUlpDoesNotBecomeUnrestricted(): void
    {
        $scope = new NetworkAccessScope('inspeksi', null);

        $this->assertFalse($scope->allowsUlp(7));
    }

    public function testOnlyConfiguredCrossUlpRolesAreUnrestricted(): void
    {
        $adminScope = new NetworkAccessScope('administrator', null);
        $pdkbScope = new NetworkAccessScope('pdkb', null);

        $this->assertTrue($adminScope->allowsUlp(7));
        $this->assertTrue($pdkbScope->allowsUlp(8));
    }
}
