<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['name' => 'admin', 'label' => 'System Administrator']);
        Role::create(['name' => 'district_admin', 'label' => 'District Admin']);
        Role::create(['name' => 'evaluator', 'label' => 'Evaluator']);
    }
}
