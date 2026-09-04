<nav
    x-data="{ open: false }"
    class="shrink-0"
>
    <div class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <x-application-logo class="block h-12 w-auto max-w-40" />
            <span class="sr-only">{{ config('app.name', 'SafeLMS ERP') }}</span>
        </a>

        <div class="flex items-center gap-1">
            @include('layouts.user-menu')

            <button
                type="button"
                @click="open = ! open"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
                aria-label="{{ __('Toggle navigation') }}"
            >
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <div
        x-show="open"
        x-transition.opacity
        @click="open = false"
        class="fixed inset-0 z-40 bg-gray-900/40 dark:bg-black/60 lg:hidden"
        x-cloak
    ></div>

    <aside
        :class="open ? '' : 'max-lg:-translate-x-full max-lg:rtl:translate-x-full'"
        class="fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-gray-200 bg-white shadow-xl transition-transform duration-200 ease-out dark:border-gray-800 dark:bg-gray-900 lg:shadow-none"
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
                aria-label="{{ __('Close navigation') }}"
            >
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        @unless(Auth::user()?->hasAnyRole(['student', 'parent_user', 'librarian', 'it_support']))
        <div class="border-b border-gray-200 p-4 dark:border-gray-800">
            <form method="GET" action="{{ route('search') }}" class="relative" data-live-search data-suggestions-url="{{ route('search.suggestions') }}">
                <div class="relative">
                    <input
                        type="text"
                        name="q"
                        autocomplete="off"
                        data-live-search-input
                        value="{{ request('q') }}"
                        placeholder="{{ __('Search ERP...') }}"
                        class="w-full rounded-md border-gray-300 py-2 ps-3 pe-10 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder-gray-500"
                    />
                    <button type="submit" aria-label="{{ __('Search') }}" class="absolute inset-y-0 end-0 flex items-center pe-3 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </div>
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

                    $hasNewNotifications = \App\Models\AppNotification::query()
                        ->where(function ($query) use ($sidebarUser, $sidebarStudentId) {
                            $query->where('user_id', $sidebarUser->id);

                            if ($sidebarStudentId) {
                                $query->orWhere('student_id', $sidebarStudentId);
                            }
                        })
                        ->whereNull('read_at')
                        ->exists();
                @endphp
                <x-nav-link :href="$sidebarUsesStudentPortal ? route('student-portal') : route('dashboard')" :active="request()->routeIs('dashboard', 'finance.dashboard') || ($sidebarUsesStudentPortal && request()->routeIs('student-portal'))">
                    {{ __('Dashboard') }}
                </x-nav-link>
                <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    <span class="flex-1">{{ __('Notifications') }}</span>
                    @if($hasNewNotifications)
                        <span class="ml-2 h-2 w-2 shrink-0 rounded-full bg-red-500" aria-label="{{ __('Unread notifications') }}"></span>
                    @endif
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
                        $canReallyEnterFinalExam = $isSuper
                            || ($navUser->hasAnyRole(['examination_administrator', 'examination_committee']) && $navUser->hasPermission('marks.enter_final_exam'));
                        $canFinalExamEntry = $canReallyEnterFinalExam
                            || ($navUser->hasAnyRole(['examination_administrator', 'examination_committee']) && $navUser->hasAnyPermission(['marks.request_change', 'marks.approve']));
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
                        $canFinanceApprovals = $canFinance && ($isSuper || $navUser->hasAnyPermission(['finance.approve_payment', 'finance.approve_expense']));
                        $canManageTuitionRates = $navUser->hasRole('chief_accountant');
                        $canAnalytics = $navUser->hasAnyRole(['super_administrator', 'administrator', 'university_president']);
                        $canDataExchange = $navUser->hasAnyRole(['administrator', 'super_administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'examination_administrator', 'lms_administrator'])
                            && ($isSuper || $navUser->hasAnyPermission(['students.view', 'students.create', 'students.update', 'courses.view', 'courses.create', 'courses.update', 'marks.view', 'marks.enter', 'marks.review', 'marks.approve', 'marks.publish']));
                        $canStructure = $isSuper
                            || $navUser->hasRole('administrator')
                            || $navUser->hasAnyDirectPermissionGrant(['academic_setup.view', 'academic_setup.manage']);
                        $canUserManagement = $isSuper || ($navUser->hasRole('it_support') && $navUser->hasAnyPermission(['users.create', 'users.update', 'users.assign_roles', 'users.reset_password']));

                        $isPlainTeacher = $usesTeachingWorkspace
                            && ! $canStudents && ! $canTeachers && ! $canEnrollments && ! $canAttendance
                            && ! $canAssessments && ! $canExams && ! $canMarkQueue && ! $canFinalExamEntry
                            && ! $canFinance && ! $canDataExchange && ! $canStructure && ! $canAcademicArchive
                            && ! $canUserManagement && ! $canAnalytics && ! $isSuper;

                        $isPlainExamRole = $navUser->hasAnyRole(['examination_administrator', 'examination_committee'])
                            && ! $canStudents && ! $canTeachers && ! $canEnrollments && ! $canTimetable && ! $canAttendance
                            && ! $canClassrooms && ! $canAssessments
                            && ! $canFinance && ! $canStructure
                            && ! $canUserManagement && ! $canAnalytics && ! $isSuper;

                        $academicSectionActive = request()->routeIs('students.*', 'teachers.*', 'enrollments.*', 'course-sections.show', 'timetables.*', 'timetable-time-slots.*', 'attendance', 'attendance.*');
                        $learningSectionActive = request()->routeIs('teacher-dashboard', 'classrooms.*', 'archived-classes.*', 'assessments.*', 'assessment-items.*', 'assessment-submissions.*', 'exams', 'marks.final-exam.*', 'marks.submission-queue');
                        $financeSectionActive = request()->routeIs('finance', 'finance.students.*', 'finance.transactions.*', 'finance.statement', 'finance.export', 'finance.approvals.*', 'finance.tuition-reminders.*', 'bologna-definition.tuition-rates*');
                        $operationsSectionActive = request()->routeIs('integrations.*');
                        $setupSectionActive = request()->routeIs('bologna-definition*', 'academic-years.*', 'universities.*', 'colleges.*', 'departments.*', 'stages.*', 'semesters.*', 'course-records.*', 'module-offerings.*', 'course-sections.create', 'course-sections.archived', 'course-sections.restore', 'academic-year-closures.index', 'academic-year-closures.archive', 'academic-year-closures.archive.show');
                        $systemSectionActive = request()->routeIs('users.*', 'access-matrix', 'activity-log', 'analytics.*');
                    @endphp

                    @if($isPlainTeacher)
                        <x-nav-link :href="route('teacher-dashboard')" :active="request()->routeIs('teacher-dashboard', 'classrooms.*')">{{ __('Teaching') }}</x-nav-link>
                        @if($canTimetable)<x-nav-link :href="route('timetables.index')" :active="request()->routeIs('timetables.*', 'timetable-time-slots.*')">{{ __('Timetable') }}</x-nav-link>@endif
                        <x-nav-link :href="route('archived-classes.index')" :active="request()->routeIs('archived-classes.*')">{{ __('Archived Classes') }}</x-nav-link>
                    @elseif($isPlainExamRole)
                        @if($canExams)<x-nav-link :href="route('exams')" :active="request()->routeIs('exams')">{{ __('Results Overview') }}</x-nav-link>@endif
                        @if($canFinalExamEntry)<x-nav-link :href="route('marks.final-exam.index')" :active="request()->routeIs('marks.final-exam.*')">{{ $canReallyEnterFinalExam ? __('Final Exam Entry') : __('Final Exam Corrections') }}</x-nav-link>@endif
                        @if($canMarkQueue)<x-nav-link :href="route('marks.submission-queue')" :active="request()->routeIs('marks.submission-queue')">{{ __('Mark Queue') }}</x-nav-link>@endif
                        @if($canDataExchange)<x-nav-link :href="route('integrations.index')" :active="request()->routeIs('integrations.*')">{{ __('Data Import / Export') }}</x-nav-link>@endif
                    @else
                        @if($canStudents || $canTeachers || $canEnrollments || $canTimetable || $canAttendance)
                            <x-nav-group label="Academic" storage-key="academic" :active="$academicSectionActive">
                                @if($canStudents)<x-nav-link :href="route('students.index')" :active="request()->routeIs('students.*')">{{ __('Student Records') }}</x-nav-link>@endif
                                @if($canTeachers)<x-nav-link :href="route('teachers.index')" :active="request()->routeIs('teachers.*')">{{ __('Teachers') }}</x-nav-link>@endif
                                @if($canEnrollments)<x-nav-link :href="route('enrollments.index')" :active="request()->routeIs('enrollments.*', 'course-sections.show')">{{ __('Enrollments') }}</x-nav-link>@endif
                                @if($canTimetable)<x-nav-link :href="route('timetables.index')" :active="request()->routeIs('timetables.*', 'timetable-time-slots.*')">{{ __('Timetable') }}</x-nav-link>@endif
                                @if($canAttendance)<x-nav-link :href="route('attendance')" :active="request()->routeIs('attendance', 'attendance.*')">{{ __('Attendance') }}</x-nav-link>@endif
                            </x-nav-group>
                        @endif

                        @if($canClassrooms || $canAssessments || $canExams || $canMarkQueue || $canFinalExamEntry)
                            <x-nav-group label="Learning & Results" storage-key="learning" :active="$learningSectionActive">
                                @if($canClassrooms)<x-nav-link :href="$usesTeachingWorkspace ? route('teacher-dashboard') : route('classrooms.index')" :active="request()->routeIs('teacher-dashboard', 'classrooms.*')">{{ $usesTeachingWorkspace ? __('Teaching') : __('Classrooms') }}</x-nav-link>@endif
                                @if($navUser->hasRole('teacher'))<x-nav-link :href="route('archived-classes.index')" :active="request()->routeIs('archived-classes.*')">{{ __('Archived Classes') }}</x-nav-link>@endif
                                @if($canAssessments)<x-nav-link :href="route('assessments.index')" :active="request()->routeIs('assessments.*', 'assessment-items.*', 'assessment-submissions.*')">{{ $assessmentNavLabel }}</x-nav-link>@endif
                                @if($canExams)<x-nav-link :href="route('exams')" :active="request()->routeIs('exams')">{{ __('Results Overview') }}</x-nav-link>@endif
                                @if($canFinalExamEntry)<x-nav-link :href="route('marks.final-exam.index')" :active="request()->routeIs('marks.final-exam.*')">{{ $canReallyEnterFinalExam ? __('Final Exam Entry') : __('Final Exam Corrections') }}</x-nav-link>@endif
                                @if($canMarkQueue)<x-nav-link :href="route('marks.submission-queue')" :active="request()->routeIs('marks.submission-queue')">{{ __('Mark Queue') }}</x-nav-link>@endif
                            </x-nav-group>
                        @endif
                    @endif

                    @if($canFinance)
                        <x-nav-group label="Accounting & Finance" storage-key="finance" :active="$financeSectionActive">
                            <x-nav-link :href="route('finance')" :active="request()->routeIs('finance', 'finance.students.*', 'finance.transactions.*', 'finance.statement', 'finance.export')">{{ __('Student Finance') }}</x-nav-link>
                            @if($canFinanceApprovals)<x-nav-link :href="route('finance.approvals.index')" :active="request()->routeIs('finance.approvals.*')">{{ __('Finance Approvals') }}</x-nav-link>@endif
                            @if($canTuitionReminders)<x-nav-link :href="route('finance.tuition-reminders.index')" :active="request()->routeIs('finance.tuition-reminders.*')">{{ __('Tuition Reminders') }}</x-nav-link>@endif
                            @if($canManageTuitionRates)<x-nav-link :href="route('bologna-definition.tuition-rates')" :active="request()->routeIs('bologna-definition.tuition-rates*')">{{ __('Tuition Rates') }}</x-nav-link>@endif
                        </x-nav-group>
                    @endif

                    @if($canDataExchange && ! $isPlainExamRole)
                        <x-nav-group label="Operations" storage-key="operations" :active="$operationsSectionActive">
                            <x-nav-link :href="route('integrations.index')" :active="request()->routeIs('integrations.*')">{{ __('Data Import / Export') }}</x-nav-link>
                        </x-nav-group>
                    @endif

                    @if(($canStructure || $canAcademicArchive) && ! $isPlainExamRole)
                        <x-nav-group label="Academic Setup" storage-key="setup" :active="$setupSectionActive">
                            @if($canStructure)<x-nav-link :href="route('bologna-definition')" :active="request()->routeIs('bologna-definition*', 'academic-years.*', 'universities.*', 'colleges.*', 'departments.*', 'stages.*', 'semesters.*', 'course-records.*', 'module-offerings.*', 'course-sections.create', 'course-sections.archived', 'course-sections.restore')">{{ __('Bologna Definition') }}</x-nav-link>@endif
                            @if($canStructure)<x-nav-link :href="route('academic-year-closures.index')" :active="request()->routeIs('academic-year-closures.index')">{{ __('Academic Year Closing') }}</x-nav-link>@endif
                            @if($canAcademicArchive)<x-nav-link :href="route('academic-year-closures.archive')" :active="request()->routeIs('academic-year-closures.archive', 'academic-year-closures.archive.show')">{{ __('Academic Year Archive') }}</x-nav-link>@endif
                        </x-nav-group>
                    @endif

                    @if($canUserManagement || $isSuper || $canAnalytics)
                        <x-nav-group label="System" storage-key="system" :active="$systemSectionActive">
                            @if($canAnalytics)<x-nav-link :href="route('analytics.index')" :active="request()->routeIs('analytics.*')">{{ __('Institution Analytics') }}</x-nav-link>@endif
                            @if($canUserManagement)<x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">{{ __('User Management') }}</x-nav-link>@endif
                            @if($isSuper)<x-nav-link :href="route('access-matrix')" :active="request()->routeIs('access-matrix')">{{ __('Access Matrix') }}</x-nav-link>@endif
                            @if($isSuper)<x-nav-link :href="route('activity-log')" :active="request()->routeIs('activity-log')">{{ __('Activity Log') }}</x-nav-link>@endif
                        </x-nav-group>
                    @endif
                @endif
            </div>
        </div>
    </aside>
</nav>
