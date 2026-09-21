<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->user = User::create([
            'name' => 'Original Owner',
            'email' => 'owner@example.test',
            'phone_number' => '15555550100',
            'password' => Hash::make('original-password'),
            'email_verified_at' => now(),
        ]);
    }

    private function profileData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated Owner',
            'email' => 'updated@example.test',
            'phone_number' => '15555550101',
            'address_1' => '123 Main Street',
            'address_2' => 'Apartment 4',
            'city' => 'Cambridge',
            'state' => 'Massachusetts',
            'zip_code' => '02139',
            'country' => 'United States',
        ], $overrides);
    }

    public function test_one_submission_updates_contact_and_address_and_keeps_a_blank_password(): void
    {
        $passwordHash = $this->user->password;
        $verification = $this->user->email_verified_at;
        $this->actingAs($this->user)->put(route('profile.update'), $this->profileData([
            'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect(route('profile'))->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', array_merge($this->profileData(), ['id' => $this->user->id]));
        $this->user->refresh();
        $this->assertSame($passwordHash, $this->user->password);
        $this->assertEquals($verification, $this->user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_optional_confirmed_password_is_hashed_in_the_same_save(): void
    {
        $this->actingAs($this->user)->put(route('profile.update'), $this->profileData([
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ]))->assertRedirect(route('profile'))->assertSessionHasNoErrors();

        $this->user->refresh();
        $this->assertSame('123 Main Street', $this->user->address_1);
        $this->assertTrue(Hash::check('replacement-password', $this->user->password));
        $this->assertNotSame('replacement-password', $this->user->password);
    }

    public function test_validation_failure_leaves_the_entire_profile_unchanged_and_retains_input(): void
    {
        $before = $this->user->fresh()->getAttributes();
        $this->actingAs($this->user)->from(route('profile'))->put(route('profile.update'), $this->profileData([
            'password' => 'replacement-password', 'password_confirmation' => 'does-not-match',
            'phone_number' => 'invalid', 'address_1' => str_repeat('a', 256),
        ]))->assertRedirect(route('profile'))
            ->assertSessionHasErrors(['password', 'phone_number', 'address_1'])
            ->assertSessionHasInput('city', 'Cambridge')
            ->assertSessionMissing('_old_input.password');

        $this->assertSame($before, $this->user->fresh()->getAttributes());
    }

    public function test_email_and_phone_are_unique_but_the_current_values_are_allowed(): void
    {
        User::create([
            'name' => 'Other Owner', 'email' => 'taken@example.test',
            'phone_number' => '15555550102', 'password' => 'unused-hash',
        ]);
        $this->actingAs($this->user)->put(route('profile.update'), $this->profileData([
            'email' => 'taken@example.test', 'phone_number' => '15555550102',
        ]))->assertSessionHasErrors(['email', 'phone_number']);
        $this->assertSame('Original Owner', $this->user->fresh()->name);

        $this->put(route('profile.update'), $this->profileData([
            'email' => $this->user->email, 'phone_number' => $this->user->phone_number,
        ]))->assertSessionHasNoErrors();
    }

    public function test_empty_optional_contact_and_address_fields_and_international_postal_codes_are_supported(): void
    {
        $this->actingAs($this->user)->put(route('profile.update'), $this->profileData([
            'phone_number' => '', 'address_1' => '', 'address_2' => '', 'city' => '',
            'state' => '', 'country' => '', 'zip_code' => '',
        ]))->assertSessionHasNoErrors();
        $this->assertNull($this->user->fresh()->address_1);
        $this->assertNull($this->user->fresh()->phone_number);

        $this->put(route('profile.update'), $this->profileData([
            'city' => 'London', 'country' => 'United Kingdom', 'zip_code' => 'SW1A 1AA',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('SW1A 1AA', $this->user->fresh()->zip_code);
    }

    public function test_existing_formatted_and_shared_phone_numbers_do_not_block_address_changes(): void
    {
        foreach (['+1 (555) 555-0100', '15555550100'] as $index => $phoneNumber) {
            $this->user->update(['phone_number' => $phoneNumber]);
            User::create([
                'name' => 'Shared Number Owner', 'email' => 'shared'.$index.'@example.test',
                'phone_number' => $phoneNumber, 'password' => 'unused-hash',
            ]);

            $page = $this->actingAs($this->user)->get(route('profile'))->assertOk();
            $this->assertSame(1, preg_match('/<input[^>]*id="phone_number"[^>]*>/', $page->getContent(), $phoneInput));
            $this->assertStringNotContainsString('pattern=', $phoneInput[0]);
            $this->assertStringContainsString('maxlength="20"', $phoneInput[0]);

            $this->put(route('profile.update'), $this->profileData([
                'phone_number' => $phoneNumber, 'address_1' => 'Updated Address '.$index,
            ]))->assertRedirect(route('profile'))->assertSessionHasNoErrors();

            $this->user->refresh();
            $this->assertSame($phoneNumber, $this->user->phone_number);
            $this->assertSame('Updated Address '.$index, $this->user->address_1);
        }
    }

    public function test_submitted_identity_and_verification_fields_cannot_target_another_user(): void
    {
        $other = User::create([
            'name' => 'Other Owner', 'email' => 'other@example.test', 'password' => 'unused-hash',
        ]);
        $otherBefore = $other->fresh()->getAttributes();
        $verification = $this->user->email_verified_at;
        $originalId = $this->user->id;

        $this->actingAs($this->user)->put(route('profile.update'), $this->profileData([
            'id' => $other->id, 'user_id' => $other->id, 'email_verified_at' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        $this->user->refresh();
        $this->assertSame($originalId, $this->user->id);
        $this->assertSame('Updated Owner', $this->user->name);
        $this->assertEquals($verification, $this->user->email_verified_at);
    }

    public function test_guests_cannot_submit_profile_changes(): void
    {
        $this->put(route('profile.update'), $this->profileData())->assertRedirect(route('login'));
        $this->assertSame('Original Owner', $this->user->fresh()->name);
    }

    public function test_profile_has_a_single_save_form_with_existing_address_values(): void
    {
        $this->user->update(['address_1' => '42 Existing Street', 'zip_code' => '00123']);
        $response = $this->actingAs($this->user)->get(route('profile'))
            ->assertOk()->assertSee('42 Existing Street')->assertSee('00123')
            ->assertSee('Save profile')->assertDontSee('Update Name');

        $this->assertSame(1, substr_count($response->getContent(), 'action="'.route('profile.update').'"'));
    }
}
