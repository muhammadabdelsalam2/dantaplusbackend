<?php

namespace App\Services\Lab\Clinic;

use App\Http\Resources\Lab\Clinic\ClinicPartnershipResource;
use App\Mail\LabClinicInvitationMail;
use App\Models\DentalLab;
use App\Models\LabClinicInvitation;
use App\Repositories\Lab\Clinic\ClinicRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClinicInviteService
{
    public function __construct(private ClinicRepositoryInterface $repository)
    {
    }

    public function invite(string $email): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $email = strtolower(trim($email));
        $lab = DentalLab::query()->find($labId);
        $clinic = $this->repository->findInternalClinicByEmail($email);

        if ($clinic) {
            $emailSent = $this->sendInvitationEmail($email, $lab?->name ?? 'Denta+ Lab', null);
            $partnership = $this->repository->findPartnership($labId, $clinic->id);
            if ($partnership) {
                if (($partnership->status?->value ?? $partnership->status) !== 'Active') {
                    $partnership = $this->repository->updatePartnership($partnership, [
                        'status' => 'Pending',
                        'invited_by' => auth()->id(),
                    ]);
                }
            } else {
                $this->repository->createPartnership([
                    'lab_id' => $labId,
                    'clinic_id' => $clinic->id,
                    'status' => 'Pending',
                    'invited_by' => auth()->id(),
                ]);
                $partnership = $this->repository->findPartnership($labId, $clinic->id);
            }

            return ServiceResult::success([
                'type' => 'existing_clinic',
                'email' => $email,
                'email_sent' => $emailSent,
                'partnership' => (new ClinicPartnershipResource($partnership))->resolve(),
            ], 'Partnership invitation sent!', 201);
        }

        $invitation = LabClinicInvitation::query()->updateOrCreate([
            'lab_id' => $labId,
            'email' => $email,
        ], [
            'status' => 'Pending',
            'token' => (string) Str::uuid(),
            'invited_by' => auth()->id(),
        ]);

        $emailSent = $this->sendInvitationEmail($email, $lab?->name ?? 'Denta+ Lab', $invitation->token);

        return ServiceResult::success([
            'type' => 'external_invitation',
            'email' => $email,
            'email_sent' => $emailSent,
            'invitation' => [
                'id' => $invitation->id,
                'status' => $invitation->status,
                'invite_url' => $this->inviteUrl($email, $invitation->token),
            ],
        ], 'Clinic invitation sent!', 201);
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }

    private function sendInvitationEmail(string $email, string $labName, ?string $token): bool
    {
        try {
            Mail::to($email)->send(new LabClinicInvitationMail(
                $labName,
                $this->inviteUrl($email, $token),
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send lab clinic invitation email.', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function inviteUrl(string $email, ?string $token): string
    {
        return url('/register?email=' . urlencode($email) . ($token ? '&invitation=' . urlencode($token) : ''));
    }
}
