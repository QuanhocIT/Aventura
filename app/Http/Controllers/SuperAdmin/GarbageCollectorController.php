<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GarbageCollectorController extends Controller
{
    protected function getOrphansQuery()
    {
        $types = MediaAsset::whereNotNull('attachable_type')->distinct()->pluck('attachable_type');

        return MediaAsset::query()->where(function($q) use ($types) {
            $q->whereNull('attachable_id')
              ->orWhereNull('attachable_type');

            foreach ($types as $type) {
                $className = Relation::getMorphedModel($type) ?? $type;
                if (class_exists($className)) {
                    $model = new $className;
                    $table = $model->getTable();
                    $q->orWhere(function($sub) use ($type, $table) {
                        $sub->where('attachable_type', $type)
                            ->whereNotExists(function($existsQuery) use ($table) {
                                $existsQuery->select(DB::raw(1))
                                    ->from($table)
                                    ->whereColumn("{$table}.id", 'media_assets.attachable_id');
                            });
                    });
                }
            }
        });
    }

    public function index(Request $request): Response
    {
        $orphansQuery = $this->getOrphansQuery();

        $totalCount = (clone $orphansQuery)->count();
        $totalBytes = (clone $orphansQuery)->sum('size_bytes');
        $totalMb = round($totalBytes / (1024 * 1024), 2);

        $orphans = $orphansQuery->with('restaurant')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('super-admin/GarbageCollector', [
            'orphans' => $orphans->through(fn ($item) => [
                'id' => $item->id,
                'file_name' => $item->file_name,
                'file_path' => $item->file_path,
                'file_url' => Storage::disk($item->disk)->url($item->file_path),
                'disk' => $item->disk,
                'media_type' => $item->media_type,
                'mime_type' => $item->mime_type,
                'size_bytes' => $item->size_bytes,
                'size_mb' => round($item->size_bytes / (1024 * 1024), 2),
                'attachable_type' => $item->attachable_type,
                'attachable_id' => $item->attachable_id,
                'restaurant_name' => $item->restaurant?->name ?? 'Hệ thống (Global)',
                'restaurant_code' => $item->restaurant?->code,
                'created_at' => $item->created_at->format('d/m/Y H:i'),
            ]),
            'stats' => [
                'total_count' => $totalCount,
                'total_mb' => $totalMb,
                'default_disk' => config('filesystems.default', 'public'),
            ]
        ]);
    }

    public function cleanup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'all' => 'nullable|boolean',
        ]);

        $query = MediaAsset::query();

        if ($request->boolean('all')) {
            $query = $this->getOrphansQuery();
        } else {
            $query->whereIn('id', $request->input('ids', []));
        }

        $count = 0;
        $freedBytes = 0;

        foreach ($query->cursor() as $asset) {
            try {
                if ($asset->file_path && Storage::disk($asset->disk)->exists($asset->file_path)) {
                    Storage::disk($asset->disk)->delete($asset->file_path);
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to delete physical file {$asset->file_path} on disk {$asset->disk}: " . $e->getMessage());
            }

            $freedBytes += $asset->size_bytes;
            $asset->forceDelete();
            $count++;
        }

        $freedMb = round($freedBytes / (1024 * 1024), 2);

        return back()->with('success', "Đã dọn dẹp thành công {$count} tệp mồ côi, giải phóng {$freedMb} MB bộ nhớ.");
    }
}
