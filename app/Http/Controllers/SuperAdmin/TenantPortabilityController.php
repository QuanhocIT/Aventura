<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\TenantDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantPortabilityController extends Controller
{
    public function __construct(private readonly TenantDataService $dataService) {}

    /**
     * Export restaurant data to a downloadable JSON file.
     */
    public function export(Restaurant $restaurant): Response
    {
        $data = $this->dataService->export($restaurant);
        $fileName = 'aventura_export_' . $restaurant->code . '_' . date('Ymd_His') . '.json';
        
        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Create a clone of the restaurant in Sandbox mode.
     */
    public function createSandbox(Restaurant $restaurant): RedirectResponse
    {
        try {
            $sandbox = $this->dataService->cloneSandbox($restaurant);
            
            return redirect()->back()->with('flash', [
                'success' => "Đã nhân bản sandbox thành công: '{$sandbox->name}' (Mã: {$sandbox->code}). Tài khoản nhân viên sandbox được reset mật khẩu về: password123"
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'error' => 'Lỗi khi tạo bản sao Sandbox: ' . $e->getMessage()
            ]);
        }
    }
}
