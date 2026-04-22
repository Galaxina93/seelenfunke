<?php

namespace Tests\Feature\Livewire\Shop\Order;

use App\Livewire\Shop\Order\OrderRevocationForm;
use App\Models\Admin\Admin;
use App\Models\Order\OrderRevocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        \Illuminate\Support\Facades\Mail::fake();
    }

    public function test_customer_can_submit_revocation_with_valid_attachment()
    {
        $file = UploadedFile::fake()->image('defekt.jpg')->size(100);

        Livewire::test(OrderRevocationForm::class)
            ->set('name', 'Max Mustermann')
            ->set('email', 'max@example.com')
            ->set('order_number', '123456789')
            ->set('items', 'Seelenkristall Blau')
            ->set('attachments', [$file])
            ->call('submitRevocation')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('order_revocations', [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'order_number' => '123456789'
        ]);

        $revocation = OrderRevocation::first();
        $this->assertCount(1, $revocation->attachments);

        // Verify file is stored securely
        Storage::disk('local')->assertExists($revocation->attachments[0]);
    }

    public function test_revocation_rejects_files_larger_than_5_mb()
    {
        $file = UploadedFile::fake()->createWithContent('huge.jpg', str_repeat('A', 6000 * 1024)); // 6MB

        Livewire::test(OrderRevocationForm::class)
            ->set('attachments', [$file])
            ->assertHasErrors(['attachments']);
    }

    public function test_revocation_slices_attachments_when_more_than_two_provided()
    {
        $file1 = UploadedFile::fake()->image('pic1.jpg')->size(100);
        $file2 = UploadedFile::fake()->image('pic2.jpg')->size(100);
        $file3 = UploadedFile::fake()->image('pic3.jpg')->size(100);

        Livewire::test(OrderRevocationForm::class)
            ->set('attachments', [$file1, $file2, $file3])
            ->assertHasErrors(['attachments']);
            
        // Assert that the array is shortened to max items (2 limit check usually returns array_slice(..., 0, 2))
        $this->assertCount(2, Livewire::test(OrderRevocationForm::class)
            ->set('attachments', [$file1, $file2, $file3])
            ->get('attachments')
        );
    }

    public function test_admin_can_download_secure_revocation_attachment()
    {
        $admin = Admin::create([
            'id' => Str::uuid()->toString(),
            'email' => 'admin_revoke@test.de',
            'first_name' => 'Hans',
            'last_name' => 'Test',
            'password' => bcrypt('password')
        ]);

        $revocation = OrderRevocation::create([
            'name' => 'Lisa Müller',
            'email' => 'lisa@example.com',
            'order_number' => 'RX-999',
            'items' => 'Mangelware',
            'status' => 'pending'
        ]);

        $fakeFile = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');
        $path = $fakeFile->store("bestellungen/private/revocations/{$revocation->id}", 'local');
        $revocation->update(['attachments' => [$path]]);

        $filename = basename($path);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.widerruf.file', [
            'revocation' => $revocation->id,
            'fileName' => $filename
        ]));
        
        
        $response->assertStatus(200);
    }

    public function test_admin_can_reject_revocation_and_send_email()
    {
        $revocation = OrderRevocation::create([
            'name' => 'Klaus K.',
            'email' => 'klaus@example.com',
            'order_number' => 'RX-999',
            'items' => 'Test Item',
            'status' => 'pending'
        ]);

        Livewire::test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('rejectRevocation', $revocation->id, 'damaged');

        $revocation->refresh();
        $this->assertEquals('declined', $revocation->status);
        $this->assertEquals('damaged', $revocation->rejection_reason);
        $this->assertNotNull($revocation->customer_notified_at);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\Order\RevocationRejectedMail::class, function ($mail) {
            return $mail->hasTo('klaus@example.com');
        });
    }

    public function test_admin_can_process_revocation_and_send_success_email()
    {
        $revocation = OrderRevocation::create([
            'name' => 'Klaus K.',
            'email' => 'klaus@example.com',
            'order_number' => 'RX-999',
            'items' => 'Test Item',
            'status' => 'pending'
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('markAsProcessed', $revocation->id);

        $revocation->refresh();
        $this->assertEquals('processed', $revocation->status);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\Order\RevocationProcessedMail::class, function ($mail) {
            return $mail->hasTo('klaus@example.com');
        });
    }

    public function test_admin_can_mark_legal_check_with_product_type()
    {
        $revocation = OrderRevocation::create([
            'name' => 'Anna',
            'email' => 'anna@test.de',
            'order_number' => '12345',
            'status' => 'pending'
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('markLegalCheck', $revocation->id, 'personalized');

        $revocation->refresh();
        $this->assertNotNull($revocation->legal_check_at);
        $this->assertEquals('personalized', $revocation->product_type);
    }

    public function test_admin_can_undo_legal_check()
    {
        $revocation = OrderRevocation::create([
            'name' => 'Anna',
            'email' => 'anna@test.de',
            'order_number' => '12345',
            'status' => 'declined', // test resetting
            'legal_check_at' => now(),
            'product_type' => 'standard',
            'customer_notified_at' => now(),
            'rejection_reason' => 'expired'
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('undoLegalCheck', $revocation->id);

        $revocation->refresh();
        $this->assertNull($revocation->legal_check_at);
        $this->assertNull($revocation->product_type);
        $this->assertNull($revocation->customer_notified_at);
        $this->assertNull($revocation->rejection_reason);
        $this->assertEquals('pending', $revocation->status);
    }

    public function test_admin_can_mark_customer_notified()
    {
        $revocation = OrderRevocation::create([
            'name' => 'Bob',
            'email' => 'bob@test.de',
            'order_number' => '555',
            'status' => 'pending'
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('markCustomerNotified', $revocation->id);

        $revocation->refresh();
        $this->assertNotNull($revocation->customer_notified_at);
    }

    public function test_admin_can_undo_customer_notified()
    {
        $revocation = OrderRevocation::create([
            'name' => 'Bob',
            'email' => 'bob@test.de',
            'order_number' => '555',
            'status' => 'declined',
            'customer_notified_at' => now(),
            'rejection_reason' => 'other'
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('undoCustomerNotified', $revocation->id);

        $revocation->refresh();
        $this->assertNull($revocation->customer_notified_at);
        $this->assertNull($revocation->rejection_reason);
        $this->assertEquals('pending', $revocation->status);
    }

    public function test_admin_can_undo_processed_status()
    {
        $revocation = OrderRevocation::create([
            'name' => 'Clara',
            'email' => 'clara@test.de',
            'order_number' => '666',
            'status' => 'processed'
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('markAsPending', $revocation->id);

        $revocation->refresh();
        $this->assertEquals('pending', $revocation->status);
    }

    public function test_admin_can_delete_revocation_with_attachments()
    {
        $revocation = OrderRevocation::create([
            'name' => 'Leo',
            'email' => 'leo@test.de',
            'order_number' => '777',
            'status' => 'pending'
        ]);

        $fakeFile = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');
        $path = $fakeFile->store("bestellungen/private/revocations/{$revocation->id}", 'local');
        $revocation->update(['attachments' => [$path]]);

        Storage::disk('local')->assertExists($path);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderRevocations::class)
            ->call('deleteRevocation', $revocation->id);

        $this->assertDatabaseMissing('order_revocations', ['id' => $revocation->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
