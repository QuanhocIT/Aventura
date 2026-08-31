<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\RestaurantFollowup;
use App\Models\RestaurantInternalNote;
use App\Models\RestaurantTag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestaurantCrmController extends Controller
{
    public function storeNote(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $note = $restaurant->internalNotes()->create([
            'user_id' => $request->user()->id,
            'note' => $validated['note'],
        ]);

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'created',
            'action' => 'crm_note_create',
            'subject_type' => RestaurantInternalNote::class,
            'subject_id' => $note->id,
            'new_values' => ['note' => $validated['note']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Đã lưu ghi chú nội bộ.');
    }

    public function destroyNote(Restaurant $restaurant, RestaurantInternalNote $note): RedirectResponse
    {
        // Ensure the note belongs to the restaurant
        if ($note->restaurant_id !== $restaurant->id) {
            abort(403);
        }

        $noteId = $note->id;
        $noteText = $note->note;
        $note->delete();

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => auth()->id(),
            'user_role' => 'admin',
            'event' => 'deleted',
            'action' => 'crm_note_delete',
            'subject_type' => RestaurantInternalNote::class,
            'subject_id' => $noteId,
            'old_values' => ['note' => $noteText],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Đã xóa ghi chú nội bộ.');
    }

    public function storeTag(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'max:30'],
        ]);

        // Prevent duplicates
        $tag = $restaurant->tags()->updateOrCreate(
            ['name' => $validated['name']],
            ['color' => $validated['color']]
        );

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'created',
            'action' => 'crm_tag_create',
            'subject_type' => RestaurantTag::class,
            'subject_id' => $tag->id,
            'new_values' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Đã gắn nhãn tenant.');
    }

    public function destroyTag(Restaurant $restaurant, RestaurantTag $tag): RedirectResponse
    {
        if ($tag->restaurant_id !== $restaurant->id) {
            abort(403);
        }

        $tagId = $tag->id;
        $tagName = $tag->name;
        $tag->delete();

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => auth()->id(),
            'user_role' => 'admin',
            'event' => 'deleted',
            'action' => 'crm_tag_delete',
            'subject_type' => RestaurantTag::class,
            'subject_id' => $tagId,
            'old_values' => ['name' => $tagName],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Đã gỡ nhãn.');
    }

    public function storeFollowup(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:255'],
            'remind_at' => ['required', 'date', 'after:now'],
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $assignee = User::query()
            ->whereKey($validated['assigned_to'])
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                'super_admin', 'system_admin', 'billing_admin', 'support_specialist', 'admin',
            ]))
            ->exists();

        if (! $assignee) {
            return back()->withErrors(['assigned_to' => 'Chỉ được phân công cho nhân sự platform đang hoạt động.']);
        }

        $followup = $restaurant->followups()->create([
            'note' => $validated['note'],
            'remind_at' => $validated['remind_at'],
            'assigned_to' => $validated['assigned_to'],
            'status' => 'pending',
        ]);

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'created',
            'action' => 'crm_followup_create',
            'subject_type' => RestaurantFollowup::class,
            'subject_id' => $followup->id,
            'new_values' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Đã đặt lịch hẹn chăm sóc.');
    }

    public function completeFollowup(Restaurant $restaurant, RestaurantFollowup $followup): RedirectResponse
    {
        if ($followup->restaurant_id !== $restaurant->id) {
            abort(403);
        }

        $followup->update(['status' => 'completed']);

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => auth()->id(),
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'crm_followup_complete',
            'subject_type' => RestaurantFollowup::class,
            'subject_id' => $followup->id,
            'new_values' => ['status' => 'completed'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Đã đánh dấu hoàn thành cuộc hẹn.');
    }
}
