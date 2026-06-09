<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use App\Models\User;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactInquiryController extends Controller
{
    public function __construct(private readonly SensitiveEventRecorder $sensitiveEvents)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(ContactInquiry::statuses())],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $status = isset($filters['status']) ? (string) $filters['status'] : '';
        $search = trim((string) ($filters['q'] ?? ''));

        $inquiries = ContactInquiry::query()
            ->with('assignedTo.profile')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $pattern = '%'.Str::lower($search).'%';
                $query->where(function ($subQuery) use ($pattern): void {
                    $subQuery
                        ->whereRaw('LOWER(full_name) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(subject) LIKE ?', [$pattern]);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('platform.contact-inquiries.index', [
            'inquiries' => $inquiries,
            'filters' => [
                'status' => $status,
                'q' => $search,
            ],
            'statuses' => ContactInquiry::statuses(),
        ]);
    }

    public function show(ContactInquiry $contactInquiry): View
    {
        $contactInquiry->loadMissing('assignedTo.profile');

        $superadmins = User::query()
            ->where('platform_role', User::PLATFORM_SUPERADMIN)
            ->where('active', true)
            ->with('profile')
            ->orderBy('email')
            ->get(['id', 'email']);

        return view('platform.contact-inquiries.show', [
            'inquiry' => $contactInquiry,
            'statuses' => ContactInquiry::statuses(),
            'superadmins' => $superadmins,
        ]);
    }

    public function update(Request $request, ContactInquiry $contactInquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(ContactInquiry::statuses())],
            'assigned_to_user_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('platform_role', User::PLATFORM_SUPERADMIN)->where('active', true)
                ),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $contactInquiry->forceFill([
            'status' => (string) $validated['status'],
            'assigned_to_user_id' => isset($validated['assigned_to_user_id']) && $validated['assigned_to_user_id'] !== ''
                ? (string) $validated['assigned_to_user_id']
                : null,
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
        ])->save();

        $this->sensitiveEvents->record(
            actionType: 'contact_inquiry.updated',
            entityType: 'contact_inquiry',
            entityId: (string) $contactInquiry->id,
            metadata: [
                'status' => (string) $contactInquiry->status,
                'assigned_to_user_id' => (string) ($contactInquiry->assigned_to_user_id ?? ''),
            ],
            actor: $request->user()
        );

        return redirect()
            ->route('superadmin.contact-inquiries.show', ['contactInquiry' => $contactInquiry->id])
            ->with('status', __('contact_inquiries.flash.updated'));
    }
}

