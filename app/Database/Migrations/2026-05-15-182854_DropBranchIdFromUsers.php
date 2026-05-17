<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropBranchIdFromUsers extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('users', 'branch_id');
    }

    public function down()
    {
        $this->forge->addColumn('users', [
            'branch_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
    }
}
