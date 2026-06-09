<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\RecruitmentNeed;
use App\Models\Company;
use App\Models\Department;
use App\Models\DatasetVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RecruitmentNeedController extends Controller
{
    use ResolvesManagedCompany;

    private function resolveCompanyContext(Request $request): array
    {
        $user = $request->user();
        $companies = collect();

        if ($user instanceof User && $user->isSuperadmin()) {
            $companies = Company::query()
                ->where('status', Company::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $companyId = $this->managedCompanyId($request, false);

        if ($companyId !== null) {
            $activeCompanyExists = Company::query()
                ->where('id', $companyId)
                ->where('status', Company::STATUS_ACTIVE)
                ->exists();

            if (! $activeCompanyExists) {
                $companyId = null;
            }
        }

        return [$companyId, $companies];
    }

    public function index(Request $request)
    {
        [$companyId, $companies] = $this->resolveCompanyContext($request);

        if ($companyId === null) {
            return view('admin.recruitment-needs.index', [
                'requiresCompanySelection' => true,
                'companies' => $companies,
                'needs' => collect(),
            ]);
        }

        $needs = RecruitmentNeed::where('company_id', $companyId)
            ->with(['company', 'department'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        $versions = DatasetVersion::where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->get();

        $requiresCompanySelection = false;
        return view('admin.recruitment-needs.index', compact('needs', 'requiresCompanySelection', 'versions'));
    }

    public function updateInline(Request $request, $id)
    {
        $need = RecruitmentNeed::findOrFail($id);
        
        $field = $request->input('field');
        $value = $request->input('value');
        
        if (in_array($field, $need->getFillable())) {
            $need->$field = $value;
            $need->save();
            return response()->json(['success' => true]);
        }
        
        return response()->json(['success' => false], 400);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();
        
        $data = array_map(function($v){return str_getcsv($v, ";");}, file($path));
        $header = array_shift($data);
        
        [$companyId, $companies] = $this->resolveCompanyContext($request);
        $count = 0;

        foreach ($data as $row) {
            if (count($row) < 5) continue; // skip empty or invalid rows
            
            // Map according to expected CSV from user
            // Année;Société;Direction;Site;Intitulé du poste du départ;Date de Départ;Motif;Intitulé poste nouvelle recrue;Budget;Statut recrutement;Contrat;Type;Posting interne;Sourcing externe;Outils sourcing;Nom recrue;M/F;Date prév. démarrage;Statut (BC/WC)
            
            // Assuming Direction matches a department name
            $deptName = $row[2] ?? '';
            $department = Department::firstOrCreate(
                ['name' => $deptName, 'company_id' => $companyId],
                ['name' => $deptName]
            );
            
            $statusStr = strtolower(trim($row[9] ?? ''));
            $status = 'draft';
            if (str_contains($statusStr, 'clôturé')) $status = 'closed';
            elseif (str_contains($statusStr, 'en cours')) $status = 'in_progress';
            elseif (str_contains($statusStr, 'pas encore lancé')) $status = 'draft';
            
            RecruitmentNeed::create([
                'company_id' => $companyId,
                'department_id' => $department->id,
                'year' => (int)($row[0] ?? date('Y')),
                'site' => $row[3] ?? null,
                'departing_position_title' => $row[4] ?? null,
                'departure_date' => !empty($row[5]) ? Carbon::parse($row[5]) : null,
                'departure_reason' => $row[6] ?? null,
                'new_recruit_position_title' => $row[7] ?? null,
                'budget_approved' => (strtolower(trim($row[8] ?? '')) === 'oui'),
                'status' => $status,
                'contract_type' => $row[10] ?? null,
                'recruitment_type' => $row[11] ?? null,
                'internal_posting' => (strtolower(trim($row[12] ?? '')) === 'oui'),
                'external_sourcing' => (strtolower(trim($row[13] ?? '')) === 'oui'),
                'sourcing_tools' => $row[14] ?? null,
                'new_recruit_name' => $row[15] ?? null,
                'gender' => $row[16] ?? null,
                'expected_start_date' => !empty($row[17]) ? Carbon::parse($row[17]) : null,
                'worker_type' => $row[18] ?? null, // BC or WC
            ]);
            $count++;
        }

        DatasetVersion::create([
            'version_name' => 'Dataset v' . (DatasetVersion::where('company_id', $companyId)->count() + 1) . ' · import CSV',
            'row_count' => $count,
            'company_id' => $companyId,
            'user_id' => auth()->id(),
        ]);

        return back()->with('success', "$count lignes importées avec succès.");
    }

    public function generateDummyData(Request $request)
    {
        [$companyId, $companies] = $this->resolveCompanyContext($request);
        if ($companyId === null) {
            return redirect()->back()->with('error', 'Veuillez sélectionner une entreprise.');
        }

        $company = Company::find($companyId);
        $department = Department::firstOrCreate(['company_id' => $companyId, 'name' => 'BPE']);

        $dummyData = [
            [
                'year' => 2025,
                'site' => 'Lasaefa',
                'departing_position_title' => 'Ingénieur Procédés',
                'departure_date' => '2025-06-30',
                'departure_reason' => 'Départ négocié',
                'new_recruit_position_title' => 'Technicien Laboratoire',
                'budget_approved' => true,
                'status' => 'pas encore lancé',
                'contract_type' => 'CDI',
                'recruitment_type' => 'Remplacement',
                'internal_posting' => false,
                'external_sourcing' => true,
                'sourcing_tools' => 'LinkedIn, Job Boards',
                'new_recruit_name' => 'Amine TAAJJ',
                'gender' => 'M',
                'expected_start_date' => '2026-01-11',
            ],
            [
                'year' => 2025,
                'site' => 'Lasaefa',
                'departing_position_title' => null,
                'departure_date' => null,
                'departure_reason' => null,
                'new_recruit_position_title' => 'Chef de projet IT',
                'budget_approved' => true,
                'status' => 'pas encore lancé',
                'contract_type' => 'CDI',
                'recruitment_type' => 'Création de poste',
                'internal_posting' => true,
                'external_sourcing' => true,
                'sourcing_tools' => 'Job Boards',
                'new_recruit_name' => null,
                'gender' => null,
                'expected_start_date' => '2025-09-01',
            ],
            [
                'year' => 2025,
                'site' => 'Casablanca',
                'departing_position_title' => 'Commercial',
                'departure_date' => '2025-04-15',
                'departure_reason' => 'Démission',
                'new_recruit_position_title' => 'Responsable Commercial',
                'budget_approved' => false,
                'status' => 'pas encore lancé',
                'contract_type' => 'CDI',
                'recruitment_type' => 'Remplacement',
                'internal_posting' => false,
                'external_sourcing' => true,
                'sourcing_tools' => 'LinkedIn',
                'new_recruit_name' => null,
                'gender' => null,
                'expected_start_date' => '2025-05-15',
            ]
        ];

        foreach ($dummyData as $data) {
            $data['company_id'] = $companyId;
            $data['department_id'] = $department->id;
            RecruitmentNeed::create($data);
        }

        return redirect()->back()->with('status', 'Données de simulation générées avec succès.');
    }
}
