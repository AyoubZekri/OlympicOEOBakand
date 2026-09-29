<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Services\Tasks\RecurrenceRule;
use App\Services\Tasks\TaskPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Templates that create tasks: periodic (RRULE) or linked to a system event (match, training, meeting) */
class TaskTemplateController extends Controller
{
    public function index(Request $request)
    {
        if (!TaskPermissions::can($request->user(), 'templates')) {
            return response()->json(['message' => 'لا تملك صلاحية قوالب المهام'], 403);
        }

        $templates = TaskTemplate::with(['assignee:id,name', 'reviewer:id,name'])->withCount('tasks')->orderByDesc('id')->get();

        return response()->json(['status' => 'success', 'data' => $templates->map(fn ($t) => $this->present($t))]);
    }

    public function store(Request $request)
    {
        if (!TaskPermissions::can($request->user(), 'templates')) {
            return response()->json(['message' => 'لا تملك صلاحية قوالب المهام'], 403);
        }

        $template = new TaskTemplate($this->validated($request) + ['created_by' => $request->user()->id]);
        $this->schedule($template);
        $template->save();

        return response()->json(['status' => 'success', 'data' => $this->present($template->load(['assignee:id,name', 'reviewer:id,name']))], 201);
    }

    public function update(Request $request)
    {
        if (!TaskPermissions::can($request->user(), 'templates')) {
            return response()->json(['message' => 'لا تملك صلاحية قوالب المهام'], 403);
        }

        $template = TaskTemplate::findOrFail($request->input('id'));
        $before = [$template->rrule, optional($template->starts_on)->toDateTimeString(), $template->active];
        $template->fill($this->validated($request));

        // A new rule / start / reactivation restarts the schedule from now on (no backfill of the past)
        if ($before !== [$template->rrule, optional($template->starts_on)->toDateTimeString(), $template->active]) {
            $this->schedule($template);
        }
        $template->save();

        return response()->json(['status' => 'success', 'data' => $this->present($template->load(['assignee:id,name', 'reviewer:id,name']))]);
    }

    /** Tasks already created by the template stay; they only lose the link */
    public function destroy(Request $request)
    {
        if (!TaskPermissions::can($request->user(), 'templates')) {
            return response()->json(['message' => 'لا تملك صلاحية قوالب المهام'], 403);
        }

        TaskTemplate::findOrFail($request->input('id'))->delete();

        return response()->json(['status' => 'success']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'kind' => 'required|in:periodic,event',
            'assignee_id' => 'required|exists:users,id',
            'reviewer_id' => 'nullable|exists:users,id|different:assignee_id',
            'priority' => 'nullable|in:' . implode(',', Task::PRIORITIES),
            'requires_approval' => 'boolean',
            'requires_proof' => 'boolean',
            'rrule' => 'required_if:kind,periodic|nullable|string|max:255',
            'starts_on' => 'nullable|date',
            'trigger' => 'required_if:kind,event|nullable|in:' . implode(',', TaskTemplate::TRIGGERS),
            'offset_minutes' => 'nullable|integer|between:-525600,525600',
            'duration_minutes' => 'nullable|integer|between:1,525600',
            'active' => 'boolean',
        ], [
            'reviewer_id.different' => 'المراجع يجب أن يكون شخصاً غير المكلف',
            'rrule.required_if' => 'حدد تكرار المهمة',
            'trigger.required_if' => 'اختر الحدث الذي ينشئ المهمة',
        ]);

        if ($data['kind'] === 'periodic' && !RecurrenceRule::isValid($data['rrule'])) {
            abort(response()->json(['message' => 'قاعدة التكرار غير صحيحة'], 422));
        }

        $data['priority'] ??= 'normal';
        $data['requires_approval'] = (bool) ($data['requires_approval'] ?? true);
        $data['requires_proof'] = (bool) ($data['requires_proof'] ?? false);
        $data['offset_minutes'] = (int) ($data['offset_minutes'] ?? 0);
        $data['duration_minutes'] = (int) ($data['duration_minutes'] ?? 1440);
        $data['active'] = (bool) ($data['active'] ?? true);
        if ($data['requires_approval'] && empty($data['reviewer_id'])) {
            abort(response()->json(['message' => 'اختر مراجع المهام، أو ألغِ خيار "تتطلب اعتماد"'], 422));
        }
        if (!$data['requires_approval']) {
            $data['reviewer_id'] = null;
        }

        if ($data['kind'] === 'periodic') {
            $data['trigger'] = null;
            $data['offset_minutes'] = 0;
            $data['starts_on'] ??= now()->toDateTimeString();
        } else {
            $data['rrule'] = null;
            $data['starts_on'] = null;
        }
        return $data;
    }

    /** Next occurrence of a periodic template, counted from now */
    private function schedule(TaskTemplate $template): void
    {
        if ($template->kind !== 'periodic') {
            $template->next_run_at = null;
            return;
        }
        $anchor = CarbonImmutable::parse($template->starts_on);
        $rule = new RecurrenceRule($template->rrule, $anchor);
        $template->next_run_at = $rule->nextAfter(CarbonImmutable::now()->subSecond()->max($anchor->subSecond()));
    }

    private function present(TaskTemplate $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description,
            'kind' => $t->kind,
            'assignee_id' => $t->assignee_id,
            'assignee_name' => $t->assignee?->name,
            'reviewer_id' => $t->reviewer_id,
            'reviewer_name' => $t->reviewer?->name,
            'priority' => $t->priority,
            'requires_approval' => $t->requires_approval,
            'requires_proof' => $t->requires_proof,
            'rrule' => $t->rrule,
            'starts_on' => $t->starts_on?->format('Y-m-d H:i'),
            'next_run_at' => $t->next_run_at?->format('Y-m-d H:i'),
            'trigger' => $t->trigger,
            'offset_minutes' => $t->offset_minutes,
            'duration_minutes' => $t->duration_minutes,
            'active' => $t->active,
            'tasks_count' => $t->tasks_count ?? null,
        ];
    }
}
