<?php

namespace App\Domains\Tutoring\Services;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Domains\Tutoring\Models\VerificationDocument;
use App\Models\User;
use App\Notifications\TutorVerificationReviewed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Tutor verification (SRS: TUT-01 – TUT-07).
 *
 * Documents live on the private disk and are only ever served through a
 * short-lived, admin-authorised route — they contain ID numbers and police
 * clearance certificates.
 */
class TutorVerificationService
{
    public const REQUIRED_DOCUMENTS = [
        'id' => 'Identity document',
        'qualification' => 'Highest qualification certificate',
        'police_clearance' => 'Police clearance certificate',
    ];

    public function storeDocument(TutorProfile $profile, string $type, UploadedFile $file): VerificationDocument
    {
        $path = $file->store("verification/{$profile->id}", 'local');

        // Replace any previous document of the same type.
        $profile->documents()->where('document_type', $type)->get()
            ->each(function (VerificationDocument $existing) {
                Storage::disk('local')->delete($existing->path);
                $existing->delete();
            });

        $document = $profile->documents()->create([
            'document_type' => $type,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        audit('tutor.document_uploaded', $profile, ['type' => $type]);

        return $document;
    }

    public function missingDocuments(TutorProfile $profile): array
    {
        $uploaded = $profile->documents()->pluck('document_type')->all();

        return array_diff_key(self::REQUIRED_DOCUMENTS, array_flip($uploaded));
    }

    public function canSubmit(TutorProfile $profile): bool
    {
        return $this->missingDocuments($profile) === []
            && $profile->subjects()->exists()
            && filled($profile->highest_qualification);
    }

    public function approve(TutorProfile $profile, User $reviewer): void
    {
        $profile->update([
            'verification_status' => TutorProfile::STATUS_APPROVED,
            'verification_notes' => null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $profile->user->notify(new TutorVerificationReviewed(approved: true));

        audit('tutor.approved', $profile, ['reviewer_id' => $reviewer->id]);
    }

    public function reject(TutorProfile $profile, User $reviewer, string $reason): void
    {
        $profile->update([
            'verification_status' => TutorProfile::STATUS_REJECTED,
            'verification_notes' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $profile->user->notify(new TutorVerificationReviewed(approved: false, reason: $reason));

        audit('tutor.rejected', $profile, ['reviewer_id' => $reviewer->id, 'reason' => $reason]);
    }

    /** TUT-05: open requests go back to matching when a tutor is suspended. */
    public function suspend(TutorProfile $profile, User $actor, string $reason): void
    {
        $profile->update(['is_available' => false]);
        $profile->user->update(['status' => 'suspended']);

        HelpRequest::where('tutor_id', $profile->user_id)
            ->where('status', HelpRequest::STATUS_ASSIGNED)
            ->update([
                'tutor_id' => null,
                'status' => HelpRequest::STATUS_ESCALATED,
                'escalated_at' => now(),
            ]);

        audit('tutor.suspended', $profile, ['actor_id' => $actor->id, 'reason' => $reason]);
    }
}
