<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OfficePersonnel;
use App\Models\QrCode;
use App\Http\Requests\StoreOfficeRequest;
use App\Http\Requests\UpdateOfficeRequest;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfficeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Office::withCount('feedbacks')
                ->when($request->query('query'), function ($q, $search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('office_head', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%");
                })
                ->when($request->has('active_only'), function ($q) {
                    $q->where('is_active', true);
                });

            if ($request->query('paginate') === 'false') {
                return response()->json($query->get());
            }

            return response()->json($query->paginate(10));
        } catch (\Exception $e) {
            Log::error('Office index error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function store(StoreOfficeRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $office = Office::create($validated);

            $token = QrCode::generateToken();
            $office->qrCode()->create([
                'qr_token' => $token,
                'is_active' => true,
            ]);

            AuditLogService::log('office.created', $office, [], $office->toArray(), $request);

            DB::commit();
            return response()->json([
                'message' => 'Office created successfully',
                'data' => $office->load('qrCode'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Office store error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function show($id)
    {
        try {
            $office = Office::with(['personnel.user', 'qrCode', 'feedbacks' => fn($q) => $q->latest()->limit(20)])->findOrFail($id);
            return response()->json($office);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not found'], 404);
        }
    }

    public function update(UpdateOfficeRequest $request, $id)
    {
        $office = Office::findOrFail($id);
        $oldValues = $office->toArray();
        $validated = $request->validated();

        try {
            $office->update($validated);

            AuditLogService::log('office.updated', $office, $oldValues, $office->fresh()->toArray(), $request);

            return response()->json([
                'message' => 'Office updated successfully',
                'data' => $office,
            ]);
        } catch (\Exception $e) {
            Log::error('Office update error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $office = Office::findOrFail($id);
            $oldValues = $office->toArray();
            $office->delete();

            AuditLogService::log('office.deleted', null, $oldValues, [], request());

            return response()->json(['message' => 'Office deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Office destroy error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            $office = Office::findOrFail($id);
            $oldValues = ['is_active' => $office->is_active];
            $office->is_active = !$office->is_active;
            $office->save();

            AuditLogService::log('office.toggled', $office, $oldValues, ['is_active' => $office->is_active], request());

            return response()->json([
                'message' => 'Status updated successfully',
                'is_active' => $office->is_active,
            ]);
        } catch (\Exception $e) {
            Log::error('Office toggle active error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function storePersonnel(Request $request, $officeId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'position' => 'nullable|string|max:255',
        ]);

        try {
            $personnel = OfficePersonnel::updateOrCreate(
                ['office_id' => $officeId, 'user_id' => $request->user_id],
                ['position' => $request->position]
            );

            return response()->json([
                'message' => 'Personnel added successfully',
                'data' => $personnel->load('user'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Office personnel store error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function destroyPersonnel($officeId, $personnelId)
    {
        try {
            OfficePersonnel::where('office_id', $officeId)->where('id', $personnelId)->delete();
            return response()->json(['message' => 'Personnel removed successfully']);
        } catch (\Exception $e) {
            Log::error('Office personnel destroy error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function all()
    {
        return response()->json(Office::active()->get());
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'uuid']);

        try {
            Office::whereIn('id', $request->ids)->delete();
            return response()->json(['message' => 'Offices deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Office bulk destroy error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function bulkToggleActive(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid',
            'status' => 'required|boolean',
        ]);

        try {
            Office::whereIn('id', $request->ids)->update(['is_active' => $request->status]);
            return response()->json(['message' => 'Status updated successfully']);
        } catch (\Exception $e) {
            Log::error('Office bulk toggle error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }
}
