<?php

namespace App\Http\Controllers\Container;

use App\Http\Controllers\BaseController;
use App\Models\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class MemberPaymentDetails extends BaseController
{
    public function __invoke(Request $request, int $id, int $userId): JsonResponse
    {
        $dateRange = $request->input('date_range');
        $paymentStatus = $request->input('payment_status', 'all');
        $startDate = null;
        $endDate = null;

        if ($dateRange) {
            [$start, $end] = array_pad(explode(' to ', $dateRange), 2, null);
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = $end ? Carbon::parse($end)->endOfDay() : now()->endOfDay();
        }

        $model = Container::with([
            'timeEntries' => function ($q) use ($userId, $startDate, $endDate, $paymentStatus) {
                $q->where('user_id', $userId);
                if ($startDate) {
                    $q->where('start', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('end', '<=', $endDate);
                }
                if ($paymentStatus !== 'all') {
                    $q->where('is_paid', $paymentStatus === 'paid');
                }
                $q->with('user:id,first_name,last_name,email');
                $q->with(['task' => function ($q) {
                    $q->withTrashed();
                }]);
                $q->withTrashed();
            },
            'members' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }   
        ])->findOrFail($id);

        $billableRate = $model->members->first()->billable_rate;

        $paymentDetails = $model->timeEntries
            ->whereNotNull('end')
            ->groupBy('task_id')
            ->map(function ($entries) use ($userId, $billableRate) {
                $trackedTime = $entries->sum(fn($entry) => $entry->end && !$entry->deleted_at
                    ? Carbon::parse($entry->start)->diffInSeconds(Carbon::parse($entry->end))
                    : 0);
                
                $task = $entries->first()->task;
                
                // Calculate paid and pending amounts
                $paidAmount = $entries->sum(fn($entry) => 
                    $entry->is_paid && !$entry->deleted_at ? ($entry->amount_paid ?? 0) : 0
                );
                
                $pendingAmount = $entries->sum(fn($entry) => 
                    !$entry->is_paid && !$entry->deleted_at && $entry->end
                        ? (Carbon::parse($entry->start)->diffInSeconds(Carbon::parse($entry->end)) / 3600) * $billableRate
                        : 0
                );
                
                return [
                    'task' => $task,
                    'trackedTime' => $trackedTime,
                    'trackedTimeDisplay' => $this->formatTime($trackedTime),
                    'paidAmount' => round($paidAmount, 2),
                    'pendingAmount' => round($pendingAmount, 2),
                    'timeEntries' => $entries
                        ->filter(fn($entry) => !is_null($entry->end))
                        ->map(fn($entry) => [
                            'id' => $entry->id,
                            'start' => $entry->start,
                            'end' => $entry->end,
                            'duration' => $entry->end && !$entry->deleted_at ? $this->formatTime(Carbon::parse($entry->start)->diffInSeconds(Carbon::parse($entry->end))) : null,
                            'is_paid' => $entry->is_paid,
                            'amount_paid' => $entry->amount_paid,
                            'paid_rate' => $entry->paid_rate,
                            'added_manually' => $entry->added_manually,
                            'deleted_at' => $entry->deleted_at,
                        ])->values(),
                    'entries_logs' => $task ? $task->logs()
                        ->where('loggable_type', 'App\Models\TimeEntry')
                        ->whereHas('loggable', function ($q) use ($userId) {
                            $q->where('user_id', $userId);
                        })
                        ->orderBy('created_at', 'desc')
                        ->with('user')->get()->map(function ($log) {
                            return [
                                'id' => $log->id,
                                'action' => $log->action,
                                'user' => $log->user,
                                'old_data' => $log->old_data,
                                'new_data' => collect($log->new_data)->map(function ($value, $key) {
                                    return in_array($key, ['start', 'end']) && $value ? Carbon::parse($value)->toIso8601String() : $value;
                                })->toArray(),
                                'created_at' => Carbon::parse($log->created_at)->toIso8601String(),
                            ];
                        }) : [],
                ];
            });

        return $this->successResponse($paymentDetails, 'Payment details fetched successfully.');
    }

    private function formatTime($seconds = 0)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
}
