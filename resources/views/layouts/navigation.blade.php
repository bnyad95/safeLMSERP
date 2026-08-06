<nav
    x-data="{
        open: false,
        darkMode: document.documentElement.classList.contains('dark'),
        toggleTheme() {
            this.darkMode = ! this.darkMode;
            document.documentElement.classList.toggle('dark', this.darkMode);
            document.documentElement.style.colorScheme = this.darkMode ? 'dark' : 'light';
            localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
        },
    }"
    class="shrink-0"
>
    <div class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <x-application-logo class="block h-12 w-auto max-w-40" />
            <span class="sr-only">{{ config('app.name', 'SafeLMS ERP') }}</span>
        </a>

        <button
            type="button"
            @click="open = ! open"
            class="inline-flex h-10 w-10 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
            aria-label="Toggle navigation"
        >
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div
        x-show="open"
        x-transition.opacity
        @click="open = false"
        class="fixed inset-0 z-40 bg-gray-900/40 dark:bg-black/60 lg:hidden"
        x-cloak
    ></div>

    <aside
        :class="open ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-gray-200 bg-white shadow-xl transition-transform duration-200 ease-out dark:border-gray-800 dark:bg-gray-900 lg:translate-x-0 lg:shadow-none"
    >
        <div class="flex h-16 items-center justify-between border-b border-gray-200 px-5 dark:border-gray-800">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <x-application-logo class="block h-12 w-auto max-w-40" />
                <span class="sr-only">{{ config('app.name', 'SafeLMS ERP') }}</span>
            </a>

            <button
                type="button"
                @click="open = false"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white lg:hidden"
                aria-label="Close navigation"
            >
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        @unless(Auth::user()?->hasAnyRole(['student', 'parent_user', 'librarian', 'it_support']))
        <div class="border-b border-gray-200 p-4 dark:border-gray-800">
            <form method="GET" action="{{ route('search') }}" class="relative space-y-2" data-live-search data-suggestions-url="{{ route('search.suggestions') }}">
                <input
                    type="text"
                    name="q"
                    autocomplete="off"
                    data-live-search-input
                    value="{{ request('q') }}"
                    placeholder="Search ERP..."
                    class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder-gray-500"
                />
                <button type="submit" class="w-full rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                    Search
                </button>
                <div class="absolute left-0 right-0 top-full z-50 mt-2 hidden rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900" data-live-search-results></div>
            </form>
        </div>
        @endunless

        <div class="flex-1 overflow-y-auto px-3 py-4">
            <div class="space-y-1">
                @php
                    $sidebarUser = Auth::user();
                    $sidebarStudentId = null;
                    $portalRoleNames = ['student', 'parent_user', 'librarian', 'receptionist'];
                    $sidebarHasNonPortalRole = $sidebarUser?->roles->pluck('name')->diff($portalRoleNames)->isNotEmpty() ?? false;
                    $sidebarUsesStudentPortal = $sidebarUser?->hasRole('student') && ! $sidebarHasNonPortalRole;

                    if ($sidebarUsesStudentPortal) {
                        $sidebarStudentId = \App\Models\Student::query()
                            ->where('email', $sidebarUser->email)
                            ->value('id');
                    }

                    $sidebarSeenAtRaw = session('notifications_sidebar_seen_at');

                    $hasNewNotifications = \App\Models\AppNotification::query()
                        ->where(function ($query) use ($sidebarUser, $sidebarStudentId) {
                            $query->where('user_id', $sidebarUser->id);

                            if ($sidebarStudentId) {
                                $query->orWhere('student_id', $sidebarStudentId);
                            }
                        })
                        ->when($sidebarSeenAtRaw, function ($query, $sidebarSeenAtRaw) {
                            $query->where('created_at', '>', \Illuminate\Support\Carbon::parse($sidebarSeenAtRaw));
                        }, function ($query) {
                            $query->whereNull('read_at');
                        })
                        ->exists();
                @endphp
                <x-nav-link :href="$sidebarUsesStudentPortal ? route('student-portal') : route('dashboard')" :active="request()->routeIs('dashboard') || ($sidebarUsesStudentPortal && request()->routeIs('student-portal'))">
                    {{ __('Dashboard') }}
                </x-nav-link>
                <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" :class="$hasNewNotifications && ! request()->routeIs('notifications.*') ? 'font-bold text-gray-900 dark:text-gray-100' : ''">
                    {{ __('Notifications') }}
                </x-nav-link>
                @if ($sidebarUsesStudentPortal)
                    <x-nav-link :href="route('student.finance')" :active="request()->routeIs('student.finance')">
                        {{ __('Finance') }}
                    </x-nav-link>
                    <x-nav-link :href="route('course-registration.index')" :active="request()->routeIs('course-registration.*')">
                        {{ __('Course Registration') }}
                    </x-nav-link>
                    <x-nav-link :href="route('timetables.index')" :active="request()->routeIs('timetables.*')">
                        {{ __('My Timetable') }}
                    </x-nav-link>
                    <x-nav-link :href="route('archived-classes.index')" :active="request()->routeIs('archived-classes.*')">
                        {{ __('Archived Classes') }}
                    </x-nav-link>
                @elseif (Auth::user()?->hasRole('parent_user') && ! $sidebarHasNonPortalRole)
                    <x-nav-link :href="route('parent.workspace')" :active="request()->routeIs('parent.workspace')">
                        {{ __('Parent Portal') }}
                    </x-nav-link>
                @elseif (Auth::user()?->hasRole('librarian') && ! $sidebarHasNonPortalRole)
                    <x-nav-link :href="route('library.workspace')" :active="request()->routeIs('library.workspace')">
                        {{ __('Library Workspace') }}
                    </x-nav-link>
                @elseif (Auth::user()?->hasRole('receptionist') && ! $sidebarHasNonPortalRole)
                    <x-nav-link :href="route('reception.workspace')" :active="request()->routeIs('reception.workspace')">
                        {{ __('Front Desk') }}
                    </x-nav-link>
                @else
                    @php
                        $navUser = Auth::user();
                        $isSuper = $navUser->hasRole('super_administrator');
                        $canStudents = $isSuper || $navUser->hasPermission('students.view');
                        $canTeachers = $isSuper || $navUser->hasPermission('teachers.view');
                        $canEnrollments = $isSuper || $navUser->hasPermission('enrollments.view');
                        $canTimetable = $isSuper || $navUser->hasAnyPermission(['timetable.view', 'timetable.manage']);
                        $canAttendance = ($isSuper || $navUser->hasAnyRole(['administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'registrar']))
                            && ($isSuper || $navUser->hasAnyPermission(['attendance.view', 'attendance.create', 'attendance.update']));
                        $canAssessments = ($isSuper || $navUser->hasAnyRole(['administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'lms_administrator', 'teaching_assistant']))
                            && ($isSuper || $navUser->hasAnyPermission(['lms.view', 'lms.create_assignment', 'lms.grade_assignment', 'marks.enter', 'marks.view']));
                        $assessmentNavLabel = $navUser->hasAnyRole(['lms_administrator', 'teaching_assistant']) && ! $isSuper ? __('Assessments') : __('Assessment Analytics');
                        $canExams = $navUser->hasAnyRole(['administrator', 'super_administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'examination_administrator', 'examination_committee'])
                            && ($isSuper || $navUser->hasAnyPermission(['marks.view', 'marks.review', 'marks.approve', 'marks.publish']));
                        $canMarkQueue = ($isSuper || $navUser->hasAnyRole(['examination_administrator', 'examination_committee']))
                            && ($isSuper || $navUser->hasAnyPermission(['marks.review', 'marks.approve', 'marks.publish']));
                        $canFinalExamEntry = ($isSuper || $navUser->hasAnyRole(['examination_administrator', 'examination_committee']))
                            && ($isSuper || $navUser->hasPermission('marks.approve'));
                        $canAcademicArchive = $navUser->hasAnyRole(['super_administrator', 'administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'examination_administrator', 'examination_committee'])
                            || $navUser->hasAnyDirectPermissionGrant(['academic_setup.view', 'academic_setup.manage']);
                        $canClassrooms = $navUser->hasAnyRole(['teacher', 'teaching_assistant', 'administrator', 'super_administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'lms_administrator']);
                        $teachingBlockingRoles = array_values(array_diff(\App\Support\UserRolePolicy::HIGH_RISK_ROLES, ['teaching_assistant']));
                        $usesTeachingWorkspace = $navUser->hasAnyRole(['teacher', 'teaching_assistant']) && ! $navUser->hasAnyRole($teachingBlockingRoles);
                        $hasFinanceRole = $navUser->hasAnyRole(['chief_accountant', 'accountant']);
                        $canFinance = $isSuper
                            || ($hasFinanceRole && $navUser->hasPermission('finance.view'))
                            || $navUser->hasDirectPermissionGrant('finance.view');
                        $canTuitionReminders = $canFinance && ($isSuper
                            || ($navUser->hasPermission('finance.view') && $navUser->hasAnyPermission(['finance.create_invoice', 'finance.record_payment'])));
                        $canAnalytics = $navUser->hasAnyRole(['super_administrator', 'administrator', 'university_president']);
                        $canDataExchange = $navUser->hasAnyRole(['administrator', 'super_administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'examination_administrator', 'lms_administrator'])
                            && ($isSuper || $navUser->hasAnyPermission(['students.view', 'students.create', 'students.update', 'courses.view', 'courses.create', 'courses.update', 'marks.view', 'marks.enter', 'marks.review', 'marks.approve', 'marks.publish']));
                        $canStructure = $isSuper
                            || $navUser->hasRole('administrator')
                            || $navUser->hasAnyDirectPermissionGrant(['academic_setup.view', 'academic_setup.manage']);
                        $canUserManagement = $isSuper || ($navUser->hasRole('it_support') && $navUser->hasAnyPermission(['users.create', 'users.update', 'users.assign_roles', 'users.reset_password']));
                    @endphp

                    @if($canStudents || $canTeachers || $canEnrollments || $canTimetable || $canAttendance)
                        <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-gray-400">Academic</p>
                        @if($canStudents)<x-nav-link :href="route('students.index')" :active="request()->routeIs('students.*')">{{ __('Student Records') }}</x-nav-link>@endif
                        @if($canTeachers)<x-nav-link :href="route('teachers.index')" :active="request()->routeIs('teachers.*')">{{ __('Teachers') }}</x-nav-link>@endif
                        @if($canEnrollments)<x-nav-link :href="route('enrollments.index')" :active="request()->routeIs('enrollments.*', 'course-sections.show')">{{ __('Enrollments') }}</x-nav-link>@endif
                        @if($canTimetable)<x-nav-link :href="route('timetables.index')" :active="request()->routeIs('timetables.*', 'timetable-time-slots.*')">{{ __('Timetable') }}</x-nav-link>@endif
                        @if($canAttendance)<x-nav-link :href="route('attendance')" :active="request()->routeIs('attendance', 'attendance.*')">{{ __('Attendance') }}</x-nav-link>@endif
                    @endif

                    @if($canClassrooms || $canAssessments || $canExams || $canMarkQueue || $canFinalExamEntry || ($canAcademicArchive && ! $canStructure))
                        <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-gray-400">Learning &amp; Results</p>
                        @if($canClassrooms)<x-nav-link :href="$usesTeachingWorkspace ? route('teacher-dashboard') : route('classrooms.index')" :active="request()->routeIs('teacher-dashboard', 'classrooms.*')">{{ $usesTeachingWorkspace ? __('Teaching') : __('Classrooms') }}</x-nav-link>@endif
                        @if($navUser->hasRole('teacher'))<x-nav-link :href="route('archived-classes.index')" :active="request()->routeIs('archived-classes.*')">{{ __('Archived Classes') }}</x-nav-link>@endif
                        @if($canAssessments)<x-nav-link :href="route('assessments.index')" :active="request()->routeIs('assessments.*', 'assessment-items.*', 'assessment-submissions.*')">{{ $assessmentNavLabel }}</x-nav-link>@endif
                        @if($canExams)<x-nav-link :href="route('exams')" :active="request()->routeIs('exams')">{{ __('Results Overview') }}</x-nav-link>@endif
                        @if($canFinalExamEntry)<x-nav-link :href="route('marks.final-exam.index')" :active="request()->routeIs('marks.final-exam.*')">{{ __('Final Exam Entry') }}</x-nav-link>@endif
                        @if($canMarkQueue)<x-nav-link :href="route('marks.submission-queue')" :active="request()->routeIs('marks.submission-queue')">{{ __('Mark Queue') }}</x-nav-link>@endif
                        @if($canAcademicArchive && ! $canStructure)<x-nav-link :href="route('academic-year-closures.archive')" :active="request()->routeIs('academic-year-closures.archive', 'academic-year-closures.archive.show')">{{ __('Academic Year Archive') }}</x-nav-link>@endif
                    @endif

                    @if($canAnalytics)
                        <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-gray-400">Reports &amp; Analytics</p>
                        <x-nav-link :href="route('analytics.index')" :active="request()->routeIs('analytics.*')">{{ __('Institution Analytics') }}</x-nav-link>
                    @endif

                    @if($canFinance)
                        <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-gray-400">Accounting &amp; Finance</p>
                        <x-nav-link :href="route('finance')" :active="request()->routeIs('finance', 'finance.students.*', 'finance.transactions.*', 'finance.statement', 'finance.export')">{{ __('Student Finance') }}</x-nav-link>
                        @if($canTuitionReminders)<x-nav-link :href="route('finance.tuition-reminders.index')" :active="request()->routeIs('finance.tuition-reminders.*')">{{ __('Tuition Reminders') }}</x-nav-link>@endif
                    @endif

                    @if($canDataExchange)
                        <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-gray-400">Operations</p>
                        <x-nav-link :href="route('integrations.index')" :active="request()->routeIs('integrations.*')">{{ __('Data Import / Export') }}</x-nav-link>
                    @endif

                    @if($canStructure)
                        <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-gray-400">Academic Setup</p>
                        <x-nav-link :href="route('bologna-definition')" :active="request()->routeIs('bologna-definition*', 'academic-years.*', 'universities.*', 'colleges.*', 'departments.*', 'stages.*', 'semesters.*', 'course-records.*', 'module-offerings.*', 'course-sections.create', 'course-sections.archived', 'course-sections.restore')">{{ __('Bologna Definition') }}</x-nav-link>
                        <x-nav-link :href="route('academic-year-closures.index')" :active="request()->routeIs('academic-year-closures.index')">{{ __('Academic Year Closing') }}</x-nav-link>
                        <x-nav-link :href="route('academic-year-closures.archive')" :active="request()->routeIs('academic-year-closures.archive', 'academic-year-closures.archive.show')">{{ __('Academic Year Archive') }}</x-nav-link>
                    @endif

                    @if($canUserManagement || $isSuper)
                        <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-gray-400">System</p>
                        @if($canUserManagement)<x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">{{ __('User Management') }}</x-nav-link>@endif
                        @if($isSuper)<x-nav-link :href="route('access-matrix')" :active="request()->routeIs('access-matrix')">{{ __('Access Matrix') }}</x-nav-link>@endif
                    @endif
                @endif
            </div>
        </div>

        <div class="border-t border-gray-200 p-4 dark:border-gray-800">
            <button
                type="button"
                @click="toggleTheme()"
                class="mb-4 flex w-full items-center justify-between rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800"
                :aria-pressed="darkMode.toString()"
            >
                <span x-text="darkMode ? 'Dark mode' : 'Light mode'">Theme</span>
                <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-gray-300 transition dark:bg-indigo-600">
                    <span
                        class="inline-block h-5 w-5 rounded-full bg-white shadow transition"
                        :class="darkMode ? 'translate-x-5' : 'translate-x-1'"
                    ></span>
                </span>
            </button>

            <div class="mb-3 min-w-0">
                <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ Auth::user()->name }}</div>
                <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="space-y-1">
                <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                    {{ Auth::user()?->hasRole('student') ? __('Account Settings') : __('Profile') }}
                </x-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-nav-link>
                </form>
            </div>
        </div>
    </aside>
</nav>
