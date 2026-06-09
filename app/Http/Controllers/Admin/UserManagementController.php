<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyMembership;
use App\Models\Profile;
use App\Models\User;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class UserManagementController extends Controller
{
    public function __construct(private readonly SensitiveEventRecorder $sensitiveEvents)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $activeCompanyId = (string) session('active_company_id');

        $memberships = CompanyMembership::query()
            ->where('company_id', $activeCompanyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->with(['user.profile'])
            ->latest()
            ->paginate(20);

        return view('admin.users.index', [
            'memberships' => $memberships,
            'roles' => User::roles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $activeCompanyId = (string) session('active_company_id');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'role'  => ['required', Rule::in(User::roles())],
        ]);

        $fullName = trim((string) ($validated['full_name'] ?? ''));

        $isNewUser = false;
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null) {
            $user = User::create([
                'email'             => $validated['email'],
                'password'          => bcrypt(str()->random(32)),
                'platform_role'     => User::PLATFORM_NONE,
                'active'            => true,
                'email_verified_at' => null,
            ]);

            Profile::create([
                'user_id'    => $user->id,
                'full_name'  => $fullName,
                'locale'     => app()->getLocale(),
                'avatar_url' => null,
            ]);

            $isNewUser = true;
        } elseif ($fullName !== '') {
            $profile = Profile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => '',
                    'locale' => app()->getLocale(),
                    'avatar_url' => null,
                ]
            );

            if ((string) $profile->full_name !== $fullName) {
                $profile->update(['full_name' => $fullName]);
            }
        }

        $alreadyMember = CompanyMembership::query()
            ->where('company_id', $activeCompanyId)
            ->where('user_id', $user->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->exists();

        if ($alreadyMember) {
            return back()->withErrors(['email' => __('admin.users.already_member')])->withInput();
        }

        CompanyMembership::create([
            'company_id'       => $activeCompanyId,
            'user_id'          => $user->id,
            'company_role'     => $validated['role'],
            'membership_status'=> CompanyMembership::STATUS_ACTIVE,
        ]);

        $warning = null;

        if ($isNewUser) {
            try {
                $status = Password::broker()->sendResetLink(['email' => $user->email]);

                if ($status !== Password::RESET_LINK_SENT) {
                    $warning = __('admin.users.password_setup_not_sent');

                    Log::warning('Password setup email was not sent for newly created user.', [
                        'company_id' => $activeCompanyId,
                        'target_user_id' => $user->id,
                        'target_email' => $user->email,
                        'broker_status' => $status,
                    ]);
                }
            } catch (Throwable $exception) {
                $warning = __('admin.users.password_setup_mail_unavailable');

                Log::warning('Failed to send password setup email for newly created user.', [
                    'company_id' => $activeCompanyId,
                    'target_user_id' => $user->id,
                    'target_email' => $user->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $redirect = back()->with('status', __('admin.users.user_added'));

        if ($warning !== null) {
            return $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->authorize('updateRole', $user);

        $activeCompanyId = (string) session('active_company_id');

        $membership = CompanyMembership::query()
            ->where('company_id', $activeCompanyId)
            ->where('user_id', $user->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->firstOrFail();

        $validated = $request->validate([
            'role' => ['required', Rule::in(User::roles())],
            'full_name' => ['nullable', 'string', 'max:255'],
            'confirm_role_change' => ['sometimes', 'accepted'],
        ]);

        $oldRole = $membership->company_role;
        $newRole = (string) $validated['role'];
        $roleChanged = $oldRole !== $newRole;
        $newFullName = trim((string) ($validated['full_name'] ?? ''));
        $profile = Profile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => '',
                'locale' => app()->getLocale(),
                'avatar_url' => null,
            ]
        );
        $oldFullName = (string) ($profile->full_name ?? '');
        $nameChanged = $newFullName !== $oldFullName;

        if (! $roleChanged && ! $nameChanged) {
            return back()->with('status', __('admin.users.no_changes'));
        }

        if ($roleChanged && ! $request->boolean('confirm_role_change')) {
            return back()
                ->withErrors(['confirm_role_change' => __('admin.users.confirm_role_change_required')])
                ->withInput();
        }

        if ($roleChanged) {
            $membership->update(['company_role' => $newRole]);
        }

        if ($nameChanged) {
            $profile->update(['full_name' => $newFullName]);
            $this->sensitiveEvents->record(
                actionType: 'user.name_updated',
                entityType: 'user',
                entityId: (string) $user->id,
                metadata: [
                    'old_full_name' => $oldFullName,
                    'new_full_name' => $newFullName,
                ],
                actor: $request->user()
            );
        }

        if ($roleChanged) {
            $this->sensitiveEvents->roleChanged(
                userId: $user->id,
                metadata: [
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                ],
                actor: $request->user()
            );
        }

        return back()->with('status', __('admin.users.user_updated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $activeCompanyId = (string) session('active_company_id');
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $membership = CompanyMembership::query()
            ->where('company_id', $activeCompanyId)
            ->where('user_id', $user->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->first();

        if (! $membership instanceof CompanyMembership) {
            return back()->with('warning', __('admin.users.remove_not_available'));
        }

        if ((string) $actor->id === (string) $user->id) {
            return back()->with('warning', __('admin.users.cannot_remove_self'));
        }

        if ($membership->company_role === CompanyMembership::ROLE_COMPANY_ADMIN) {
            $activeAdminCount = CompanyMembership::query()
                ->where('company_id', $activeCompanyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->where('company_role', CompanyMembership::ROLE_COMPANY_ADMIN)
                ->count();

            if ($activeAdminCount <= 1) {
                return back()->with('warning', __('admin.users.cannot_remove_last_admin'));
            }
        }

        $membership->update([
            'membership_status' => CompanyMembership::STATUS_REVOKED,
        ]);

        $this->sensitiveEvents->record(
            actionType: 'user.membership_revoked',
            entityType: 'user',
            entityId: (string) $user->id,
            metadata: [
                'company_id' => $activeCompanyId,
                'company_role' => $membership->company_role,
            ],
            actor: $actor
        );

        return back()->with('status', __('admin.users.user_removed'));
    }
}
