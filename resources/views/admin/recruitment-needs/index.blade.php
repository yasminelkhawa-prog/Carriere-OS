<x-shell-layout title="TB Recrutement | {{ config('app.name') }}">
    @if($requiresCompanySelection)
        <div class="p-6">
            <x-empty-state title="TB Recrutement" message="Veuillez sélectionner une entreprise pour gérer les besoins en recrutement." />
        </div>
    @else
        @php
            $needsData = $needs->map(function($need) {
                return [
                    'id' => $need->id,
                    'year' => $need->year,
                    'company' => $need->company->name ?? '-',
                    'department' => $need->department->name ?? '-',
                    'site' => $need->site ?? '-',
                    'departing_position_title' => $need->departing_position_title ?? '-',
                    'departure_date' => $need->departure_date ? $need->departure_date->format('Y-m-d') : '-',
                    'departure_reason' => $need->departure_reason ?? '-',
                    'new_recruit_position_title' => $need->new_recruit_position_title ?? '-',
                    'budget_approved' => $need->budget_approved,
                    'status' => ucfirst($need->status),
                    'contract_type' => $need->contract_type ?? '-',
                    'worker_type' => $need->worker_type ?? '-',
                    'recruitment_type' => $need->recruitment_type ?? '-',
                    'internal_posting' => $need->internal_posting,
                    'external_sourcing' => $need->external_sourcing,
                    'sourcing_tools' => $need->sourcing_tools ?? '-',
                    'new_recruit_name' => $need->new_recruit_name ?? '-',
                    'gender' => $need->gender ?? '-',
                    'expected_start_date' => $need->expected_start_date ? $need->expected_start_date->format('Y-m-d') : '-',
                ];
            })->values();
        @endphp

        <div class="space-y-6 p-6" x-data="recruitmentDataTable(@js($needsData))">

            <!-- Versions archivées -->
            @if($versions && $versions->count() > 0)
            <div class="flex flex-col overflow-hidden rounded-2xl border border-white/80 bg-white/70 shadow-[0_10px_30px_-15px_rgba(30,41,59,0.2)] backdrop-blur-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Versions archivées</h2>
                        <p class="text-xs text-slate-500">Chaque import crée automatiquement une version restaurable du dataset</p>
                    </div>
                </div>
                <div class="space-y-3">
                    @foreach($versions as $version)
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                        <div>
                            <h3 class="font-semibold text-slate-800">{{ $version->version_name }}</h3>
                            <p class="text-xs text-slate-500">{{ $version->row_count }} lignes · {{ $version->created_at->format('d/m/Y H:i:s') }}</p>
                        </div>
                        <button class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-weightless hover:bg-primary-700">
                            Restaurer
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Main Data Table Card -->
            <div class="flex flex-col overflow-hidden rounded-2xl border border-white/80 bg-white/70 shadow-[0_10px_30px_-15px_rgba(30,41,59,0.2)] backdrop-blur-xl">
                <!-- Card Header -->
                <div class="border-b border-slate-200/60 px-6 py-5">
                    <div class="flex flex-col lg:flex-row items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">Détail Dynamique & Édition des Données</h2>
                            <p class="mt-1 text-xs text-slate-500">Adapté automatiquement aux colonnes importées • double-cliquer pour modifier • tri, recherche, suppression, pagination, export CSV</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Import Form -->
                            <form action="{{ route('admin.recruitment-needs.import') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                                @csrf
                                <input type="file" name="csv_file" accept=".csv" class="block w-full text-xs text-slate-500 file:mr-4 file:rounded-full file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100" required>
                                <button type="submit" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Importer CSV</button>
                            </form>
                        </div>
                    </div>

                    <!-- Toolbar -->
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
                        <!-- Search -->
                        <div class="relative w-full max-w-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="size-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                            <input x-model="search" type="text" class="block w-full rounded-xl border-slate-200 bg-slate-50/50 py-2 pl-10 pr-3 text-sm placeholder:text-slate-400 focus:border-primary-500 focus:ring-primary-500" placeholder="Rechercher dans toutes les colonnes...">
                        </div>

                        <!-- Actions & Badges -->
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                <span x-text="filteredRows.length"></span> / <span x-text="rows.length"></span> lignes · 19 colonnes
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                Double-cliquer pour modifier
                            </span>
                            <button @click="exportCSV" class="inline-flex items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                CSV
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table Wrapper -->
                <div class="overflow-auto" style="max-height: calc(100vh - 350px);">
                    <table class="min-w-full divide-y divide-slate-200/60 text-left text-sm whitespace-nowrap">
                        <thead class="sticky top-0 z-10 bg-slate-50/95 text-xs font-semibold text-slate-500 backdrop-blur-sm">
                            <tr>
                                <template x-for="col in columns" :key="col.key">
                                    <th class="cursor-pointer px-4 py-3 hover:bg-slate-100/50" @click="sortBy(col.key)">
                                        <div class="flex items-center gap-1">
                                            <span x-text="col.label"></span>
                                            <span class="text-[10px] text-slate-400" x-show="sortCol !== col.key">↑↓</span>
                                            <span class="text-[10px] text-primary-600" x-show="sortCol === col.key && sortAsc">↑</span>
                                            <span class="text-[10px] text-primary-600" x-show="sortCol === col.key && !sortAsc">↓</span>
                                        </div>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-for="row in filteredRows" :key="row.id">
                                <tr class="transition-colors hover:bg-slate-50/80">
                                    <template x-for="col in columns" :key="col.key">
                                        <td class="px-4 py-3" @dblclick="editCell(row, col.key)">
                                            <div x-show="editingCell !== (row.id + '-' + col.key)">
                                                <!-- Special badges -->
                                                <template x-if="col.key === 'budget_approved'">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="row[col.key] ? 'bg-success-100 text-success-800' : 'bg-slate-100 text-slate-800'" x-text="row[col.key] ? 'Oui' : 'Non'"></span>
                                                </template>
                                                <template x-if="col.key === 'status'">
                                                    <span class="inline-flex items-center rounded-full bg-warning-100 px-2 py-0.5 text-[11px] font-medium text-warning-800" x-text="row[col.key]"></span>
                                                </template>
                                                <template x-if="col.key === 'internal_posting' || col.key === 'external_sourcing'">
                                                    <span x-text="row[col.key] ? 'Oui' : 'Non'"></span>
                                                </template>
                                                <!-- Default text -->
                                                <template x-if="col.key !== 'budget_approved' && col.key !== 'status' && col.key !== 'internal_posting' && col.key !== 'external_sourcing'">
                                                    <span x-text="row[col.key]"></span>
                                                </template>
                                            </div>
                                            <!-- Inline Edit Input -->
                                            <div x-show="editingCell === (row.id + '-' + col.key)">
                                                <template x-if="col.key === 'status'">
                                                    <select x-model="editValue" @blur="saveEdit(row, col.key)" @keydown.escape="cancelEdit()" class="block w-full rounded-md border-slate-300 py-1 px-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                                                        <option value="Clôturé">Clôturé</option>
                                                        <option value="En cours">En cours</option>
                                                        <option value="Pas encore lancé">Pas encore lancé</option>
                                                    </select>
                                                </template>
                                                <template x-if="col.key !== 'status'">
                                                    <input type="text" x-model="editValue" @keydown.enter="saveEdit(row, col.key)" @keydown.escape="cancelEdit()" @blur="saveEdit(row, col.key)" class="block w-full rounded-md border-slate-300 py-1 px-2 text-sm focus:border-primary-500 focus:ring-primary-500" x-ref="editInput">
                                                </template>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                            <tr x-show="filteredRows.length === 0">
                                <td colspan="19" class="px-4 py-12 text-center text-slate-500">
                                    Aucun résultat trouvé pour votre recherche.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('recruitmentDataTable', (initialData) => ({
                    rows: initialData,
                    search: '',
                    sortCol: '',
                    sortAsc: true,
                    
                    editingCell: null,
                    editValue: '',

                    columns: [
                        { key: 'year', label: 'Année' },
                        { key: 'company', label: 'Société' },
                        { key: 'department', label: 'Direction' },
                        { key: 'site', label: 'Site' },
                        { key: 'departing_position_title', label: 'Intitulé du poste du départ' },
                        { key: 'departure_date', label: 'Date de Départ' },
                        { key: 'departure_reason', label: 'Motif' },
                        { key: 'new_recruit_position_title', label: 'Poste nouvelle recrue' },
                        { key: 'budget_approved', label: 'Budget' },
                        { key: 'status', label: 'Statut recrutement' },
                        { key: 'contract_type', label: 'Contrat' },
                        { key: 'worker_type', label: 'Statut (BC/WC)' },
                        { key: 'recruitment_type', label: 'Type' },
                        { key: 'internal_posting', label: 'Posting interne' },
                        { key: 'external_sourcing', label: 'Sourcing externe' },
                        { key: 'sourcing_tools', label: 'Outils sourcing' },
                        { key: 'new_recruit_name', label: 'Nom recrue' },
                        { key: 'gender', label: 'M/F' },
                        { key: 'expected_start_date', label: 'Date prév. démarrage' },
                    ],

                    get filteredRows() {
                        let result = this.rows;
                        if (this.search) {
                            const q = this.search.toLowerCase();
                            result = result.filter(row => {
                                return Object.values(row).some(val => 
                                    String(val).toLowerCase().includes(q)
                                );
                            });
                        }
                        if (this.sortCol) {
                            result = result.slice().sort((a, b) => {
                                let valA = a[this.sortCol];
                                let valB = b[this.sortCol];
                                if (typeof valA === 'string') valA = valA.toLowerCase();
                                if (typeof valB === 'string') valB = valB.toLowerCase();
                                if (valA < valB) return this.sortAsc ? -1 : 1;
                                if (valA > valB) return this.sortAsc ? 1 : -1;
                                return 0;
                            });
                        }
                        return result;
                    },

                    sortBy(col) {
                        if (this.sortCol === col) {
                            this.sortAsc = !this.sortAsc;
                        } else {
                            this.sortCol = col;
                            this.sortAsc = true;
                        }
                    },

                    editCell(row, colKey) {
                        // Some columns are relations, might be hard to inline edit easily, but let's allow it as text for now
                        this.editingCell = row.id + '-' + colKey;
                        this.editValue = row[colKey];
                        
                        // Focus the input
                        this.$nextTick(() => {
                            const inputs = this.$root.querySelectorAll('input[type="text"]');
                            for (let input of inputs) {
                                if (input.offsetParent !== null) {
                                    input.focus();
                                    break;
                                }
                            }
                        });
                    },

                    cancelEdit() {
                        this.editingCell = null;
                        this.editValue = '';
                    },

                    saveEdit(row, colKey) {
                        if (this.editingCell !== row.id + '-' + colKey) return; // Prevent double save on blur+enter
                        
                        let newValue = this.editValue;
                        // Special bool handling
                        if (colKey === 'budget_approved' || colKey === 'internal_posting' || colKey === 'external_sourcing') {
                            newValue = newValue.toString().toLowerCase() === 'oui' || newValue === '1' || newValue.toString().toLowerCase() === 'true';
                        }
                        
                        // Update UI immediately
                        row[colKey] = newValue;
                        
                        // Send to server
                        fetch(`/admin/recruitment-needs/${row.id}/inline`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                field: colKey,
                                value: newValue
                            })
                        }).catch(err => console.error('Erreur sauvegarde', err));

                        this.editingCell = null;
                    },

                    exportCSV() {
                        if (this.filteredRows.length === 0) return;
                        let csvContent = "data:text/csv;charset=utf-8,";
                        csvContent += this.columns.map(c => '"' + c.label.replace(/"/g, '""') + '"').join(";") + "\n";
                        this.filteredRows.forEach(row => {
                            let rowValues = this.columns.map(c => {
                                let val = row[c.key];
                                if (c.key === 'budget_approved') val = val ? 'Oui' : 'Non';
                                if (c.key === 'internal_posting') val = val ? 'Oui' : 'Non';
                                if (c.key === 'external_sourcing') val = val ? 'Oui' : 'Non';
                                return '"' + String(val ?? '').replace(/"/g, '""') + '"';
                            });
                            csvContent += rowValues.join(";") + "\n";
                        });
                        const encodedUri = encodeURI(csvContent);
                        const link = document.createElement("a");
                        link.setAttribute("href", encodedUri);
                        link.setAttribute("download", "besoins_recrutement.csv");
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }
                }));
            });
        </script>
        @endpush
    @endif
</x-shell-layout>
