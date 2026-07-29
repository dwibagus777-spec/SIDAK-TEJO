<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * FASE 32 — Gamification System
 * Tables: user_gamification, user_achievements, user_activity_timeline
 */
class CreateGamificationTables extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------------
        // TABLE 1: user_gamification — Points, Level, Streak per user
        // -----------------------------------------------------------------------
        if (!$this->db->tableExists('user_gamification')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'           => ['type' => 'INT', 'unsigned' => true],
                'total_points'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'level'             => ['type' => 'ENUM', 'constraint' => ['bronze', 'silver', 'gold', 'platinum', 'diamond'], 'default' => 'bronze'],
                'streak_days'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'last_activity_date'=> ['type' => 'DATE', 'null' => true],
                'weekly_target'     => ['type' => 'INT', 'unsigned' => true, 'default' => 25],
                'monthly_target'    => ['type' => 'INT', 'unsigned' => true, 'default' => 100],
                'temuan_count'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'selesai_count'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'emergency_selesai' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'sla_met_count'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'sla_overdue_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('user_id');
            $this->forge->createTable('user_gamification');
        }

        // -----------------------------------------------------------------------
        // TABLE 2: user_achievements — Badge per user
        // -----------------------------------------------------------------------
        if (!$this->db->tableExists('user_achievements')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'          => ['type' => 'INT', 'unsigned' => true],
                'achievement_key'  => ['type' => 'VARCHAR', 'constraint' => 80],
                'achievement_name' => ['type' => 'VARCHAR', 'constraint' => 120],
                'achievement_icon' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'fa-trophy'],
                'achievement_desc' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'points_awarded'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'achieved_at'      => ['type' => 'DATETIME', 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('user_id');
            $this->forge->addUniqueKey(['user_id', 'achievement_key']);
            $this->forge->createTable('user_achievements');
        }

        // -----------------------------------------------------------------------
        // TABLE 3: user_activity_timeline — Work timeline per user per day
        // -----------------------------------------------------------------------
        if (!$this->db->tableExists('user_activity_timeline')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'     => ['type' => 'INT', 'unsigned' => true],
                'action_type' => ['type' => 'VARCHAR', 'constraint' => 60],
                'description' => ['type' => 'VARCHAR', 'constraint' => 255],
                'ref_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'ref_type'    => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'points'      => ['type' => 'INT', 'default' => 0],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['user_id', 'created_at']);
            $this->forge->createTable('user_activity_timeline');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('user_activity_timeline', true);
        $this->forge->dropTable('user_achievements', true);
        $this->forge->dropTable('user_gamification', true);
    }
}
