<?php

namespace Tests\Feature;

use App\Users\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_found_by_slug_without_authentication(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Acme SRL',
            'slug' => 'acme',
        ]);

        $this->getJson('/api/organizations/slug/acme')
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id)
            ->assertJsonPath('data.slug', 'acme')
            ->assertJsonPath('data.name', 'Acme SRL');
    }

    public function test_organization_slug_lookup_returns_not_found_for_unknown_slug(): void
    {
        $this->getJson('/api/organizations/slug/missing')
            ->assertNotFound();
    }
}
