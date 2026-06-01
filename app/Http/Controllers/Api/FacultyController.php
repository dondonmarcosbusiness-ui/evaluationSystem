<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FacultyController extends Controller
{
    public function index(Request $request)
    {
        try {
            $faculty = Faculty::with('user')
                ->when($request->query('query'), function ($q, $search) {
                    $q->whereHas('user', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($request->query('department'), function ($q, $dept) {
                    $q->where('department', $dept);
                })
                ->paginate(10);

            return response()->json($faculty);
        } catch (\Exception $e) {
            Log::error('Faculty index error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'id_number' => 'required|string|unique:users',
            'email' => 'nullable|email|unique:users',
            'password' => 'nullable|string|min:6',
            'department' => 'required',
            'course' => 'required',
            'position' => 'required',
        ]);

        $idNumber = $request->id_number;
        $email = $request->email ?: "car{$idNumber}@neustcarranglan.ph.education";
        $password = $request->password ?: "password123";

        DB::beginTransaction();
        try {
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'middlename' => $request->middlename,
                'id_number' => $idNumber,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'faculty',
                'email_verified_at' => now(),
            ]);

            $user->assignRole('Faculty');

            $faculty = Faculty::create([
                'user_id' => $user->id,
                'department' => $request->department,
                'course' => $request->course,
                'position' => $request->position
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Faculty added successfully',
                'data' => $faculty->load('user')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Faculty store error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function show($id)
    {
        try {
            $faculty = Faculty::with('user')->findOrFail($id);
            return response()->json($faculty);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $faculty = Faculty::findOrFail($id);
        $user = $faculty->user;

        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'id_number' => 'required|string|unique:users,id_number,'.$user->id,
            'email' => 'required|email|unique:users,email,'.$user->id,
            'department' => 'required',
            'course' => 'required',
            'position' => 'required',
        ]);

        DB::beginTransaction();
        try {
            // Update user details
            $userData = [
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'middlename' => $request->middlename,
                'id_number' => $request->id_number,
                'email' => $request->email,
            ];

            if ($request->password) {
                $userData['password'] = Hash::make($request->password);
            }

            $user->update($userData);

            $faculty->update([
                'department' => $request->department,
                'course' => $request->course,
                'position' => $request->position
            ]);

            DB::commit();
            return response()->json(['message' => 'Faculty updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Faculty update error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $faculty = Faculty::findOrFail($id);
            $user = $faculty->user;
            $faculty->delete();
            $user->delete();
            return response()->json(['message' => 'Faculty deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            $faculty = Faculty::findOrFail($id);
            $user = $faculty->user;
            $user->is_active = !$user->is_active;
            $user->save();
            return response()->json([
                'message' => 'Status updated successfully',
                'is_active' => $user->is_active
            ]);
        } catch (\Exception $e) {
            Log::error('Faculty toggle active error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt|max:2048'
        ]);

        $file = $request->file('file');
        $csvData = file_get_contents($file);
        $rows = array_map('str_getcsv', explode("\n", $csvData));
        $header = array_shift($rows);

        // Normalize headers
        $header = array_map(function($val) { return strtolower(trim($val)); }, $header);
        $expected = ['id number', 'last name', 'first name', 'middle name', 'position', 'department', 'course'];
        
        $missing = array_diff($expected, $header);
        if (count($missing) > 0) {
            return response()->json([
                'message' => 'Invalid CSV format. Expected columns: ID Number, Last Name, First Name, Middle Name, Position, Department, Course'
            ], 400);
        }

        $imported = 0;
        $failed = 0;

        foreach ($rows as $row) {
            if (count($row) !== count($header)) continue;
            
            $data = array_combine($header, $row);
            
            $validator = Validator::make($data, [
                'id number' => 'required|unique:users,id_number',
                'last name' => 'required|string',
                'first name' => 'required|string',
                'position' => 'required',
                'department' => 'required',
                'course' => 'required'
            ]);

            if ($validator->fails()) {
                $failed++;
                continue;
            }

            DB::beginTransaction();
            try {
                $idNumber = trim($data['id number']);
                $lastName = trim($data['last name']);
                $firstName = trim($data['first name']);
                $middleName = trim($data['middle name'] ?? '');
                
                $fullName = $lastName . ', ' . $firstName . ($middleName ? ' ' . $middleName : '');
                $email = "car{$idNumber}@neustcarranglan.ph.education";

                $user = User::create([
                    'id_number' => $idNumber,
                    'lastname' => $lastName,
                    'firstname' => $firstName,
                    'middlename' => $middleName,
                    'name' => $fullName,
                    'email' => $email,
                    'password' => Hash::make($idNumber),
                    'role' => 'faculty',
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
                
                $user->assignRole('Faculty');

                Faculty::create([
                    'user_id' => $user->id,
                    'position' => trim($data['position']),
                    'department' => trim($data['department']),
                    'course' => trim($data['course'])
                ]);
                
                DB::commit();
                $imported++;
            } catch (\Exception $e) {
                DB::rollBack();
                $failed++;
            }
        }

        return response()->json([
            'message' => 'Import completed',
            'imported' => $imported,
            'failed' => $failed
        ]);
    }

    public function all()
    {
        try {
            $faculty = Faculty::with('user')->get();
            return response()->json($faculty);
        } catch (\Exception $e) {
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:faculty,id'
        ]);

        try {
            DB::beginTransaction();
            $currentUserId = $request->user()->id;
            $faculties = Faculty::with('user')->whereIn('id', $request->ids)->get();
            foreach ($faculties as $faculty) {
                if ($faculty->user && $faculty->user->id !== $currentUserId) {
                    $user = $faculty->user;
                    // Delete faculty first then the user
                    $faculty->delete();
                    $user->delete();
                }
            }
            DB::commit();
            return response()->json(['message' => 'Selected faculty deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function bulkToggleActive(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:faculty,id',
            'status' => 'required|boolean'
        ]);

        try {
            DB::beginTransaction();
            $currentUserId = $request->user()->id;
            $faculties = Faculty::with('user')->whereIn('id', $request->ids)->get();
            foreach ($faculties as $faculty) {
                if ($faculty->user && $faculty->user->id !== $currentUserId) {
                    $faculty->user->is_active = $request->status;
                    $faculty->user->save();
                }
            }
            DB::commit();
            return response()->json(['message' => 'Selected faculty status updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'System error'], 500);
        }
    }
}
