<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FaqItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqItemController extends Controller
{
    use ResolvesManagedCompany;

    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, $actor->isSuperadmin());

        if ($actor->isSuperadmin() && $companyId === null) {
            return view('admin.master-data.faqs', [
                'faqItems' => collect(),
                'categories' => collect(),
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'selectedCompanyId' => null,
                'selectedCategory' => null,
                'searchTerm' => '',
                'requiresCompanySelection' => true,
            ]);
        }

        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $searchTerm = (string) $request->query('q', '');
        $selectedCategory = (string) $request->query('category', '');

        $faqItems = FaqItem::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($selectedCategory !== '', fn ($q) => $q->where('category', $selectedCategory))
            ->when($searchTerm !== '', fn ($q) => $q->where(function ($inner) use ($searchTerm): void {
                $inner->where('question', 'like', '%'.$searchTerm.'%')
                    ->orWhere('answer', 'like', '%'.$searchTerm.'%');
            }))
            ->orderBy('category')
            ->orderBy('question')
            ->paginate(20)
            ->withQueryString();

        $categories = FaqItem::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('admin.master-data.faqs', [
            'faqItems' => $faqItems,
            'categories' => $categories,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedCompanyId' => $companyId,
            'selectedCategory' => $selectedCategory !== '' ? $selectedCategory : null,
            'searchTerm' => $searchTerm,
            'requiresCompanySelection' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 422);

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:120'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        FaqItem::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'category' => $validated['category'],
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'is_published' => (bool) ($validated['is_published'] ?? false),
        ]);

        return back()->with('status', __('master.faqs.created'));
    }

    public function update(Request $request, FaqItem $faqItem): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $faqItem->company_id === (string) $companyId, 403);

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:120'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $faqItem->update([
            'category' => $validated['category'],
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'is_published' => (bool) ($validated['is_published'] ?? false),
        ]);

        return back()->with('status', __('master.faqs.updated'));
    }

    public function destroy(Request $request, FaqItem $faqItem): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $faqItem->company_id === (string) $companyId, 403);

        $faqItem->delete();

        return back()->with('status', __('master.faqs.deleted'));
    }
}
