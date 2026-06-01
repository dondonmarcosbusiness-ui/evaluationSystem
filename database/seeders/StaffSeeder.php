<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Staff;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $staffData = [
            [
                'email' => 'staff1@neust.edu.ph',
                'firstname' => 'Juanita',
                'lastname' => 'Dela Cruz',
                'id_number' => 'STF-0001',
                'department' => 'Registrar Office',
                'designation' => 'Registrar Officer',
            ],
            [
                'email' => 'staff2@neust.edu.ph',
                'firstname' => 'Pedro',
                'lastname' => 'Penduko',
                'id_number' => 'STF-0002',
                'department' => 'Finance Department',
                'designation' => 'Cashier',
            ],
            [
                'email' => 'staff3@neust.edu.ph',
                'firstname' => 'Maria',
                'lastname' => 'Makiling',
                'id_number' => 'STF-0003',
                'department' => 'Library Services',
                'designation' => 'Head Librarian',
            ],
        ];

        // Ensure Staff role exists in Spatie roles
        $guards = ['web', 'sanctum'];
        $staffRoles = [];
        foreach ($guards as $guard) {
            $staffRoles[$guard] = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => $guard]);
        }

        foreach ($staffData as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'firstname' => $data['firstname'],
                    'lastname' => $data['lastname'],
                    'name' => $data['lastname'] . ', ' . $data['firstname'],
                    'id_number' => $data['id_number'],
                    'password' => Hash::make('password123'),
                    'role' => 'staff',
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            // Assign Spatie Role
            $user->assignRole('Staff');

            Staff::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'department' => $data['department'],
                    'designation' => $data['designation'],
                ]
            );
        }
    }
}
