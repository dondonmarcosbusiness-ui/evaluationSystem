<?php
 
namespace App\Http\Controllers\Api;
 
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
 
class StaffController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Staff::with('user')
                ->when($request->query('query'), function ($q, $search) {
                    $q->whereHas('user', function ($sq) use ($search) {
                        $sq->where('firstname', 'like', "%{$search}%")
                           ->orWhere('lastname', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%");
                })
                ->when($request->query('department'), function ($q, $dept) {
                    $q->where('department', $dept);
                });
 
            if ($request->query('paginate') === 'false') {
                return response()->json($query->get());
            }
 
            return response()->json($query->paginate(10));
        } catch (\Exception $e) {
            Log::error('Staff index error: ' . $e->getMessage());
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
            'department' => 'nullable|string|max:255',
            'designation' => 'required|string|max:255',
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
                'role' => 'staff',
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
 
            $user->assignRole('Staff');
 
            $staff = Staff::create([
                'user_id' => $user->id,
                'department' => $request->department,
                'designation' => $request->designation
            ]);
 
            DB::commit();
            return response()->json([
                'message' => 'Staff created successfully',
                'data' => $staff->load('user'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff store error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }
 
    public function show($id)
    {
        try {
            $staff = Staff::with('user')->findOrFail($id);
            return response()->json($staff);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not found'], 404);
        }
    }
 
    public function update(Request $request, $id)
    {
        $staff = Staff::findOrFail($id);
        $user = $staff->user;
 
        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'id_number' => 'required|string|unique:users,id_number,'.$user->id,
            'email' => 'required|email|unique:users,email,'.$user->id,
            'department' => 'nullable|string|max:255',
            'designation' => 'required|string|max:255',
        ]);
 
        DB::beginTransaction();
        try {
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
 
            $staff->update([
                'department' => $request->department,
                'designation' => $request->designation
            ]);
 
            DB::commit();
            return response()->json([
                'message' => 'Staff updated successfully',
                'data' => $staff->load('user')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff update error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }
 
    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $staff = Staff::findOrFail($id);
            $user = $staff->user;
            $staff->delete();
            if ($user) {
                $user->delete();
            }
            DB::commit();
            return response()->json(['message' => 'Staff deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff destroy error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }
 
    public function toggleActive($id)
    {
        try {
            $staff = Staff::findOrFail($id);
            $user = $staff->user;
            $user->is_active = !$user->is_active;
            $user->save();
            return response()->json([
                'message' => 'Status updated successfully',
                'is_active' => $user->is_active
            ]);
        } catch (\Exception $e) {
            Log::error('Staff toggle active error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }
 
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:staff,id'
        ]);
 
        try {
            DB::beginTransaction();
            $currentUserId = $request->user()->id;
            $staffMembers = Staff::with('user')->whereIn('id', $request->ids)->get();
            foreach ($staffMembers as $staff) {
                if ($staff->user && $staff->user->id !== $currentUserId) {
                    $user = $staff->user;
                    $staff->delete();
                    $user->delete();
                }
            }
            DB::commit();
            return response()->json(['message' => 'Selected staff deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'System error'], 500);
        }
    }
 
    public function bulkToggleActive(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:staff,id',
            'status' => 'required|boolean'
        ]);
 
        try {
            DB::beginTransaction();
            $currentUserId = $request->user()->id;
            $staffMembers = Staff::with('user')->whereIn('id', $request->ids)->get();
            foreach ($staffMembers as $staff) {
                if ($staff->user && $staff->user->id !== $currentUserId) {
                    $staff->user->is_active = $request->status;
                    $staff->user->save();
                }
            }
            DB::commit();
            return response()->json(['message' => 'Selected staff status updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'System error'], 500);
        }
    }
}
