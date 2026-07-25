<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin'], ['label' => 'System Administrator']);
        Role::firstOrCreate(['name' => 'district_admin'], ['label' => 'District Admin']);
        Role::firstOrCreate(['name' => 'evaluator'], ['label' => 'Evaluator']);
    }
}
