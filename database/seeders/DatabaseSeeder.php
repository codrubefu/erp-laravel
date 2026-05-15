<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Right;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rights = collect([
            ['name' => 'profile.view', 'label' => 'View own profile', 'description' => 'Access the authenticated user profile.'],
            ['name' => 'users.view', 'label' => 'View users', 'description' => 'Read user records.'],
            ['name' => 'users.manage', 'label' => 'Manage users', 'description' => 'Create, update, and deactivate users.'],
            ['name' => 'groups.view', 'label' => 'View groups', 'description' => 'Read user groups and their rights.'],
            ['name' => 'groups.manage', 'label' => 'Manage groups', 'description' => 'Create, update, and delete user groups.'],
            ['name' => 'rights.view', 'label' => 'View rights', 'description' => 'Read available application rights.'],
            ['name' => 'rights.manage', 'label' => 'Manage rights', 'description' => 'Create, update, and delete application rights.'],
        ])->mapWithKeys(fn (array $right) => [
            $right['name'] => Right::query()->updateOrCreate(
                ['name' => $right['name']],
                ['label' => $right['label'], 'description' => $right['description']],
            ),
        ]);

        $admin = Group::query()->updateOrCreate(
            ['name' => 'admin'],
            ['label' => 'Administrator', 'description' => 'Full application access.'],
        );
        $admin->rights()->sync($rights->pluck('id'));

        $manager = Group::query()->updateOrCreate(
            ['name' => 'manager'],
            ['label' => 'Manager', 'description' => 'Can view users, groups, and rights.'],
        );
        $manager->rights()->sync($rights->only([
            'profile.view',
            'users.view',
            'groups.view',
            'rights.view',
        ])->pluck('id'));

        $staff = Group::query()->updateOrCreate(
            ['name' => 'staff'],
            ['label' => 'Staff', 'description' => 'Basic authenticated access.'],
        );
        $staff->rights()->sync($rights->only(['profile.view'])->pluck('id'));

        User::factory(10)->create()->each(fn (User $user) => $user->groups()->sync([$staff->id]));

        $testUser = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+15550000000',
            'active' => true,
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $testUser->groups()->sync([$admin->id]);
    }
}
