<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceFaultCasesAndActualFindings extends Migration
{
    public function up()
    {
        $db = $this->db ?? \Config\Database::connect();

        // 1. Enhance fault_cases
        if ($db->tableExists('fault_cases')) {
            $fieldsToAdd = [];

            if (!$db->fieldExists('ulp_id', 'fault_cases')) {
                $fieldsToAdd['ulp_id'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'case_number',
                ];
            }
            if (!$db->fieldExists('penyulang_id', 'fault_cases')) {
                $fieldsToAdd['penyulang_id'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'ulp_id',
                ];
            }
            if (!$db->fieldExists('source_type', 'fault_cases')) {
                $fieldsToAdd['source_type'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'GI',
                    'after'      => 'penyulang_id',
                ];
            }
            if (!$db->fieldExists('current_r', 'fault_cases')) {
                $fieldsToAdd['current_r'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                    'after'      => 'source_type',
                ];
            }
            if (!$db->fieldExists('current_s', 'fault_cases')) {
                $fieldsToAdd['current_s'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                    'after'      => 'current_r',
                ];
            }
            if (!$db->fieldExists('current_t', 'fault_cases')) {
                $fieldsToAdd['current_t'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                    'after'      => 'current_s',
                ];
            }
            if (!$db->fieldExists('current_n', 'fault_cases')) {
                $fieldsToAdd['current_n'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                    'after'      => 'current_t',
                ];
            }
            if (!$db->fieldExists('conductor_type', 'fault_cases')) {
                $fieldsToAdd['conductor_type'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'AAAC 150 mm²',
                    'after'      => 'current_n',
                ];
            }
            if (!$db->fieldExists('formula_version', 'fault_cases')) {
                $fieldsToAdd['formula_version'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'FLI-2.1.0-PHYSICS-RIGOR',
                    'after'      => 'conductor_type',
                ];
            }
            if (!$db->fieldExists('missing_parameters_json', 'fault_cases')) {
                $fieldsToAdd['missing_parameters_json'] = [
                    'type' => 'TEXT',
                    'null' => true,
                    'after'=> 'formula_version',
                ];
            }
            if (!$db->fieldExists('telemetry_payload_json', 'fault_cases')) {
                $fieldsToAdd['telemetry_payload_json'] = [
                    'type' => 'LONGTEXT',
                    'null' => true,
                    'after'=> 'missing_parameters_json',
                ];
            }
            if (!$db->fieldExists('estimated_lat', 'fault_cases')) {
                $fieldsToAdd['estimated_lat'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '11,8',
                    'null'       => true,
                    'after'      => 'telemetry_payload_json',
                ];
            }
            if (!$db->fieldExists('estimated_lng', 'fault_cases')) {
                $fieldsToAdd['estimated_lng'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '11,8',
                    'null'       => true,
                    'after'      => 'estimated_lat',
                ];
            }
            if (!$db->fieldExists('follow_up_status', 'fault_cases')) {
                $fieldsToAdd['follow_up_status'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'MENUNGGU_PEMERIKSAAN',
                    'after'      => 'estimated_lng',
                ];
            }

            if (!empty($fieldsToAdd)) {
                $this->forge->addColumn('fault_cases', $fieldsToAdd);
            }

            // Relax device_asset_id to NULLABLE (when source_type is GI, there is no protection device asset)
            try {
                $db->query("ALTER TABLE `fault_cases` MODIFY COLUMN `device_asset_id` INT(11) UNSIGNED NULL DEFAULT NULL");
            } catch (\Throwable $e) {
                // ignore if already nullable
            }
        }

        // 2. Enhance fault_actual_findings
        if ($db->tableExists('fault_actual_findings')) {
            $findingFieldsToAdd = [];

            if (!$db->fieldExists('outcome_type', 'fault_actual_findings')) {
                $findingFieldsToAdd['outcome_type'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'CONFIRMED',
                    // CONFIRMED, NOT_FOUND, AMBIGUOUS_VERIFIED
                    'after'      => 'revision_no',
                ];
            }
            if (!$db->fieldExists('component_damaged', 'fault_actual_findings')) {
                $findingFieldsToAdd['component_damaged'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'cause_category_code',
                ];
            }
            if (!$db->fieldExists('is_location_match', 'fault_actual_findings')) {
                $findingFieldsToAdd['is_location_match'] = [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'component_damaged',
                ];
            }
            if (!$db->fieldExists('is_component_match', 'fault_actual_findings')) {
                $findingFieldsToAdd['is_component_match'] = [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'is_location_match',
                ];
            }
            if (!$db->fieldExists('distance_error_meters', 'fault_actual_findings')) {
                $findingFieldsToAdd['distance_error_meters'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                    'after'      => 'is_component_match',
                ];
            }
            if (!$db->fieldExists('inspected_area_notes', 'fault_actual_findings')) {
                $findingFieldsToAdd['inspected_area_notes'] = [
                    'type' => 'TEXT',
                    'null' => true,
                    'after'=> 'finding_notes',
                ];
            }
            if (!$db->fieldExists('not_found_reason', 'fault_actual_findings')) {
                $findingFieldsToAdd['not_found_reason'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'inspected_area_notes',
                ];
            }
            if (!$db->fieldExists('created_by', 'fault_actual_findings')) {
                $findingFieldsToAdd['created_by'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'technician_name',
                ];
            }
            if (!$db->fieldExists('inspected_at', 'fault_actual_findings')) {
                $findingFieldsToAdd['inspected_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after'=> 'verified_at',
                ];
            }

            if (!empty($findingFieldsToAdd)) {
                $this->forge->addColumn('fault_actual_findings', $findingFieldsToAdd);
            }
        }
    }

    public function down()
    {
        // Additive columns preserved
    }
}
