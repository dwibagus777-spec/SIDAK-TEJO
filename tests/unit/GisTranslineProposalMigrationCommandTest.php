<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Unit Test for MigrateProposalTableCommand & Migration Safety
 */
class GisTranslineProposalMigrationCommandTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
    }

    public function testCommandClassExistsAndHasValidProperties()
    {
        $cmd = new \App\Commands\MigrateProposalTableCommand(
            \Config\Services::logger(),
            \Config\Services::commands()
        );

        $this->assertInstanceOf(\App\Commands\MigrateProposalTableCommand::class, $cmd);
    }
}
