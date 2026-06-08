<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamRecord;
use App\Models\ProctorEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProctorEventController extends Controller
{
    public function timeline(Request $request, ExamRecord $record)
    {
        if ($record->user_id !== $request->user()->id && !$request->user()->isAdmin() && !$request->user()->isTeacher()) {
            return response()->json(['message' => '无权查看此记录'], 403);
        }

        $events = ProctorEvent::where('exam_record_id', $record->id)
            ->orderBy('event_time', 'asc')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'event_type_label' => ProctorEvent::TYPES[$event->event_type] ?? $event->event_type,
                    'event_time' => $event->event_time->toIso8601String(),
                    'detail' => $event->detail,
                    'has_appeal' => $event->appeals()->exists(),
                    'appeal' => $event->appeals()->latest()->first(),
                ];
            });

        return response()->json([
            'record' => $record->load('examPaper'),
            'events' => $events,
        ]);
    }

    public function report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'event_type' => 'required|in:' . implode(',', array_keys(ProctorEvent::TYPES)),
            'event_time' => 'required|date',
            'detail' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $event = ProctorEvent::create([
            'exam_record_id' => $record->id,
            'event_type' => $request->event_type,
            'event_time' => $request->event_time,
            'detail' => $request->detail,
        ]);

        $record->update([
            'has_anomaly' => true,
            'anomaly_status' => ExamRecord::ANOMALY_FLAGGED,
        ]);

        return response()->json([
            'message' => '事件已记录',
            'event' => $event,
        ], 201);
    }

    public function batchReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'events' => 'required|array',
            'events.*.event_type' => 'required|in:' . implode(',', array_keys(ProctorEvent::TYPES)),
            'events.*.event_time' => 'required|date',
            'events.*.detail' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $created = [];
        foreach ($request->events as $eventData) {
            $created[] = ProctorEvent::create([
                'exam_record_id' => $record->id,
                'event_type' => $eventData['event_type'],
                'event_time' => $eventData['event_time'],
                'detail' => $eventData['detail'] ?? null,
            ]);
        }

        if (count($created) > 0) {
            $record->update([
                'has_anomaly' => true,
                'anomaly_status' => ExamRecord::ANOMALY_FLAGGED,
            ]);
        }

        return response()->json([
            'message' => '批量事件已记录',
            'count' => count($created),
        ], 201);
    }
}
