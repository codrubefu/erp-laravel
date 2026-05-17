<?php

namespace Database\Seeders;

use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
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
            ['name' => 'locations.view', 'label' => 'View locations', 'description' => 'Read locations and assigned users.'],
            ['name' => 'locations.manage', 'label' => 'Manage locations', 'description' => 'Create, update, and delete locations.'],
            ['name' => 'subscriptions.view', 'label' => 'View subscriptions', 'description' => 'Read subscriptions.'],
            ['name' => 'subscriptions.create', 'label' => 'Create subscriptions', 'description' => 'Create subscriptions.'],
            ['name' => 'subscriptions.update', 'label' => 'Update subscriptions', 'description' => 'Update subscriptions and toggle active status.'],
            ['name' => 'subscriptions.delete', 'label' => 'Delete subscriptions', 'description' => 'Delete subscriptions.'],
            ['name' => 'subscriptions.restore', 'label' => 'Restore subscriptions', 'description' => 'Restore deleted subscriptions.'],
            ['name' => 'subscriptions.manage', 'label' => 'Manage subscriptions', 'description' => 'Manage all subscription actions.'],
            ['name' => 'articles.view', 'label' => 'View articles', 'description' => 'Read articles.'],
            ['name' => 'articles.create', 'label' => 'Create articles', 'description' => 'Create articles.'],
            ['name' => 'articles.update', 'label' => 'Update articles', 'description' => 'Update articles.'],
            ['name' => 'articles.delete', 'label' => 'Delete articles', 'description' => 'Delete articles.'],
            ['name' => 'articles.manage', 'label' => 'Manage articles', 'description' => 'Manage all article actions.'],
            ['name' => 'events.view', 'label' => 'View events', 'description' => 'Read events and event occurrences.'],
            ['name' => 'events.manage', 'label' => 'Manage events', 'description' => 'Create, update, and delete events.'],
            ['name' => 'event_participants.view', 'label' => 'View event participants', 'description' => 'Read event occurrence participants.'],
            ['name' => 'event_participants.manage', 'label' => 'Manage event participants', 'description' => 'Add and remove event occurrence participants.'],
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
            'locations.view',
            'subscriptions.view',
            'articles.view',
            'events.view',
            'event_participants.view',
        ])->pluck('id'));

        $staff = Group::query()->updateOrCreate(
            ['name' => 'staff'],
            ['label' => 'Staff', 'description' => 'Basic authenticated access.'],
        );
        $staff->rights()->sync($rights->only(['profile.view'])->pluck('id'));

        User::factory(10)->create()->each(fn (User $user) => $user->groups()->sync([$staff->id]));

     

        $this->call(LocationSeeder::class);
    }
}
