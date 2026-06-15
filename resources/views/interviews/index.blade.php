<x-shell-layout :title="__('interviews.title').' | '.config('app.name')">
    @if($requiresCompanySelection)
        <x-glass-card :title="__('interviews.title')" :subtitle="__('interviews.subtitle')">
            <x-empty-state :title="__('kanban.select_company_title')" :message="__('kanban.select_company_message')" />
        </x-glass-card>
    @else
        <div id="interviews-app" x-data="{
            viewMode: 'calendar',
            events: @js($calendarEvents),
            draftApplicationId: '',
            draftInterviewerId: '{{ auth()->id() }}',
            draftDuration: 60,
            draftType: 'zoom',
            draftColor: '#8b5cf6',
            draftLink: ''
        }" class="flex gap-6" style="display: flex; flex-direction: row; gap: 1.5rem; height: calc(100vh - 8rem);">
            
            <!-- Left Sidebar (Filters & Mini Calendar) -->
            <div class="flex-shrink-0 flex flex-col gap-6 overflow-y-auto custom-scrollbar pr-2 pb-2" style="width: 320px; flex-shrink: 0; overflow-y: auto;">
                
                <div class="flex items-center gap-2 mb-2">
                    <div class="h-8 w-8 rounded-lg bg-cyan-500 flex items-center justify-center text-white font-bold">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <h1 class="text-xl font-semibold text-slate-800">{{ __('interviews.title') }}</h1>
                </div>

                <!-- Mini Calendar -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <div id="mini-calendar"></div>
                </div>

                <!-- Scheduling Form -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col gap-4">
                    <h3 class="font-semibold text-slate-800">{{ __('interviews.schedule_title') ?? 'Planifier' }}</h3>
                    
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Candidat</label>
                        <select x-model="draftApplicationId" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-aura-500 focus:ring-aura-500">
                            <option value="">Sélectionner un candidat...</option>
                            @if(isset($activeApplications))
                                @foreach($activeApplications as $app)
                                    <option value="{{ $app->id }}">{{ $app->candidate?->full_name }} ({{ $app->job?->title }})</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Intervieweur</label>
                        <select x-model="draftInterviewerId" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-aura-500 focus:ring-aura-500">
                            <option value="">Sélectionner...</option>
                            @foreach($interviewers as $interviewer)
                                <option value="{{ $interviewer->id }}">{{ $interviewer->profile?->full_name ?? $interviewer->email }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Durée (min)</label>
                            <input type="number" x-model="draftDuration" step="15" min="15" max="240" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-aura-500 focus:ring-aura-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Lieu</label>
                            <select x-model="draftType" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-aura-500 focus:ring-aura-500">
                                <option value="zoom">Zoom</option>
                                <option value="in_person">Sur place</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Couleur</label>
                            <input type="color" x-model="draftColor" class="h-[38px] w-full rounded-lg border border-slate-200 bg-slate-50 p-1 cursor-pointer">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Lien de la réunion</label>
                        <input type="url" x-model="draftLink" placeholder="https://zoom.us/j/..." class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-aura-500 focus:ring-aura-500">
                    </div>

                    <!-- Draggable Element -->
                    <div id="external-events" x-show="draftApplicationId !== '' && draftInterviewerId !== ''" x-cloak>
                        <div class="fc-event flex items-center justify-center gap-2 text-white rounded-lg p-3 text-sm font-medium cursor-grab shadow-sm border hover:opacity-90 transition-opacity" :style="`background-color: ${draftColor}; border-color: ${draftColor};`" :data-color="draftColor" :data-duration-str="Math.floor(draftDuration/60).toString().padStart(2, '0') + ':' + (draftDuration%60).toString().padStart(2, '0')">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Glissez-moi sur le calendrier
                        </div>
                    </div>
                </div>


            </div>

            <!-- Main Content Area -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col overflow-hidden relative" style="flex: 1; min-width: 0;">
                <!-- Header Toolbar -->
                <div class="border-b border-slate-200 p-3 sm:p-4 flex flex-wrap gap-3 justify-between items-center bg-slate-50/50 z-10">
                    <div class="flex items-center gap-2 sm:gap-4">
                        <button type="button" onclick="window.mainCalendar.today()" class="px-3 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 shadow-sm transition-colors">
                            Aujourd'hui
                        </button>
                        <div class="flex items-center">
                            <button type="button" onclick="window.mainCalendar.prev()" class="p-1 sm:p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 rounded-full transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                </svg>
                            </button>
                            <button type="button" onclick="window.mainCalendar.next()" class="p-1 sm:p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 rounded-full transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>
                        <h2 id="calendar-title" class="text-lg sm:text-xl font-semibold text-slate-800 ml-1 sm:ml-2 capitalize"></h2>
                    </div>

                    <div class="flex items-center gap-3 sm:gap-4">
                        <!-- View Switcher -->
                        <div class="hidden sm:flex bg-slate-100/80 p-1 rounded-lg border border-slate-200/60">
                            <button type="button" onclick="window.mainCalendar.changeView('timeGridDay'); updateActiveViewBtn('day')" id="btn-view-day" class="px-3 py-1.5 text-sm font-medium rounded-md text-slate-600 hover:text-slate-900 transition-all">Jour</button>
                            <button type="button" onclick="window.mainCalendar.changeView('timeGridWeek'); updateActiveViewBtn('week')" id="btn-view-week" class="px-3 py-1.5 text-sm font-medium rounded-md text-slate-900 bg-white shadow-sm transition-all">Semaine</button>
                            <button type="button" onclick="window.mainCalendar.changeView('dayGridMonth'); updateActiveViewBtn('month')" id="btn-view-month" class="px-3 py-1.5 text-sm font-medium rounded-md text-slate-600 hover:text-slate-900 transition-all">Mois</button>
                        </div>

                        <!-- Mode Switcher -->
                        <div class="flex border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                            <button type="button" @click="viewMode = 'calendar'; setTimeout(() => window.mainCalendar.render(), 50)" :class="viewMode === 'calendar' ? 'bg-slate-100 text-slate-800' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-1.5 text-sm font-medium transition-colors border-r border-slate-200 flex items-center">
                                <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                                <span class="hidden sm:inline">Calendrier</span>
                            </button>
                            <button type="button" @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-slate-100 text-slate-800' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-1.5 text-sm font-medium transition-colors flex items-center">
                                <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <span class="hidden sm:inline">Liste</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Calendar View -->
                <div x-show="viewMode === 'calendar'" class="flex-1 p-0 overflow-hidden relative bg-white">
                    <div id="main-calendar" class="absolute inset-0"></div>
                </div>

                <!-- Table View -->
                <div x-show="viewMode === 'table'" x-cloak class="flex-1 overflow-auto p-4 bg-slate-50/30">
                    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50/80">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('interviews.list.candidate') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('interviews.list.job') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('interviews.list.interviewers') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('interviews.list.time') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('interviews.list.status') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('interviews.list.action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($interviews as $interview)
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-3 text-slate-800 font-medium">{{ $interview->application?->candidate?->full_name }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $interview->application?->job?->title }}</td>
                                        <td class="px-4 py-3 text-slate-600">
                                            {{ $interview->participants->map(fn($participant) => $participant->user?->profile?->full_name ?? $participant->user?->email)->filter()->implode(', ') }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ $interview->scheduled_start_at?->timezone($interview->timezone)->format('Y-m-d H:i') }}</td>
                                        <td class="px-4 py-3"><x-badge>{{ __('interviews.status.'.$interview->status) }}</x-badge></td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('interviews.show', ['interview' => $interview->id, 'company_id' => $selectedCompanyId]) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 hover:text-slate-900">
                                                {{ __('interviews.list.open') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8">
                                            <x-empty-state :title="__('interviews.empty_title')" :message="__('interviews.empty_message')" />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($interviews instanceof \Illuminate\Pagination\AbstractPaginator)
                        <div class="mt-4">{{ $interviews->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
        <style>
            /* Mini calendar styling */
            #mini-calendar .fc-toolbar-title { font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize; }
            #mini-calendar .fc-col-header-cell { font-weight: 500; font-size: 0.75rem; color: #64748b; padding: 4px 0; text-transform: uppercase; border: none; }
            #mini-calendar .fc-daygrid-day-number { font-size: 0.8125rem; color: #334155; width: 100%; text-align: center; padding: 4px 0; cursor: pointer; border-radius: 9999px; }
            #mini-calendar .fc-daygrid-day-number:hover { background-color: #f1f5f9; }
            #mini-calendar .fc-day-today .fc-daygrid-day-number { background: #0f172a; color: white; font-weight: 600; }
            #mini-calendar .fc-day-today .fc-daygrid-day-number:hover { background: #1e293b; }
            #mini-calendar .fc-theme-standard td, #mini-calendar .fc-theme-standard th { border: none; }
            #mini-calendar .fc-scrollgrid { border: none; }
            #mini-calendar .fc-button { background: transparent; border: none; color: #64748b; padding: 0.25rem; box-shadow: none !important; }
            #mini-calendar .fc-button:hover { color: #0f172a; background: #f1f5f9; border-radius: 9999px; }
            #mini-calendar .fc-button .fc-icon { font-size: 1rem; }
            #mini-calendar .fc-daygrid-day-frame { min-height: 32px !important; display: flex; align-items: center; justify-content: center; }
            #mini-calendar .fc-daygrid-day-top { display: flex; justify-content: center; width: 100%; }

            /* Main calendar styling (Google Calendar like) */
            #main-calendar .fc-theme-standard td, #main-calendar .fc-theme-standard th { border-color: #e2e8f0; }
            #main-calendar .fc-scrollgrid { border: none; }
            #main-calendar .fc-timegrid-axis-cushion { font-size: 0.75rem; color: #64748b; padding-right: 12px; font-weight: 500; }
            #main-calendar .fc-timegrid-slot-label-cushion { font-size: 0.75rem; color: #64748b; font-weight: 500; }
            #main-calendar .fc-col-header-cell-cushion { padding: 12px 8px; font-weight: 500; color: #475569; font-size: 0.875rem; }
            #main-calendar .fc-day-today { background-color: #f8fafc !important; }
            #main-calendar .fc-day-today .fc-col-header-cell-cushion { color: #0f172a; font-weight: 600; }
            #main-calendar .fc-v-event { border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 1px 2px rgba(0,0,0,0.05); padding: 2px 4px; }
            #main-calendar .fc-event-main { font-size: 0.75rem; font-weight: 500; line-height: 1.2; white-space: normal !important; overflow: visible !important; word-break: break-word; }
            #main-calendar .fc-event-title { white-space: normal !important; overflow: visible !important; word-break: break-word; }
            #main-calendar .fc-timegrid-event .fc-event-time { font-size: 0.7rem; opacity: 0.9; margin-bottom: 2px; font-weight: 400; }
            #main-calendar .fc-timegrid-now-indicator-line { border-color: #ef4444; border-width: 2px; }
            #main-calendar .fc-timegrid-now-indicator-arrow { border-color: #ef4444; border-width: 5px; }
            
            /* Remove blue focus rings from FC buttons */
            .fc-button:focus { box-shadow: none !important; }

            /* Custom thin scrollbar */
            .custom-scrollbar {
                scrollbar-width: thin;
                scrollbar-color: #cbd5e1 transparent;
            }
            .custom-scrollbar::-webkit-scrollbar {
                width: 6px;
            }
            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb {
                background-color: #cbd5e1;
                border-radius: 20px;
            }
        </style>
        <script>
        // Wait for FullCalendar to be available, then init both calendars
        (function initCalendars() {
            // If FullCalendar or DOM elements aren't ready yet, retry shortly
            if (typeof FullCalendar === 'undefined' || !document.getElementById('mini-calendar')) {
                setTimeout(initCalendars, 50);
                return;
            }

            // ---------- Helper ----------
            window.updateActiveViewBtn = function(viewName) {
                ['day', 'week', 'month'].forEach(function(v) {
                    var btn = document.getElementById('btn-view-' + v);
                    if (btn) {
                        if (v === viewName) {
                            btn.classList.add('bg-white', 'shadow-sm', 'text-slate-900');
                            btn.classList.remove('text-slate-600', 'hover:text-slate-900');
                        } else {
                            btn.classList.remove('bg-white', 'shadow-sm', 'text-slate-900');
                            btn.classList.add('text-slate-600', 'hover:text-slate-900');
                        }
                    }
                });
            };

            // ---------- Mini Calendar ----------
            var miniCalEl = document.getElementById('mini-calendar');
            if (miniCalEl) {
                window.miniCalendar = new FullCalendar.Calendar(miniCalEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: { left: 'prev', center: 'title', right: 'next' },
                    locale: 'fr',
                    height: 'auto',
                    contentHeight: 'auto',
                    dayHeaders: true,
                    fixedWeekCount: false,
                    showNonCurrentDates: false
                });
                window.miniCalendar.render();

                // Vanilla JS click handler - clicks on any day cell navigate the main calendar
                miniCalEl.addEventListener('click', function(e) {
                    var dayCell = e.target.closest('[data-date]');
                    if (dayCell && window.mainCalendar) {
                        var dateStr = dayCell.getAttribute('data-date');
                        if (dateStr) {
                            window.mainCalendar.gotoDate(dateStr);
                            window.mainCalendar.changeView('timeGridDay');
                            window.updateActiveViewBtn('day');
                        }
                    }
                });
            }

            // ---------- Main Calendar ----------
            var mainCalEl = document.getElementById('main-calendar');
            if (!mainCalEl) return;

            var calendarEvents = @js($calendarEvents);

            window.mainCalendar = new FullCalendar.Calendar(mainCalEl, {
                initialView: 'timeGridWeek',
                headerToolbar: false,
                events: calendarEvents,
                droppable: true,
                locale: 'fr',
                weekends: false,
                slotMinTime: '07:00:00',
                slotMaxTime: '19:00:00',
                allDaySlot: false,
                height: '100%',
                nowIndicator: true,
                dayMaxEvents: true,
                navLinks: true,
                slotLabelFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    omitZeroMinute: false,
                    meridiem: 'short'
                },
                navLinkDayClick: function(date) {
                    window.mainCalendar.gotoDate(date);
                    window.mainCalendar.changeView('timeGridDay');
                    window.updateActiveViewBtn('day');
                },
                dateClick: function(info) {
                    if (info.view.type === 'dayGridMonth') {
                        window.mainCalendar.gotoDate(info.date);
                        window.mainCalendar.changeView('timeGridDay');
                        window.updateActiveViewBtn('day');
                    }
                },
                datesSet: function(info) {
                    var titleEl = document.getElementById('calendar-title');
                    if (titleEl) titleEl.innerText = info.view.title;
                    if (window.miniCalendar) {
                        window.miniCalendar.gotoDate(info.view.currentStart);
                    }
                },
                eventClick: function(info) {
                    var link = info.event.extendedProps.meeting_link;
                    if (link) {
                        window.open(link, '_blank');
                        info.jsEvent.preventDefault();
                    } else if (info.event.url) {
                        window.location.href = info.event.url;
                        info.jsEvent.preventDefault();
                    }
                },
                eventReceive: function(info) {
                    var appData = Alpine.$data(document.getElementById('interviews-app'));
                    if (!appData.draftApplicationId || !appData.draftInterviewerId) {
                        info.revert();
                        return;
                    }
                    fetch('/candidates/' + appData.draftApplicationId + '/schedule-interview', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            scheduled_for: info.event.start.toISOString(),
                            duration_minutes: appData.draftDuration,
                            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                            interview_type: 'screening',
                            interviewer_user_ids: [appData.draftInterviewerId],
                            location_type: appData.draftType,
                            location_address: appData.draftType === 'in_person' ? 'Bureau' : '',
                            meeting_link: appData.draftLink
                        })
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.ok) {
                            info.event.setProp('title', data.interview.title);
                            if (data.interview.meeting_link) info.event.setExtendedProp('meeting_link', data.interview.meeting_link);
                            if (data.interview.url) info.event.setExtendedProp('url', data.interview.url);
                            alert(data.message || 'Entretien planifié avec succès');
                            appData.draftApplicationId = '';
                        } else {
                            info.revert();
                            alert(data.message || 'Erreur lors de la planification');
                        }
                    })
                    .catch(function() {
                        info.revert();
                        alert('Erreur de connexion');
                    });
                }
            });
            window.mainCalendar.render();

            // Make draggable element work with FullCalendar Draggable
            var externalEventsEl = document.getElementById('external-events');
            if (externalEventsEl) {
                new FullCalendar.Draggable(externalEventsEl, {
                    itemSelector: '.fc-event',
                    eventData: function(eventEl) {
                        var appData = Alpine.$data(document.getElementById('interviews-app'));
                        var titleText = 'Planification...';
                        if (appData && appData.draftApplicationId) {
                            var selectEl = document.querySelector('select[x-model="draftApplicationId"]');
                            if (selectEl && selectEl.selectedIndex >= 0) {
                                var selectedOption = selectEl.options[selectEl.selectedIndex];
                                if (selectedOption && selectedOption.value) {
                                    titleText = 'Planification: ' + selectedOption.text;
                                }
                            }
                        }
                        return {
                            title: titleText,
                            duration: eventEl.getAttribute('data-duration-str') || '01:00',
                            backgroundColor: eventEl.getAttribute('data-color'),
                            borderColor: eventEl.getAttribute('data-color')
                        };
                    }
                });
            }
        })();
        </script>
    @endpush
</x-shell-layout>
