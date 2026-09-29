<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place that changes a task's status. Each action checks who may do it and from which status,
 * then records the change in task_status_history.
 *
 *   assigned → in_progress → (blocked ⇄ in_progress) → in_review → approved | returned → in_progress
 *   A task that does not require approval goes straight from in_progress to approved.
 */
class TaskWorkflow
{
    /** action => [allowed "from" statuses, "to" status, actor: assignee | reviewer] */
    private const ACTIONS = [
        'start' => [['assigned', 'returned'], 'in_progress', 'assignee'],
        'block' => [['in_progress'], 'blocked', 'assignee'],
        'resume' => [['blocked'], 'in_progress', 'assignee'],
        'submit' => [['in_progress'], 'in_review', 'assignee'],
        'approve' => [['in_review'], 'approved', 'reviewer'],
        'return' => [['in_review'], 'returned', 'reviewer'],
    ];

    public static function record(Task $task, string $action, ?string $from, ?string $to, ?int $userId, ?string $note = null): void
    {
        TaskStatusHistory::create([
            'task_id' => $task->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'user_id' => $userId,
        ]);
    }

    /** Create a task, give it its reference and log its creation */
    public static function create(array $data, ?int $userId, ?string $note = null): Task
    {
        return DB::transaction(function () use ($data, $userId, $note) {
            $task = Task::create($data + ['status' => 'assigned']);
            $task->reference = sprintf('TSK-%s-%04d', $task->created_at->format('Y'), $task->id);
            $task->save();
            self::record($task, 'created', null, 'assigned', $userId, $note);
            return $task;
        });
    }

    /**
     * Run one workflow action.
     * $extra: block => [reason, note], return => [reason]
     *
     * @throws TaskWorkflowException
     */
    public static function apply(Task $task, string $action, User $user, array $extra = []): Task
    {
        if (!isset(self::ACTIONS[$action])) {
            throw new TaskWorkflowException('إجراء غير معروف', 422);
        }
        [$from, $to, $actor] = self::ACTIONS[$action];

        // Who may act
        if ($actor === 'assignee' && (int) $task->assignee_id !== (int) $user->id) {
            throw new TaskWorkflowException('هذا الإجراء خاص بالمكلف بالمهمة', 403);
        }
        if ($actor === 'reviewer' && (int) $task->reviewer_id !== (int) $user->id) {
            throw new TaskWorkflowException('هذا الإجراء خاص بمراجع المهمة', 403);
        }

        // From which status
        if (!in_array($task->status, $from, true)) {
            throw new TaskWorkflowException('لا يمكن تنفيذ هذا الإجراء في حالة المهمة الحالية', 422);
        }

        $note = null;
        $previous = $task->status;

        switch ($action) {
            case 'block':
                $reason = $extra['reason'] ?? null;
                if (!in_array($reason, Task::BLOCK_REASONS, true)) {
                    throw new TaskWorkflowException('اختر سبب التعطيل', 422);
                }
                $blockNote = trim((string) ($extra['note'] ?? ''));
                if ($reason === 'other' && $blockNote === '') {
                    throw new TaskWorkflowException('اكتب سبب التعطيل', 422);
                }
                $task->block_reason = $reason;
                $task->block_note = $blockNote ?: null;
                $note = $blockNote ?: $reason;
                break;

            case 'resume':
                $task->block_reason = null;
                $task->block_note = null;
                break;

            case 'submit':
                if ($task->requires_proof && $task->attachments()->count() === 0) {
                    throw new TaskWorkflowException('هذه المهمة تتطلب إثباتاً: أرفق ملفاً أو صورة أو نصاً أو رابطاً', 422);
                }
                $task->completed_at = now();
                // No review needed: the task is done
                if (!$task->requires_approval) {
                    $to = 'approved';
                    $task->approved_at = now();
                }
                break;

            case 'approve':
                $task->approved_at = now();
                $task->return_reason = null;
                break;

            case 'return':
                $reason = trim((string) ($extra['reason'] ?? ''));
                if ($reason === '') {
                    throw new TaskWorkflowException('اكتب سبب الإرجاع للتصحيح', 422);
                }
                $task->return_reason = $reason;
                $task->completed_at = null;
                $note = $reason;
                break;
        }

        $task->status = $to;
        DB::transaction(function () use ($task, $action, $previous, $to, $user, $note) {
            $task->save();
            self::record($task, $action === 'submit' && $to === 'approved' ? 'approved' : $action, $previous, $to, $user->id, $note);
        });

        return $task;
    }

    /** Add a proof attachment (file / image / text / link) and log it */
    public static function attach(Task $task, User $user, array $data): TaskAttachment
    {
        if ((int) $task->assignee_id !== (int) $user->id) {
            throw new TaskWorkflowException('الإثبات يرفعه المكلف بالمهمة', 403);
        }
        if (in_array($task->status, ['approved', 'in_review'], true)) {
            throw new TaskWorkflowException('لا يمكن إضافة مرفقات بعد إرسال المهمة للمراجعة', 422);
        }
        $attachment = $task->attachments()->create($data + ['uploaded_by' => $user->id]);
        self::record($task, 'attached', $task->status, $task->status, $user->id, $data['original_name'] ?? $data['url'] ?? $data['type']);
        return $attachment;
    }
}
