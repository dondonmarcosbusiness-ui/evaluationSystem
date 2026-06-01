<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $students = User::with('student.section_relationship')
                ->where('role', 'student')
                ->when($request->query('query'), function ($q, $search) {
                    $q->where(function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($request->query('course'), function ($q, $course) {
                    $q->whereHas('student', fn($sq) => $sq->where('course', $course));
                })
                ->when($request->query('section_id'), function ($q, $sectionId) {
                    $section = \App\Models\Section::find($sectionId);
                    $q->whereHas('student', function($sq) use ($sectionId, $section) {
                        $sq->where('section_id', $sectionId);
                        if ($section) {
                            $sq->orWhere('section', $section->name)
                               ->orWhere('section', 'like', "%{$section->name}%");
                        }
                    });
                })
                ->when($request->query('student_type'), function ($q, $type) {
                    $q->whereHas('student', function ($sq) use ($type) {
                        if ($type === 'regular') {
                            $sq->where(function ($sub) {
                                $sub->where('student_type', 'regular')
                                    ->orWhereNull('student_type')
                                    ->orWhere('student_type', '');
                            });
                        } else {
                            $sq->where('student_type', $type);
                        }
                    });
                })
                ->orderBy('name')
                ->paginate(10);

            return response()->json($students);
        } catch (\Exception $e) {
            Log::error('Student index error: ' . $e->getMessage());
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
            'course' => 'required',
            'section' => 'nullable',
            'section_id' => 'required|exists:sections,id',
            'student_type' => 'required|in:regular,irregular',
        ]);

        $idNumber = $request->id_number;
        $email = $request->email ?: "car{$idNumber}@neustcarranglan.ph.education";
        $password = $request->password ?: "password123";

        try {
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'middlename' => $request->middlename,
                'id_number' => $idNumber,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'student',
                'email_verified_at' => now(),
            ]);

            $sectionName = $request->section;
            if (!$sectionName && $request->section_id) {
                $sec = \App\Models\Section::find($request->section_id);
                if ($sec) $sectionName = $sec->name;
            }

            $user->student()->create([
                'course' => $request->course,
                'section' => $sectionName,
                'section_id' => $request->section_id,
                'student_type' => $request->student_type,
            ]);

            $user->assignRole('Student');

            return response()->json([
                'message' => 'Student added successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Student store error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function show($id)
    {
        try {
            $student = User::with('student')->where('role', 'student')->findOrFail($id);
            return response()->json($student);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'id_number' => 'required|string|unique:users,id_number,'.$id,
            'email' => 'required|email|unique:users,email,'.$id,
            'course' => 'required',
            'section' => 'nullable',
            'section_id' => 'required|exists:sections,id',
            'student_type' => 'required|in:regular,irregular',
        ]);

        try {
            $user = User::where('role', 'student')->findOrFail($id);

            $user->update([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'middlename' => $request->middlename,
                'id_number' => $request->id_number,
                'email' => $request->email,
            ]);

            $sectionName = $request->section;
            if (!$sectionName && $request->section_id) {
                $sec = \App\Models\Section::find($request->section_id);
                if ($sec) $sectionName = $sec->name;
            }

            $user->student()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'course' => $request->course,
                    'section' => $sectionName,
                    'section_id' => $request->section_id,
                    'student_type' => $request->student_type,
                ]
            );
            if ($request->email && $request->email !== $user->email) {
                $request->validate(['email' => 'unique:users']);
                $user->update(['email' => $request->email]);
            }
            if ($request->password) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            return response()->json(['message' => 'Student updated successfully']);
        } catch (\Exception $e) {
            Log::error('Student update error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::where('role', 'student')->findOrFail($id);
            $user->delete();
            return response()->json(['message' => 'Student deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            $user = User::where('role', 'student')->findOrFail($id);
            $user->is_active = !$user->is_active;
            $user->save();
            return response()->json([
                'message' => 'Status updated successfully',
                'is_active' => $user->is_active
            ]);
        } catch (\Exception $e) {
            Log::error('Student toggle active error: ' . $e->getMessage());
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
        $expected = ['id number', 'last name', 'first name', 'middle name', 'course', 'section'];
        
        $missing = array_diff($expected, $header);
        if (count($missing) > 0) {
            return response()->json([
                'message' => 'Invalid CSV format. Expected columns: ID Number, Last Name, First Name, Middle Name, Course, Section'
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
                'course' => 'required',
                'section' => 'required'
            ]);

            if ($validator->fails()) {
                $failed++;
                continue;
            }

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
                    'role' => 'student',
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);

                $courseName = trim($data['course']);
                $sectionName = trim($data['section']);
                
                // Try to find matching section_id
                $sectionId = null;
                $matchingCourse = \App\Models\Course::where('name', $courseName)->first();
                if ($matchingCourse) {
                    $matchingSection = \App\Models\Section::where('course_id', $matchingCourse->id)
                        ->where('name', $sectionName)
                        ->first();
                    if ($matchingSection) {
                        $sectionId = $matchingSection->id;
                    }
                }

                $user->student()->create([
                    'course' => $courseName,
                    'section' => $sectionName,
                    'section_id' => $sectionId,
                ]);

                $user->assignRole('Student');
                $imported++;
            } catch (\Exception $e) {
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
            $students = User::with('student.section_relationship')->where('role', 'student')->orderBy('name')->get();
            return response()->json($students);
        } catch (\Exception $e) {
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id'
        ]);

        try {
            DB::beginTransaction();
            $users = User::where('role', 'student')->whereIn('id', $request->ids)->get();
            foreach ($users as $user) {
                if ($user->id !== $request->user()->id) {
                    $user->delete();
                }
            }
            DB::commit();
            return response()->json(['message' => 'Selected students deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function bulkToggleActive(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
            'status' => 'required|boolean'
        ]);

        try {
            DB::beginTransaction();
            $users = User::where('role', 'student')->whereIn('id', $request->ids)->get();
            foreach ($users as $user) {
                if ($user->id !== $request->user()->id) {
                    $user->is_active = $request->status;
                    $user->save();
                }
            }
            DB::commit();
            return response()->json(['message' => 'Selected student statuses updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'System error'], 500);
        }
    }
}
