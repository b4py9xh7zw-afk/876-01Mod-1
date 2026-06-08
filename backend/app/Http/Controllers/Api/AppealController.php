<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppealRecord;
use App\Models\ExamRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppealController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isTeacher()) {
            $query = AppealRecord::with(['examRecord.examPaper', 'proctorEvent', 'user']);
        } else {
            $query = AppealRecord::with(['examRecord.examPaper', 'proctorEvent'])
                ->where('user_id', $user->id);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $appeals = $query->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'appeals' => $appeals,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'proctor_event_id' => 'nullable|exists:proctor_events,id',
            'explanation' => 'required|string|max:2000',
            'screenshots' => 'nullable|array|max:5',
            'screenshots.*' => 'string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $existingAppeal = AppealRecord::where('exam_record_id', $record->id)
            ->where('proctor_event_id', $request->proctor_event_id)
            ->where('status', AppealRecord::STATUS_PENDING)
            ->first();

        if ($existingAppeal) {
            return response()->json(['message' => '该异常已有待审核的申诉'], 422);
        }

        $appeal = AppealRecord::create([
            'exam_record_id' => $record->id,
            'proctor_event_id' => $request->proctor_event_id,
            'user_id' => $request->user()->id,
            'explanation' => $request->explanation,
            'screenshots' => $request->screenshots,
            'status' => AppealRecord::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => '申诉已提交',
            'appeal' => $appeal->load(['examRecord.examPaper', 'proctorEvent']),
        ], 201);
    }

    public function show(Request $request, AppealRecord $appeal)
    {
        $user = $request->user();
        if ($appeal->user_id !== $user->id && !$user->isAdmin() && !$user->isTeacher()) {
            return response()->json(['message' => '无权查看此申诉'], 403);
        }

        return response()->json([
            'appeal' => $appeal->load(['examRecord.examPaper', 'proctorEvent', 'user', 'reviewer']),
        ]);
    }

    public function review(Request $request, AppealRecord $appeal)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isTeacher()) {
            return response()->json(['message' => '无权审核申诉'], 403);
        }

        if ($appeal->status !== AppealRecord::STATUS_PENDING) {
            return response()->json(['message' => '该申诉已处理'], 422);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'review_comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $appeal->update([
            'status' => $request->status,
            'reviewer_id' => $user->id,
            'review_comment' => $request->review_comment,
            'reviewed_at' => now(),
        ]);

        if ($request->status === AppealRecord::STATUS_APPROVED) {
            $record = $appeal->examRecord;
            $record->update([
                'anomaly_status' => ExamRecord::ANOMALY_OVERRIDDEN,
            ]);
        }

        return response()->json([
            'message' => $request->status === AppealRecord::STATUS_APPROVED ? '申诉已通过，异常标记已改判' : '申诉已驳回',
            'appeal' => $appeal->fresh()->load(['examRecord.examPaper', 'proctorEvent', 'user', 'reviewer']),
        ]);
    }
}
