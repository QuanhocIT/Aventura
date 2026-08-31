<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\MediaCleanupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GarbageCollectorController extends Controller
{
    public function __construct(protected MediaCleanupService $mediaCleanup) {}

    protected function getOrphansQuery(bool $respectGracePeriod = true)
    {
        return $this->mediaCleanup->orphanQuery(null, $respectGracePeriod);
    }

    public function index(Request $request): Response
    {
        $orphansQuery = $this->getOrphansQuery();

        $totalCount = (clone $orphansQuery)->count();
        $totalBytes = (clone $orphansQuery)->sum('size_bytes');
        $totalMb = round($totalBytes / (1024 * 1024), 2);

        $orphans = $orphansQuery->with('restaurant')
            ->latest()
            ->paginate(10)
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
            ],
        ]);
    }

    public function cleanup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'all' => 'nullable|boolean',
        ]);

        if ($request->boolean('all')) {
            $query = $this->getOrphansQuery();
        } else {
            $query = $this->getOrphansQuery()->whereIn('id', $request->input('ids', []));
        }

        $count = 0;
        $freedBytes = 0;

        foreach ($query->cursor() as $asset) {
            try {
                if ($asset->file_path && Storage::disk($asset->disk)->exists($asset->file_path)) {
                    Storage::disk($asset->disk)->delete($asset->file_path);
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to delete physical file {$asset->file_path} on disk {$asset->disk}: ".$e->getMessage());
            }

            $freedBytes += $asset->size_bytes;
            $asset->forceDelete();
            $count++;
        }

        $freedMb = round($freedBytes / (1024 * 1024), 2);

        return back()->with('success', "Đã dọn dẹp thành công {$count} tệp mồ côi, giải phóng {$freedMb} MB bộ nhớ.");
    }
}
