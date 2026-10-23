<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Services\RolePermissions;
use App\Services\TaskSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The tasks.
 * Personal space: the member sees the tasks given to them and talks about each one (the conversation); they do not
 * change its status.
 * Management (tasks.view / add / edit / delete): gives tasks (normal, urgent) and appointments (linked to an event,
 * or periodic), follows them, changes their status, and talks with the member.
 */
class TaskController extends Controller
{
    // ── Personal space ──

    public function mine(Request $request)
    {
        TaskSchedule::generateDue();
        $tasks = $this->tasks()
            ->where('assignee_id', $request->user()->id)
            // Unread: the messages only (a status change is not shown in the conversation)
            ->withCount(['messages as unread' => fn ($q) => $q
                ->whereNull('task_messages.status')
                ->where(fn ($q) => $q->whereNull('task_messages.user_id')->orWhereColumn('task_messages.user_id', '!=', 'tasks.assignee_id'))
                ->where(fn ($q) => $q->whereNull('tasks.assignee_read_at')->orWhereColumn('task_messages.created_at', '>', 'tasks.assignee_read_at'))])
            ->get();

        return response()->json(['data' => $tasks->map(fn ($t) => $this->present($t))]);
    }

    // ── Management ──

    public function index(Request $request)
    {
        $this->allow($request, 'view');
        TaskSchedule::generateDue();
        $tasks = $this->tasks()
            ->withCount(['messages as unread' => fn ($q) => $q
                ->whereNull('task_messages.status')
                ->whereColumn('task_messages.user_id', 'tasks.assignee_id')
                ->where(fn ($q) => $q->whereNull('tasks.managers_read_at')->orWhereColumn('task_messages.created_at', '>', 'tasks.managers_read_at'))])
            ->get();

        return response()->json(['data' => $tasks->map(fn ($t) => $this->present($t))]);
    }

    /** The appointments (linked to an event, or periodic) */
    public function templates(Request $request)
    {
        $this->allow($request, 'view');
        $templates = Task::with(['assignee:id,name', 'creator:id,name'])
            ->withCount(['children as tasks_count'])
            ->where('is_template', true)
            ->latest('id')
            ->get();

        return response()->json(['data' => $templates->map(fn ($t) => $this->presentTemplate($t))]);
    }

    /** Who a task can be given to: the members with an account */
    public function users(Request $request)
    {
        abort_unless(RolePermissions::can($request->user(), 'tasks', 'add') || RolePermissions::can($request->user(), 'tasks', 'edit'), 403, 'ليست لديك صلاحية');
        $users = \App\Models\User::orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])]);
    }

    /** A normal or urgent task (given at once), or an appointment (scheduled) */
    public function store(Request $request)
    {
        $this->allow($request, 'add');
        $data = $this->validated($request, $request->input('kind') === 'scheduled');

        if ($data['kind'] === 'scheduled') {
            $task = Task::create($this->templateFields($data) + [
                'title' => $data['title'], 'details' => $data['details'] ?? null, 'kind' => 'scheduled', 'nature' => $data['nature'],
                'assignee_id' => $data['assignee_id'], 'created_by' => $request->user()->id, 'is_template' => true, 'status' => 'active',
            ]);
            // A periodic one whose day is today, or a birthday whose alert time came: its task at once
            if ($task->schedule === 'periodic' || $task->event_type === 'birthday') {
                TaskSchedule::generateDue(true);
            }

            return response()->json(['data' => $this->presentTemplate($task->fresh(['assignee', 'creator'])->loadCount(['children as tasks_count']))], 201);
        }

        $task = Task::create([
            'title' => $data['title'], 'details' => $data['details'] ?? null, 'kind' => $data['kind'], 'nature' => $data['nature'],
            'assignee_id' => $data['assignee_id'], 'created_by' => $request->user()->id,
            'due_at' => $data['due_at'] ?? null, 'status' => 'new',
        ]);

        return response()->json(['data' => $this->present($this->tasks()->find($task->id))], 201);
    }

    /**
     * Edited: its text, its member, its nature, and by its kind — a normal one its deadline, an appointment's task its
     * alert and deadline (that task only), an appointment its schedule. A normal task can become urgent and back;
     * the other kinds stay.
     */
    public function update(Request $request)
    {
        $this->allow($request, 'edit');
        $task = Task::findOrFail($request->validate(['id' => 'required|integer'])['id']);
        $kind = (string) $request->input('kind');
        $switchable = !$task->is_template && in_array($task->kind, ['normal', 'urgent'], true);
        if ($switchable ? !in_array($kind, ['normal', 'urgent'], true) : $kind !== $task->kind) {
            abort(response()->json(['message' => 'لا يمكن تحويل المهمة إلى هذا النوع', 'errors' => ['kind' => ['لا يمكن تحويل المهمة إلى هذا النوع']]], 422));
        }
        // An appointment's task: its own alert and deadline
        $generated = !$task->is_template && $task->kind === 'scheduled';
        $data = $this->validated($request, $task->is_template, $kind !== 'normal' && !$generated);
        $dates = $generated ? $request->validate(
            ['alert_at' => 'nullable|date', 'due_at' => 'nullable|date|after_or_equal:alert_at'],
            ['due_at.after_or_equal' => 'آخر أجل يكون بعد التنبيه'],
        ) : [];

        $fields = ['title' => $data['title'], 'details' => $data['details'] ?? null, 'nature' => $data['nature'], 'assignee_id' => $data['assignee_id']];
        if ((int) $task->assignee_id !== (int) $data['assignee_id']) {
            // Another member: it is new to them
            $fields += ['assignee_seen_at' => null, 'assignee_read_at' => null];
        }
        if ($task->is_template) {
            $task->update($fields + $this->templateFields($data));

            return response()->json(['data' => $this->presentTemplate($task->fresh(['assignee', 'creator'])->loadCount(['children as tasks_count']))]);
        }

        if ($generated) {
            $fields += ['alert_at' => $dates['alert_at'] ?? null, 'due_at' => $dates['due_at'] ?? null];
        } elseif ($switchable) {
            $fields += ['kind' => $kind, 'due_at' => $kind === 'normal' ? ($data['due_at'] ?? null) : null];
        }
        $task->update($fields);

        return response()->json(['data' => $this->present($this->tasks()->find($task->id))]);
    }

    /** The management changes a task's status; the change (and its reason) is written in the conversation */
    public function status(Request $request)
    {
        $this->allow($request, 'edit');
        $data = $request->validate([
            'id' => 'required|integer',
            'status' => 'required|in:' . implode(',', Task::STATUSES),
            'note' => 'nullable|string|max:2000',
        ], [
            'status.in' => 'حالة غير صالحة',
        ]);
        $task = Task::where('is_template', false)->findOrFail($data['id']);
        if ($task->status === $data['status']) {
            return response()->json(['message' => 'المهمة في هذه الحالة أصلاً'], 422);
        }

        $task->update(['status' => $data['status'], 'done_at' => $data['status'] === 'done' ? now() : null, 'managers_read_at' => now()]);
        TaskMessage::create(['task_id' => $task->id, 'user_id' => $request->user()->id, 'status' => $data['status'], 'body' => $data['note'] ?? null]);

        return response()->json(['data' => $this->present($this->tasks()->find($task->id))]);
    }

    /** An appointment stopped (creates no more tasks) or started again */
    public function toggle(Request $request)
    {
        $this->allow($request, 'edit');
        $template = Task::where('is_template', true)->findOrFail($request->validate(['id' => 'required|integer'])['id']);
        $resume = $template->status === 'paused';
        $template->update(['status' => $resume ? 'active' : 'paused']
            // Started again: from today, not the days it was stopped
            + ($resume ? ['last_run_on' => TaskSchedule::localNow()->subDay()->toDateString()] : []));
        if ($resume && ($template->schedule === 'periodic' || $template->event_type === 'birthday')) {
            TaskSchedule::generateDue(true);
        }

        return response()->json(['data' => $this->presentTemplate($template->fresh(['assignee', 'creator'])->loadCount(['children as tasks_count']))]);
    }

    /** A task, or an appointment (the tasks it created stay) */
    public function destroy(Request $request)
    {
        $this->allow($request, 'delete');
        Task::findOrFail($request->validate(['id' => 'required|integer'])['id'])->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    /** The member opened the task: no longer "new" (the conversation stays unread until it is opened) */
    public function seen(Request $request)
    {
        $task = Task::where('is_template', false)->where('assignee_id', $request->user()->id)
            ->findOrFail($request->validate(['id' => 'required|integer'])['id']);
        if (!$task->assignee_seen_at) {
            $task->update(['assignee_seen_at' => now()]);
        }

        return response()->json(['message' => 'ok']);
    }

    // ── The conversation (the member and the management) ──

    /** A task's conversation; opening it marks it read for whoever opens it */
    public function messages(Request $request)
    {
        $task = $this->reachable($request);
        $this->markRead($request, $task);
        $messages = $task->messages()->with('user:id,name')->oldest('id')->get();

        return response()->json([
            'task' => $this->present($this->tasks()->find($task->id)),
            'data' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'status' => $m->status,
                'by' => $m->user?->name,
                'mine' => (int) $m->user_id === (int) $request->user()->id,
                'from_assignee' => $m->user_id !== null && (int) $m->user_id === (int) $task->assignee_id,
                'at' => $this->local($m->created_at),
            ]),
        ]);
    }

    public function send(Request $request)
    {
        $task = $this->reachable($request);
        $data = $request->validate(['body' => 'required|string|max:3000'], ['body.required' => 'اكتب الرسالة']);
        $message = TaskMessage::create(['task_id' => $task->id, 'user_id' => $request->user()->id, 'body' => $data['body']]);
        $this->markRead($request, $task);

        return response()->json(['data' => [
            'id' => $message->id, 'body' => $message->body, 'status' => null, 'by' => $request->user()->name, 'mine' => true,
            'from_assignee' => $task->isAssignee($request->user()), 'at' => $this->local($message->created_at),
        ]], 201);
    }

    // ── Helpers ──

    private function tasks()
    {
        return Task::with(['assignee:id,name', 'creator:id,name', 'lastMessage.user:id,name', 'template:id,schedule,event_type,repeat'])
            ->where('is_template', false)
            ->latest('id');
    }

    /** The member it is given to, or the management */
    private function reachable(Request $request): Task
    {
        $task = Task::where('is_template', false)->findOrFail($request->validate(['id' => 'required|integer'])['id']);
        abort_unless($task->isAssignee($request->user()) || RolePermissions::can($request->user(), 'tasks', 'view'), 403, 'ليست لديك صلاحية');

        return $task;
    }

    private function markRead(Request $request, Task $task): void
    {
        if ($task->isAssignee($request->user())) {
            $task->update(['assignee_read_at' => now(), 'assignee_seen_at' => $task->assignee_seen_at ?? now()]);
        } else {
            $task->update(['managers_read_at' => now()]);
        }
    }

    /** $scheduled: an appointment's fields; $noDeadline: a task with no deadline of its own (an appointment's, urgent, voice) */
    private function validated(Request $request, bool $scheduled, bool $noDeadline = false): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'details' => 'nullable|string|max:5000',
            'kind' => 'required|in:' . implode(',', Task::KINDS),
            'nature' => 'required|in:' . implode(',', Task::NATURES),
            'assignee_id' => 'required|integer|exists:users,id',
            'due_at' => $scheduled || $noDeadline || in_array($request->input('kind'), Task::NO_DEADLINE, true) ? 'prohibited' : 'nullable|date',
            'schedule' => $scheduled ? 'required|in:event,periodic' : 'prohibited',
            'event_type' => $scheduled ? 'required_if:schedule,event|nullable|in:' . implode(',', Task::EVENTS) : 'prohibited',
            'repeat' => $scheduled ? 'required_if:schedule,periodic|nullable|in:weekly,monthly' : 'prohibited',
            'repeat_day' => $scheduled ? 'required_if:schedule,periodic|nullable|integer|min:1|max:31' : 'prohibited',
            'alert_offset' => $scheduled ? 'required|integer|between:-525600,525600' : 'prohibited',
            'due_offset' => $scheduled ? 'required|integer|between:-525600,525600|gte:alert_offset' : 'prohibited',
        ], [
            'title.required' => 'اكتب المهمة',
            'assignee_id.required' => 'اختر المكلّف',
            'assignee_id.exists' => 'اختر المكلّف',
            'kind.required' => 'اختر النوع',
            'nature.required' => 'اختر طبيعة المهمة',
            'schedule.required' => 'اختر: مرتبطة بحدث أو دورية',
            'event_type.required_if' => 'اختر الحدث',
            'repeat.required_if' => 'اختر التكرار',
            'repeat_day.required_if' => 'اختر اليوم',
            'alert_offset.required' => 'حدد موعد التنبيه',
            'due_offset.required' => 'حدد آخر أجل',
            'due_offset.gte' => 'آخر أجل يكون بعد التنبيه',
            '*.prohibited' => 'هذا الحقل لا يخص هذا النوع',
        ]);

        if ($scheduled && $data['schedule'] === 'periodic') {
            if ($data['repeat'] === 'weekly' && $data['repeat_day'] > 7) {
                abort(response()->json(['message' => 'اختر يوماً من الأسبوع', 'errors' => ['repeat_day' => ['اختر يوماً من الأسبوع']]], 422));
            }
        }

        return $data;
    }

    private function templateFields(array $data): array
    {
        $event = $data['schedule'] === 'event';

        return [
            'schedule' => $data['schedule'],
            'event_type' => $event ? $data['event_type'] : null,
            'repeat' => $event ? null : $data['repeat'],
            'repeat_day' => $event ? null : $data['repeat_day'],
            'alert_offset' => $data['alert_offset'],
            'due_offset' => $data['due_offset'],
        ];
    }

    private function allow(Request $request, string $action): void
    {
        abort_unless(RolePermissions::can($request->user(), 'tasks', $action), 403, 'ليست لديك صلاحية');
    }

    /** A timestamp in the club's time */
    private function local($time): ?string
    {
        return $time ? Carbon::parse($time)->setTimezone('Africa/Algiers')->format('Y-m-d H:i') : null;
    }

    private function present(Task $t): array
    {
        $now = TaskSchedule::localNow();
        $last = $t->lastMessage;

        return [
            'id' => $t->id,
            'title' => $t->title,
            'details' => $t->details,
            'kind' => $t->kind,
            'nature' => $t->nature,
            'status' => $t->status,
            // Not a status anyone picks: the deadline passed and it is not finished
            'overdue' => $t->isOpen() && $t->due_at !== null && $t->due_at->lt($now),
            'alert_at' => $t->alert_at?->format('Y-m-d H:i'),
            'due_at' => $t->due_at?->format('Y-m-d H:i'),
            'event_label' => $t->event_label,
            // Where it comes from: an event (match, training, travel, meeting), periodic, or given directly
            'source' => $t->template?->schedule === 'event' ? $t->template->event_type
                : ($t->template?->schedule === 'periodic' ? 'periodic' : ($t->source_ref ? strtok($t->source_ref, ':') : null)),
            'template_id' => $t->template_id,
            // The event it was created for, to open it: a match / training session / meeting / trip, or the member (a birthday)
            'event' => $this->eventOf($t->source_ref),
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            'created_by' => $t->creator?->name,
            'seen' => $t->assignee_seen_at !== null,
            'unread' => (int) ($t->unread ?? 0),
            'last_message' => $last ? [
                'body' => $last->body,
                'status' => $last->status,
                'by' => $last->user?->name,
                'at' => $this->local($last->created_at),
            ] : null,
            'done_at' => $this->local($t->done_at),
            'created_at' => $this->local($t->created_at),
        ];
    }

    /** «match:5» → match 5; «birthday:12:2026» → the member 12; a periodic day → none */
    private function eventOf(?string $ref): ?array
    {
        $parts = explode(':', (string) $ref);

        return in_array($parts[0], Task::EVENTS, true) && ctype_digit($parts[1] ?? '') ? ['type' => $parts[0], 'id' => (int) $parts[1]] : null;
    }

    private function presentTemplate(Task $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'details' => $t->details,
            'nature' => $t->nature,
            'schedule' => $t->schedule,
            'event_type' => $t->event_type,
            'repeat' => $t->repeat,
            'repeat_day' => $t->repeat_day,
            'alert_offset' => $t->alert_offset,
            'due_offset' => $t->due_offset,
            'status' => $t->status,
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            'created_by' => $t->creator?->name,
            'tasks_count' => (int) ($t->tasks_count ?? 0),
            'created_at' => $this->local($t->created_at),
        ];
    }
}
