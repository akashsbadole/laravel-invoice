<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerNoteCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_add_a_note_to_a_customer(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $response = $this->actingAs($user)->post(
            route('customers.notes.store', $customer),
            ['type' => 'communication', 'note' => 'Called about the pending invoice.'],
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_notes', [
            'customer_id' => $customer->id,
            'note' => 'Called about the pending invoice.',
            'created_by' => $user->id,
        ]);
    }

    public function test_a_note_can_be_attached_to_a_specific_invoice(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('customers.notes.store', $customer), [
            'type' => 'private',
            'note' => 'Asked for a revised quote.',
        ])->assertRedirect();

        $this->assertDatabaseHas('customer_notes', [
            'customer_id' => $customer->id,
            'invoice_id' => null,
            'type' => 'private',
        ]);
    }

    public function test_a_note_is_rejected_without_a_body(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('customers.notes.store', $customer), [
            'type' => 'private',
            'note' => '',
        ])->assertSessionHasErrors('note');

        $this->assertSame(0, Customer::find($customer->id)->notesLog()->count());
    }

    public function test_notes_are_listed_on_the_customer_page(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, fn () => $customer->notesLog()->create([
            'type' => 'communication',
            'note' => 'Requested a call back.',
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/show')
                ->has('customer.notes_log', 1)
                ->where('customer.notes_log.0.note', 'Requested a call back.')
            );
    }
}
