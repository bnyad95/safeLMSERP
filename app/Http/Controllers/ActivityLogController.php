<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAnyRole('super_administrator');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'log_name' => trim((string) $request->query('log_name', '')),
            'causer_id' => $request->integer('causer_id') ?: null,
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $logsQuery = ActivityLog::with('causer')
            ->when($filters['q'] !== '', fn ($query) => $query->where('description', 'like', "%{$filters['q']}%"))
            ->when($filters['log_name'] !== '', fn ($query) => $query->where('log_name', $filters['log_name']))
            ->when($filters['causer_id'], fn ($query) => $query->where('causer_type', User::class)->where('causer_id', $filters['causer_id']))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest();

        $logs = $logsQuery->paginate(30)->withQueryString();

        $logNames = ActivityLog::query()
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name');

        $actorIds = ActivityLog::query()
            ->where('causer_type', User::class)
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        $actors = User::whereIn('id', $actorIds)->orderBy('name')->get(['id', 'name', 'email']);

        $stats = [
            ['label' => 'Total Events', 'value' => number_format(ActivityLog::count()), 'detail' => 'All recorded activity'],
            ['label' => 'Events Today', 'value' => number_format(ActivityLog::whereDate('created_at', today())->count()), 'detail' => 'Since midnight'],
            ['label' => 'Distinct Actors', 'value' => number_format($actorIds->count()), 'detail' => 'Users who have triggered an event'],
        ];

        return view('activity-log.index', compact('logs', 'filters', 'logNames', 'actors', 'stats'));
    }
}
