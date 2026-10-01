<?php

namespace Tests\Feature;

use App\Enums\FollowupStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFollowupTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_schedule_a_followup_with_a_reminder_time(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('customers.followups.store', $customer), [
            'followup_date' => now()->addWeek()->toDateString(),
            'reminder_at' => now()->addDay()->format('Y-m-d H:i'),
            'notes' => 'Confirm delivery date.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_followups', [
            'customer_id' => $customer->id,
            'notes' => 'Confirm delivery date.',
            'status' => FollowupStatus::Pending->value,
        ]);
    }

    public function test_a_followup_status_can_be_changed_with_a_single_field_update(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $followup = $this->inTenant($user, fn () => $customer->followups()->create([
            'followup_date' => now()->toDateString(),
            'status' => FollowupStatus::Pending,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->put(route('customers.followups.update', [$customer, $followup]), [
                'status' => FollowupStatus::Completed->value,
            ])
            ->assertRedirect();

        $this->assertSame(FollowupStatus::Completed, $followup->refresh()->status);
    }

    public function test_a_followup_can_be_deleted(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $followup = $this->inTenant($user, fn () => $customer->followups()->create([
            'followup_date' => now()->toDateString(),
            'status' => FollowupStatus::Pending,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->delete(route('customers.followups.destroy', [$customer, $followup]))
            ->assertRedirect();

        $this->assertDatabaseMissing('customer_followups', ['id' => $followup->id]);
    }

    public function test_a_followup_from_another_customer_cannot_be_touched(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);
        $otherCustomer = $this->customerFor($user);

        $followup = $this->inTenant($user, fn () => $otherCustomer->followups()->create([
            'followup_date' => now()->toDateString(),
            'status' => FollowupStatus::Pending,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->delete(route('customers.followups.destroy', [$customer, $followup]))
            ->assertNotFound();

        $this->assertDatabaseHas('customer_followups', ['id' => $followup->id]);
    }

    public function test_a_due_followup_appears_on_the_reminders_page(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, fn () => $customer->followups()->create([
            'followup_date' => now()->subDay()->toDateString(),
            'notes' => 'Call about the marble order.',
            'status' => FollowupStatus::Pending,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('reminders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reminders')
                ->has('followups', 1)
                ->where('followups.0.notes', 'Call about the marble order.')
            );
    }

    public function test_a_future_followup_does_not_appear_yet(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, fn () => $customer->followups()->create([
            'followup_date' => now()->addMonth()->toDateString(),
            'status' => FollowupStatus::Pending,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('reminders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('followups', 0));
    }

    public function test_a_completed_followup_drops_off_the_reminders_list(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, fn () => $customer->followups()->create([
            'followup_date' => now()->subDay()->toDateString(),
            'status' => FollowupStatus::Completed,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('reminders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('followups', 0));
    }
}
