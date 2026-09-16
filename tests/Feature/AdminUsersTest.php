<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_client_cannot_enter_admin_but_admin_can(): void
    {
        $this->get('/admin')->assertRedirect();

        $client = User::factory()->create();
        $this->actingAs($client)->get('/admin')->assertForbidden();
        $this->actingAs($client)->get(UserResource::getUrl())->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get(UserResource::getUrl())->assertOk();
    }

    public function test_role_is_independent_of_admin_name_email_and_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->update([
            'name' => 'New Name',
            'email' => 'new-admin@example.test',
            'password' => 'new-safe-password',
        ]);

        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertTrue(Hash::check('new-safe-password', $admin->fresh()->password));
    }

    public function test_last_admin_cannot_be_demoted_and_no_admin_can_be_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        try {
            $admin->update(['is_admin' => false]);
            $this->fail('Last admin was demoted.');
        } catch (\RuntimeException $e) {
            $this->assertTrue($admin->fresh()->is_admin);
        }

        $this->assertFalse(UserResource::canDelete($admin));

        $this->expectException(\RuntimeException::class);
        $admin->delete();
    }

    public function test_a_client_cannot_promote_themselves_through_profile(): void
    {
        $client = User::factory()->create();

        $this->actingAs($client)->patch('/profile', [
            'name' => 'Client New',
            'email' => $client->email,
            'is_admin' => true,
        ])->assertRedirect();

        $this->assertFalse($client->fresh()->is_admin);
    }

    public function test_admin_edit_keeps_existing_password_when_left_blank(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $oldHash = $client->password;

        $this->actingAs($admin);
        Livewire::test(EditUser::class, ['record' => $client->getRouteKey()])
            ->fillForm([
                'name' => 'Edited Client',
                'email' => $client->email,
                'is_admin' => 0,
                'password' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($oldHash, $client->fresh()->password);
        $this->assertSame('Edited Client', $client->fresh()->name);
    }

    public function test_admin_can_promote_client_and_replace_password_without_exposing_old_hash(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $oldHash = $client->password;

        $this->actingAs($admin);
        $page = Livewire::test(EditUser::class, ['record' => $client->getRouteKey()]);
        $this->assertNull($page->instance()->form->getRawState()['password'] ?? null);

        $page->fillForm([
            'name' => $client->name,
            'email' => $client->email,
            'is_admin' => 1,
            'password' => 'replacement-password-123',
        ])->call('save')->assertHasNoFormErrors();

        $client->refresh();
        $this->assertTrue($client->is_admin);
        $this->assertNotSame($oldHash, $client->password);
        $this->assertTrue(Hash::check('replacement-password-123', $client->password));
    }

    public function test_an_admin_cannot_delete_their_account_through_the_store_profile(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'safe-password-123']);

        $this->actingAs($admin)->delete('/profile', [
            'password' => 'safe-password-123',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true]);
    }

    public function test_an_admin_can_demote_another_admin_when_one_remains(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Livewire::test(EditUser::class, ['record' => $other->getRouteKey()])
            ->fillForm([
                'name' => $other->name,
                'email' => $other->email,
                'is_admin' => 0,
                'password' => '',
            ])->call('save')->assertHasNoFormErrors();

        $this->assertFalse($other->fresh()->is_admin);
        $this->assertTrue($admin->fresh()->is_admin);
    }
}
