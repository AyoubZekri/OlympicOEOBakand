<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\Tasks\TaskEvents;
use App\Services\Tasks\TaskPermissions;
use App\Services\Tasks\TaskWorkflow;
use App\Services\Tasks\TaskWorkflowException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    /**
     * Tasks list. scope: my (assigned to me) | review (waiting for a reviewer, or reviewed by me) | created | all | archive
     * Reviewers are not chosen in advance: every user with tasks.review sees the tasks waiting for review.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $scope = $request->query('scope', 'my');

        if (in_array($scope, ['all', 'archive'], true) && !TaskPermissions::can($user, 'manage')) {
            return response()->json(['message' => 'لا تملك صلاحية عرض كل المهام'], 403);
        }
        if ($scope === 'review' && !TaskPermissions::can($user, 'review')) {
            return response()->json(['message' => 'لا تملك صلاحية مراجعة المهام'], 403);
        }

        $query = Task::query()->with(['assignee:id,name', 'reviewer:id,name', 'creator:id,name'])->withCount('attachments');
        match ($scope) {
            'review' => $query->where('assignee_id', '!=', $user->id)
                ->where(fn (Builder $q) => $q->where('status', 'in_review')->orWhere('reviewer_id', $user->id)),
            'created' => $query->where('created_by', $user->id),
            'all' => null,
            'archive' => $query->onlyTrashed(),
            default => $query->where('assignee_id', $user->id),
        };

        if ($status = $request->query('status')) {
            $query->whereIn('status', explode(',', $status));
        }
        if ($assignee = $request->query('assignee_id')) {
            $query->where('assignee_id', $assignee);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn (Builder $q) => $q->where('title', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%"));
        }

        $tasks = $query->orderByRaw("CASE WHEN status = 'approved' THEN 1 ELSE 0 END")
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')->orderByDesc('id')
            ->limit(500)->get();

        return response()->json(['status' => 'success', 'data' => $tasks->map(fn (Task $t) => $this->present($t))]);
    }

    public function show(Request $request, $id)
    {
        $task = Task::withTrashed()->with([
            'assignee:id,name', 'reviewer:id,name', 'creator:id,name',
            'attachments.uploader:id,name', 'history.user:id,name',
        ])->findOrFail($id);

        if (!$this->canSee($request->user(), $task)) {
            return response()->json(['message' => 'لا تملك صلاحية عرض هذه المهمة'], 403);
        }

        return response()->json(['status' => 'success', 'data' => $this->present($task, true)]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!TaskPermissions::can($user, 'add')) {
            return response()->json(['message' => 'لا تملك صلاحية إنشاء المهام'], 403);
        }

        $data = $this->validated($request);

        // Linked to a specific match or training session
        $source = ['source_type' => 'manual', 'source_ref' => null];
        $event = $request->validate([
            'event_type' => 'nullable|in:' . implode(',', TaskEvents::TYPES),
            'event_id' => 'required_with:event_type|nullable|integer',
        ], ['event_id.required_with' => 'اختر المباراة أو الحصة التدريبية']);
        if (!empty($event['event_type'])) {
            if (!TaskEvents::exists($event['event_type'], (int) $event['event_id'])) {
                return response()->json(['message' => 'المباراة أو الحصة التدريبية غير موجودة'], 422);
            }
            $source = ['source_type' => 'event', 'source_ref' => "{$event['event_type']}:{$event['event_id']}"];
        }

        $task = TaskWorkflow::create($data + $source + ['created_by' => $user->id], $user->id);

        return response()->json(['status' => 'success', 'data' => $this->present($task->fresh(['assignee:id,name', 'reviewer:id,name', 'creator:id,name']))], 201);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $task = Task::findOrFail($request->input('id'));

        if ((int) $task->created_by !== (int) $user->id && !TaskPermissions::can($user, 'edit')) {
            return response()->json(['message' => 'لا تملك صلاحية تعديل هذه المهمة'], 403);
        }
        if ($task->status === 'approved') {
            return response()->json(['message' => 'لا يمكن تعديل مهمة معتمدة'], 422);
        }

        $data = $this->validated($request);
        $reassigned = (int) $data['assignee_id'] !== (int) $task->assignee_id;
        $task->fill($data)->save();
        TaskWorkflow::record($task, 'updated', $task->status, $task->status, $user->id, $reassigned ? 'تغيير المكلف' : null);

        return response()->json(['status' => 'success', 'data' => $this->present($task->fresh(['assignee:id,name', 'reviewer:id,name', 'creator:id,name']))]);
    }

    /** Soft delete: the task stays in the archive */
    public function destroy(Request $request)
    {
        $user = $request->user();
        $task = Task::findOrFail($request->input('id'));

        if ((int) $task->created_by !== (int) $user->id && !TaskPermissions::can($user, 'delete')) {
            return response()->json(['message' => 'لا تملك صلاحية حذف هذه المهمة'], 403);
        }

        TaskWorkflow::record($task, 'deleted', $task->status, $task->status, $user->id);
        $task->delete();

        return response()->json(['status' => 'success']);
    }

    public function restore(Request $request)
    {
        $user = $request->user();
        if (!TaskPermissions::can($user, 'delete') && !TaskPermissions::can($user, 'manage')) {
            return response()->json(['message' => 'لا تملك صلاحية استرجاع المهام'], 403);
        }

        $task = Task::onlyTrashed()->findOrFail($request->input('id'));
        $task->restore();
        TaskWorkflow::record($task, 'restored', $task->status, $task->status, $user->id);

        return response()->json(['status' => 'success', 'data' => $this->present($task)]);
    }

    /** Workflow action: start | block | resume | submit | approve | return */
    public function action(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:tasks,id',
            'action' => 'required|string',
            'reason' => 'nullable|string',
            'note' => 'nullable|string',
        ]);
        $user = $request->user();

        if (in_array($request->input('action'), ['approve', 'return'], true) && !TaskPermissions::can($user, 'review')) {
            return response()->json(['message' => 'لا تملك صلاحية مراجعة المهام'], 403);
        }

        try {
            $task = TaskWorkflow::apply(Task::findOrFail($request->input('id')), $request->input('action'), $user, $request->only(['reason', 'note']));
        } catch (TaskWorkflowException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        return response()->json(['status' => 'success', 'data' => $this->present($task->fresh(['assignee:id,name', 'reviewer:id,name', 'creator:id,name']))]);
    }

    /** Proof of execution: a file, an image, a text or a link */
    public function attach(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:tasks,id',
            'type' => 'required|in:' . implode(',', TaskAttachment::TYPES),
            'file' => 'required_if:type,file,image|file|max:10240',
            'url' => 'required_if:type,link|nullable|url|max:1000',
            'body' => 'required_if:type,text|nullable|string|max:5000',
        ]);
        $task = Task::findOrFail($request->input('id'));
        $type = $request->input('type');

        $data = ['type' => $type];
        if (in_array($type, ['file', 'image'], true)) {
            if ($type === 'image' && !str_starts_with((string) $request->file('file')->getMimeType(), 'image/')) {
                return response()->json(['message' => 'الملف ليس صورة'], 422);
            }
            $data['path'] = $request->file('file')->store('task_attachments', 'public');
            $data['original_name'] = $request->file('file')->getClientOriginalName();
        } elseif ($type === 'link') {
            $data['url'] = $request->input('url');
        } else {
            $data['body'] = $request->input('body');
        }

        try {
            $attachment = TaskWorkflow::attach($task, $request->user(), $data);
        } catch (TaskWorkflowException $e) {
            if (!empty($data['path'])) {
                Storage::disk('public')->delete($data['path']);
            }
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        return response()->json(['status' => 'success', 'data' => $this->presentAttachment($attachment)], 201);
    }

    public function deleteAttachment(Request $request)
    {
        $attachment = TaskAttachment::with('task')->findOrFail($request->input('id'));
        $task = $attachment->task;

        if ((int) $attachment->uploaded_by !== (int) $request->user()->id || in_array($task->status, ['in_review', 'approved'], true)) {
            return response()->json(['message' => 'لا يمكن حذف هذا المرفق'], 403);
        }
        if ($attachment->path) {
            Storage::disk('public')->delete($attachment->path);
        }
        $attachment->delete();

        return response()->json(['status' => 'success']);
    }

    /**
     * Manager dashboard: counts and the on-time rate.
     * Filter: from / to on the deadline, assignee_id.
     * On-time rate = approved tasks finished by their deadline ÷ approved tasks with a deadline.
     */
    public function stats(Request $request)
    {
        if (!TaskPermissions::can($request->user(), 'manage')) {
            return response()->json(['message' => 'لا تملك صلاحية لوحة المهام'], 403);
        }

        $query = Task::query();
        if ($from = $request->query('from')) {
            $query->where('due_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('due_at', '<=', $to . ' 23:59:59');
        }
        if ($assignee = $request->query('assignee_id')) {
            $query->where('assignee_id', $assignee);
        }
        $tasks = $query->with('assignee:id,name')->get();

        $summary = fn ($list) => [
            'total' => $list->count(),
            'open' => $list->where('status', '!=', 'approved')->count(),
            'approved' => $list->where('status', 'approved')->count(),
            'overdue' => $list->filter(fn (Task $t) => $t->is_overdue)->count(),
            'blocked' => $list->where('status', 'blocked')->count(),
            'in_review' => $list->where('status', 'in_review')->count(),
            'returned' => $list->where('status', 'returned')->count(),
            'on_time_rate' => $this->onTimeRate($list),
        ];

        $byAssignee = $tasks->groupBy('assignee_id')->map(fn ($list) => [
            'assignee_id' => $list->first()->assignee_id,
            'assignee_name' => $list->first()->assignee?->name,
        ] + $summary($list))->values()->sortByDesc('total')->values();

        return response()->json(['status' => 'success', 'data' => $summary($tasks) + ['by_assignee' => $byAssignee]]);
    }

    /** Upcoming matches or training sessions a new task can be linked to (type: match | training) */
    public function events(Request $request)
    {
        $type = $request->query('type', 'match');
        if (!in_array($type, TaskEvents::TYPES, true)) {
            return response()->json(['message' => 'نوع غير معروف'], 422);
        }
        return response()->json(['status' => 'success', 'data' => TaskEvents::upcoming($type)]);
    }

    /** People a task can be given to */
    public function users()
    {
        return response()->json(['status' => 'success', 'data' => User::orderBy('name')->get(['id', 'name'])]);
    }

    private function onTimeRate($list): ?int
    {
        $approved = $list->filter(fn (Task $t) => $t->status === 'approved' && $t->due_at);
        if ($approved->isEmpty()) {
            return null;
        }
        $onTime = $approved->filter(fn (Task $t) => $t->completed_at && $t->completed_at->lessThanOrEqualTo($t->due_at))->count();
        return (int) round($onTime * 100 / $approved->count());
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignee_id' => 'required|exists:users,id',
            'priority' => 'nullable|in:' . implode(',', Task::PRIORITIES),
            'starts_at' => 'nullable|date',
            'due_at' => 'nullable|date|after_or_equal:starts_at',
            'requires_approval' => 'boolean',
            'requires_proof' => 'boolean',
        ], [
            'due_at.after_or_equal' => 'آخر أجل يجب أن يكون بعد تاريخ البداية',
        ]);

        $data['priority'] ??= 'normal';
        $data['requires_approval'] = (bool) ($data['requires_approval'] ?? true);
        $data['requires_proof'] = (bool) ($data['requires_proof'] ?? false);
        return $data;
    }

    private function canSee(User $user, Task $task): bool
    {
        return in_array((int) $user->id, [(int) $task->assignee_id, (int) $task->reviewer_id, (int) $task->created_by], true)
            || TaskPermissions::can($user, 'manage')
            || ($task->status === 'in_review' && TaskPermissions::can($user, 'review'));
    }

    private function present(Task $task, bool $full = false): array
    {
        $data = [
            'id' => $task->id,
            'reference' => $task->reference,
            'title' => $task->title,
            'description' => $task->description,
            'assignee_id' => $task->assignee_id,
            'assignee_name' => $task->assignee?->name,
            'reviewer_id' => $task->reviewer_id,
            'reviewer_name' => $task->reviewer?->name,
            'created_by' => $task->created_by,
            'creator_name' => $task->creator?->name,
            'priority' => $task->priority,
            'starts_at' => $task->starts_at?->format('Y-m-d H:i'),
            'due_at' => $task->due_at?->format('Y-m-d H:i'),
            'requires_approval' => $task->requires_approval,
            'requires_proof' => $task->requires_proof,
            'status' => $task->status,
            'is_overdue' => $task->is_overdue,
            'block_reason' => $task->block_reason,
            'block_note' => $task->block_note,
            'return_reason' => $task->return_reason,
            'source_type' => $task->source_type,
            'source_ref' => $task->source_ref,
            'completed_at' => $task->completed_at?->format('Y-m-d H:i'),
            'approved_at' => $task->approved_at?->format('Y-m-d H:i'),
            'created_at' => $task->created_at?->format('Y-m-d H:i'),
            'deleted_at' => $task->deleted_at?->format('Y-m-d H:i'),
            'attachments_count' => $task->attachments_count ?? null,
        ];

        if ($full) {
            $data['event'] = $task->source_type === 'event' ? TaskEvents::find($task->source_ref) : null;
            $data['attachments'] = $task->attachments->map(fn ($a) => $this->presentAttachment($a))->values();
            $data['history'] = $task->history->map(fn ($h) => [
                'id' => $h->id,
                'action' => $h->action,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'note' => $h->note,
                'user_name' => $h->user?->name,
                'created_at' => $h->created_at?->format('Y-m-d H:i'),
            ])->values();
        }
        return $data;
    }

    private function presentAttachment(TaskAttachment $a): array
    {
        return [
            'id' => $a->id,
            'type' => $a->type,
            'name' => $a->original_name,
            'url' => $a->path ? asset('storage/' . $a->path) : $a->url,
            'body' => $a->body,
            'uploaded_by' => $a->uploaded_by,
            'uploader_name' => $a->uploader?->name,
            'created_at' => $a->created_at?->format('Y-m-d H:i'),
        ];
    }
}
