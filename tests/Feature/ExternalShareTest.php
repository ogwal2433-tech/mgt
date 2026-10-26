<?php

use App\Mail\ExternalFeedbackMail;
use App\Mail\ExternalShareMail;
use App\Mail\ExternalSignedMail;
use App\Models\ExternalRequisition;
use App\Models\ExternalRequisitionItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

function makeExternalForm(array $overrides = []): ExternalRequisition
{
    $lead = makeUser('technical_lead');

    $requisition = ExternalRequisition::create(array_merge([
        'requisition_number' => \App\Support\Sequence::next('external_requisition', 'EXT'),
        'date' => now()->toDateString(),
        'external_party_name' => 'DevWorks Ltd',
        'external_contact' => 'Jane Developer',
        'external_email' => 'jane@devworks.test',
        'prepared_by_user_id' => $lead->id,
        'purpose' => 'Development milestone payment',
        'currency' => 'UGX',
        'total_amount' => '2000000.00',
        'mode_of_payment' => 'bank_transfer',
        'status' => 'draft',
    ], $overrides));

    ExternalRequisitionItem::create([
        'external_requisition_id' => $requisition->id,
        'description' => 'Milestone 1 — frontend',
        'quantity' => 1,
        'unit_price' => '2000000.00',
        'total_price' => '2000000.00',
    ]);

    return $requisition->fresh();
}

it('sends the form to the external party with a tokenized link', function () {
    Mail::fake();

    $lead = makeUser('technical_lead');
    $form = makeExternalForm();

    $this->actingAs($lead)->post("/external-requisitions/{$form->id}/send", [
        'email' => 'jane@devworks.test',
        'contact_name' => 'Jane Developer',
    ])->assertSessionHas('success');

    $form->refresh();
    expect($form->share_token)->not->toBeNull();
    expect($form->status)->toBe('awaiting_external');
    expect($form->isTokenValid())->toBeTrue();

    Mail::assertSent(ExternalShareMail::class, fn ($mail) => $mail->hasTo('jane@devworks.test')
        && str_contains($mail->link, '/share/external-requisitions/'));
});

it('lets the external party copy a share link on demand', function () {
    $lead = makeUser('technical_lead');
    $form = makeExternalForm();

    $response = $this->actingAs($lead)->postJson("/external-requisitions/{$form->id}/share-link");
    $response->assertOk()->assertJsonStructure(['url', 'expires_at']);

    expect($response->json('url'))->toContain('/share/external-requisitions/');
    expect($form->fresh()->share_token)->not->toBeNull();
});

it('lets the external party fill sign and submit without an account', function () {
    Mail::fake();

    $lead = makeUser('technical_lead');
    $form = makeExternalForm();
    $this->actingAs($lead)->post("/external-requisitions/{$form->id}/send", ['email' => 'jane@devworks.test']);
    $form->refresh();

    // The public page renders for a guest with the token.
    $this->get("/share/external-requisitions/{$form->share_token}")->assertOk();

    $signature = 'data:image/png;base64,' . base64_encode(str_repeat("\x89PNG", 250));

    $this->post("/share/external-requisitions/{$form->share_token}", [
        'requested_by_name' => 'Jane Developer',
        'payee_name' => 'Jane Developer',
        'mode_of_payment' => 'mobile_money',
        'purpose' => 'Development milestone payment',
        'items' => [['id' => $form->items()->first()->id, 'actual_cost' => '1750000.00']],
        'signature' => $signature,
    ])->assertOk();

    $form->refresh();
    expect($form->status)->toBe('submitted');
    expect($form->signed_by_name)->toBe('Jane Developer');
    expect($form->mode_of_payment)->toBe('mobile_money');
    expect((string) $form->actual_total)->toBe('1750000.00');
    expect((string) $form->unspent_funds)->toBe('250000.00');
    expect($form->signature_document_id)->not->toBeNull();

    // A signed copy goes back to the external party immediately (their own submission).
    Mail::assertSent(ExternalSignedMail::class, fn ($mail) => $mail->hasTo('jane@devworks.test'));
});

it('sends approval feedback to the external party only after lead hr and md have all approved', function () {
    Mail::fake();

    $lead = makeUser('technical_lead');
    $hr = makeUser('hr');
    $md = makeUser('managing_director');
    $form = makeExternalForm();

    $this->actingAs($lead)->post("/external-requisitions/{$form->id}/send", ['email' => 'jane@devworks.test']);
    $token = $form->fresh()->share_token;

    $this->post("/share/external-requisitions/{$token}", [
        'requested_by_name' => 'Jane Developer',
        'payee_name' => 'Jane Developer',
        'mode_of_payment' => 'bank_transfer',
        'purpose' => 'Milestone payment',
        'items' => [['id' => $form->items()->first()->id, 'actual_cost' => '2000000.00']],
        'signature' => 'data:image/png;base64,' . base64_encode(str_repeat("\x89PNG", 250)),
    ]);

    // Lead confirms all → NOT yet sent to external.
    $this->actingAs($lead)->post("/external-requisitions/{$form->id}/approve", ['comments' => 'All items confirmed'])
        ->assertSessionHas('success');
    Mail::assertNotSent(ExternalFeedbackMail::class);

    // HR approves → NOT yet sent.
    $this->actingAs($hr)->post("/external-requisitions/{$form->id}/approve", ['comments' => 'HR review complete'])
        ->assertSessionHas('success');
    Mail::assertNotSent(ExternalFeedbackMail::class);

    // MD approves → NOW the feedback goes to the external party.
    $this->actingAs($md)->post("/external-requisitions/{$form->id}/approve", ['comments' => 'Approved for payment'])
        ->assertSessionHas('success');

    Mail::assertSent(ExternalFeedbackMail::class, fn ($mail) => $mail->hasTo('jane@devworks.test'));

    $form->refresh();
    expect($form->status)->toBe('approved');
    expect($form->is_approved)->toBeTrue();
    expect($form->feedback_sent_at)->not->toBeNull();
    expect($form->approvals()->count())->toBe(3);
});

it('notifies the external party immediately when the form is rejected', function () {
    Mail::fake();

    $lead = makeUser('technical_lead');
    $form = makeExternalForm();
    $this->actingAs($lead)->post("/external-requisitions/{$form->id}/send", ['email' => 'jane@devworks.test']);

    $this->post("/share/external-requisitions/{$form->fresh()->share_token}", [
        'requested_by_name' => 'Jane Developer',
        'payee_name' => 'Jane Developer',
        'mode_of_payment' => 'cash',
        'purpose' => 'Milestone payment',
        'items' => [['id' => $form->items()->first()->id, 'actual_cost' => '1000.00']],
        'signature' => 'data:image/png;base64,' . base64_encode(str_repeat("\x89PNG", 250)),
    ]);

    // Rejection without a reason is refused.
    $this->actingAs($lead)->post("/external-requisitions/{$form->id}/reject", ['comments' => ''])
        ->assertSessionHasErrors('comments');

    $this->actingAs($lead)->post("/external-requisitions/{$form->id}/reject", ['comments' => 'Costs not justified'])
        ->assertSessionHas('success');

    Mail::assertSent(ExternalFeedbackMail::class);
    expect($form->fresh()->status)->toBe('rejected');
});

it('rejects expired or unknown share tokens', function () {
    $form = makeExternalForm(['share_token' => 'expired-token', 'token_expires_at' => now()->subDay()]);

    $this->get('/share/external-requisitions/expired-token')->assertNotFound();
    $this->get('/share/external-requisitions/unknown-token')->assertNotFound();
});

it('renders the requisition form with company details and mode of payment', function () {
    $lead = makeUser('technical_lead');
    $form = makeExternalForm(['requested_by_name' => 'Jane Developer', 'payee_name' => 'Jane Developer']);

    $html = view('pdf.external-requisition', [
        'requisition' => $form->load(['items', 'preparedBy.employee', 'party', 'project', 'signature', 'approvals.user']),
        'company' => \App\Support\Settings::company(),
    ])->render();

    expect($html)->toContain('Biiteek')
        ->toContain('Requisition / Accountability Form')
        ->toContain('Bank Transfer')
        ->toContain($form->requisition_number)
        ->toContain('Jane Developer');
});
