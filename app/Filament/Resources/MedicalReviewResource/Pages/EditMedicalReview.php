<?php

declare(strict_types=1);

namespace App\Filament\Resources\MedicalReviewResource\Pages;

use App\Filament\Resources\MedicalReviewResource;
use App\Models\MedicalReview;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * EditMedicalReview Page
 *
 * The primary doctor workspace for completing a medical review.
 * After filling in the clinical conclusion and final_status,
 * the doctor clicks "Approve & Sign" to finalize.
 *
 * The "Approve & Sign" header action calls `MedicalReview::approve()`
 * which is a clean domain method — keeping the Filament layer thin.
 */
class EditMedicalReview extends EditRecord
{
    protected static string $resource = MedicalReviewResource::class;

    protected function getHeaderActions(): array
    {
        /** @var MedicalReview $record */
        $record = $this->getRecord();

        return [
            Actions\ViewAction::make(),

            /**
             * "Approve & Sign" — the primary CTA for doctors.
             * Only visible when:
             *  - Review is in AI_DRAFT_READY state
             *  - Doctor has filled in a conclusion
             *  - A final_status has been selected
             *
             * After approval, the record becomes read-only (REVIEWED status).
             */
            Actions\Action::make('approve_and_sign')
                ->label('Approve & Sign')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Finalize Medical Review')
                ->modalDescription(
                    'By clicking "Confirm", you are digitally signing this medical review. ' .
                    'This action is recorded in the audit log and cannot be undone without admin intervention.'
                )
                ->modalSubmitActionLabel('Yes, Approve & Sign')
                ->visible(fn () =>
                    $record->review_status !== 'REVIEWED'
                )
                ->action(function () use ($record) {
                    // Pull the latest form data from the Livewire state
                    $formData = $this->form->getState();

                    if (empty($formData['doctor_conclusion'])) {
                        Notification::make()
                            ->title('Clinical Conclusion is required before approval.')
                            ->danger()
                            ->send();
                        return;
                    }

                    if (empty($formData['final_status'])) {
                        Notification::make()
                            ->title('Please select a Final Fitness Status before approving.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Save current form state first
                    $record->update([
                        'doctor_conclusion' => $formData['doctor_conclusion'],
                        'doctor_notes'      => $formData['doctor_notes'] ?? null,
                        'final_status'      => $formData['final_status'],
                    ]);

                    // Then execute the domain approve() method
                    $record->approve(auth()->id(), $formData['final_status']);

                    Notification::make()
                        ->title('Medical review approved and signed.')
                        ->body("Patient status: {$formData['final_status']}")
                        ->success()
                        ->send();

                    // Redirect to view page (read-only after approval)
                    $this->redirect(
                        MedicalReviewResource::getUrl('view', ['record' => $record])
                    );
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasRole('super_admin')),
        ];
    }

    /**
     * After a successful save (without the Approve action),
     * redirect back to the edit page to continue reviewing.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * Mutate data before filling the form — pre-populate final_status
     * with the AI recommendation if the doctor hasn't set one yet.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['final_status']) && !empty($data['ai_recommended_status'])) {
            $data['final_status'] = $data['ai_recommended_status'];
        }

        return $data;
    }
}
