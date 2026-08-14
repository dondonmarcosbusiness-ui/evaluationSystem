<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = QrCode::with('office')
                ->when($request->query('office_id'), function ($q, $officeId) {
                    $q->where('office_id', $officeId);
                });

            return response()->json($query->get());
        } catch (\Exception $e) {
            Log::error('QR code index error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function generate(Request $request)
    {
        $request->validate([
            'office_id' => 'required|exists:offices,id',
        ]);

        try {
            $existing = QrCode::where('office_id', $request->office_id)->first();
            if ($existing) {
                $existing->update([
                    'qr_token' => Str::random(32),
                    'is_active' => true,
                ]);
                return response()->json([
                    'message' => 'QR code regenerated successfully',
                    'data' => $existing->fresh('office'),
                ]);
            }

            $qr = QrCode::create([
                'office_id' => $request->office_id,
                'qr_token' => Str::random(32),
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'QR code generated successfully',
                'data' => $qr->load('office'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('QR code generate error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function regenerate($id)
    {
        try {
            $qr = QrCode::findOrFail($id);
            $qr->update([
                'qr_token' => Str::random(32),
                'is_active' => true,
            ]);
            return response()->json([
                'message' => 'QR code regenerated successfully',
                'data' => $qr->fresh('office'),
            ]);
        } catch (\Exception $e) {
            Log::error('QR code regenerate error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function showByToken($token)
    {
        try {
            $qr = QrCode::with('office')->where('qr_token', $token)->where('is_active', true)->firstOrFail();
            return response()->json([
                'office' => $qr->office,
                'qr' => $qr,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid or inactive QR code'], 404);
        }
    }
}
