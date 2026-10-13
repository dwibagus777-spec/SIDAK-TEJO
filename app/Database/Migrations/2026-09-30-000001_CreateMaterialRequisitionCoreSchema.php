<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: CreateMaterialRequisitionCoreSchema (B8.2 Material Requisition Core)
 *
 * Implements 5 tables for B8.2:
 * 1. material_warehouses
 * 2. material_requisition_packages
 * 3. material_requisition_items
 * 4. material_requisition_history
 * 5. material_consumption_reconciliations
 *
 * HARDENED GOVERNANCE:
 * - ALL Foreign Keys use ON DELETE RESTRICT (CORR-03 / B82-G05).
 * - Exact 9 FSM states in ENUM (CORR-01).
 * - Exact 6 Provenance levels in ENUM (CORR-02).
 * - Master Material FK points to existing master_materials table (CORR-04).
 */
class CreateMaterialRequisitionCoreSchema extends Migration
{
    public function up(): void
    {
        // 1. material_warehouses (Authority Gudang ULP)
        if (!$this->db->tableExists('material_warehouses')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'warehouse_code'   => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false],
                'warehouse_name'   => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false],
                'ulp_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'location_address' => ['type' => 'TEXT', 'null' => true],
                'status'           => ['type' => 'ENUM', 'constraint' => ['ACTIVE', 'INACTIVE'], 'default' => 'ACTIVE'],
                'created_at'       => ['type' => 'DATETIME', 'null' => false],
                'updated_at'       => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('warehouse_code');
            $this->forge->createTable('material_warehouses', true);
        }

        // 2. material_requisition_packages
        if (!$this->db->tableExists('material_requisition_packages')) {
            $this->forge->addField([
                'id'                    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'requisition_code'      => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false],
                'work_package_id'       => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'client_operation_uuid' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'status'                => [
                    'type'       => 'ENUM',
                    'constraint' => ['DRAFT', 'REQUESTED', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'RESERVED', 'ISSUED', 'CONSUMED', 'RECONCILED'],
                    'default'    => 'DRAFT',
                ],
                'warehouse_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'requested_by'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'approved_by'           => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'issued_by'             => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'reconciled_by'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'rejection_notes'       => ['type' => 'TEXT', 'null' => true],
                'test_mode'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at'            => ['type' => 'DATETIME', 'null' => false],
                'updated_at'            => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('requisition_code');
            $this->forge->addUniqueKey('client_operation_uuid');
            $this->forge->addForeignKey('work_package_id', 'remediation_work_packages', 'id', 'CASCADE', 'RESTRICT', 'fk_mrp_work_package');
            $this->forge->addForeignKey('warehouse_id', 'material_warehouses', 'id', 'CASCADE', 'RESTRICT', 'fk_mrp_warehouse');
            $this->forge->createTable('material_requisition_packages', true);
        }

        // 3. material_requisition_items
        if (!$this->db->tableExists('material_requisition_items')) {
            $this->forge->addField([
                'id'                     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'requisition_package_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'material_master_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
                'item_code'              => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'requested_quantity'     => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => false],
                'approved_quantity'      => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'issued_quantity'        => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'consumed_quantity'      => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'returned_quantity'      => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'unit_of_measure'        => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => false],
                'item_status'            => [
                    'type'       => 'ENUM',
                    'constraint' => ['PENDING', 'APPROVED', 'RESERVED', 'ISSUED', 'INSTALLED', 'RETURNED', 'REJECTED', 'PENDING_CLASSIFICATION'],
                    'default'    => 'PENDING',
                ],
                'provenance_level'       => [
                    'type'       => 'ENUM',
                    'constraint' => ['OBSERVED', 'DERIVED', 'ANALYTICAL', 'RECOMMENDED', 'APPROVED', 'EXECUTED'],
                    'default'    => 'RECOMMENDED',
                ],
                'created_at'             => ['type' => 'DATETIME', 'null' => false],
                'updated_at'             => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addForeignKey('requisition_package_id', 'material_requisition_packages', 'id', 'CASCADE', 'RESTRICT', 'fk_mri_package');
            $this->forge->addForeignKey('material_master_id', 'master_materials', 'id', 'CASCADE', 'RESTRICT', 'fk_mri_master');
            $this->forge->createTable('material_requisition_items', true);
        }

        // 4. material_requisition_history
        if (!$this->db->tableExists('material_requisition_history')) {
            $this->forge->addField([
                'id'                     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'requisition_package_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'previous_status'        => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
                'new_status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false],
                'action_name'            => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'actor_id'               => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'actor_role'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'transition_notes'       => ['type' => 'TEXT', 'null' => true],
                'created_at'             => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addForeignKey('requisition_package_id', 'material_requisition_packages', 'id', 'CASCADE', 'RESTRICT', 'fk_mrh_package');
            $this->forge->createTable('material_requisition_history', true);
        }

        // 5. material_consumption_reconciliations
        if (!$this->db->tableExists('material_consumption_reconciliations')) {
            $this->forge->addField([
                'id'                     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'requisition_package_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'work_package_id'        => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'total_items_issued'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
                'total_items_consumed'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
                'total_items_returned'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false],
                'overall_variance_pct'   => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => false],
                'reconciliation_status'  => [
                    'type'       => 'ENUM',
                    'constraint' => ['EXACT_MATCH', 'MINOR_VARIANCE', 'EXCESS_CONSUMPTION', 'UNACCOUNTED_LOSS'],
                    'null'       => false,
                ],
                'reconciled_by'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
                'evidence_sha256'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'reconciliation_notes'   => ['type' => 'TEXT', 'null' => true],
                'created_at'             => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addForeignKey('requisition_package_id', 'material_requisition_packages', 'id', 'CASCADE', 'RESTRICT', 'fk_mcr_package');
            $this->forge->addForeignKey('work_package_id', 'remediation_work_packages', 'id', 'CASCADE', 'RESTRICT', 'fk_mcr_wp');
            $this->forge->createTable('material_consumption_reconciliations', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('material_consumption_reconciliations', true);
        $this->forge->dropTable('material_requisition_history', true);
        $this->forge->dropTable('material_requisition_items', true);
        $this->forge->dropTable('material_requisition_packages', true);
        $this->forge->dropTable('material_warehouses', true);
    }
}
