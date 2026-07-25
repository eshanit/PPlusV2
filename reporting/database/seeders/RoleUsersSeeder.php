<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class RoleUsersSeeder extends Seeder
{
    /**
     * One demo login per role, all with password "password".
     *
     * @var list<array{role: string, firstname: string, lastname: string, email: string}>
     */
    private const USERS = [
        ['role' => 'admin', 'firstname' => 'Admin', 'lastname' => 'User', 'email' => 'admin@penplus.local'],
        ['role' => 'district_admin', 'firstname' => 'District', 'lastname' => 'Admin', 'email' => 'district-admin@penplus.local'],
        ['role' => 'evaluator', 'firstname' => 'Evaluator', 'lastname' => 'User', 'email' => 'evaluator@penplus.local'],
    ];

    public function run(): void
    {
        foreach (self::USERS as $data) {
            $role = Role::where('name', $data['role'])->first();

            if (! $role) {
                throw new RuntimeException("Role [{$data['role']}] not found — run RoleSeeder first.");
            }

            $user = User::firstOrNew(['email' => $data['email']]);

            if (! $user->exists) {
                $user->id = (string) Str::uuid();
            }

            $user->fill([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'username' => $data['role'],
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);

            $user->save();

            $this->command?->info("Seeded {$data['role']}: {$data['email']} / password");
        }
    }
}
