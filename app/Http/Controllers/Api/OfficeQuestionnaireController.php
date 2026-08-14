<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfficeCategory;
use App\Models\OfficeQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OfficeQuestionnaireController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = OfficeCategory::withCount('questions')->with('questions');

            if ($request->has('paginate') && $request->paginate != 'false') {
                $perPage = $request->input('per_page', 4);
                return response()->json($query->paginate($perPage));
            }

            return response()->json($query->get());
        } catch (\Exception $e) {
            Log::error('Office questionnaire index error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function stats()
    {
        try {
            $categories = OfficeCategory::with('questions')->get();
            $totalWeight = $categories->sum('weight') * 100;
            $totalQuestions = $categories->sum(fn($cat) => $cat->questions->count());

            return response()->json([
                'total_weight' => $totalWeight,
                'total_questions' => $totalQuestions,
                'total_categories' => $categories->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Office questionnaire stats error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0|max:1',
        ]);

        try {
            $category = OfficeCategory::create($request->only('category_name', 'weight'));
            return response()->json($category, 201);
        } catch (\Exception $e) {
            Log::error('Office category store error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'category_name' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0|max:1',
        ]);

        try {
            $category = OfficeCategory::findOrFail($id);
            $category->update($request->only('category_name', 'weight'));
            return response()->json($category);
        } catch (\Exception $e) {
            Log::error('Office category update error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            OfficeCategory::findOrFail($id)->delete();
            return response()->json(['message' => 'Category deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Office category destroy error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function questions(Request $request, $categoryId)
    {
        try {
            $query = OfficeQuestion::where('category_id', $categoryId);

            if ($request->has('paginate') && $request->paginate != 'false') {
                return response()->json($query->paginate($request->input('per_page', 4)));
            }

            return response()->json($query->get());
        } catch (\Exception $e) {
            Log::error('Office questions error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function storeQuestion(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:office_categories,id',
            'question_text' => 'required|string',
        ]);

        try {
            $question = OfficeQuestion::create($request->only('category_id', 'question_text'));
            return response()->json($question, 201);
        } catch (\Exception $e) {
            Log::error('Office question store error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function updateQuestion(Request $request, $id)
    {
        $request->validate([
            'question_text' => 'required|string',
        ]);

        try {
            $question = OfficeQuestion::findOrFail($id);
            $question->update($request->only('question_text'));
            return response()->json($question);
        } catch (\Exception $e) {
            Log::error('Office question update error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function destroyQuestion($id)
    {
        try {
            OfficeQuestion::findOrFail($id)->delete();
            return response()->json(['message' => 'Question deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Office question destroy error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }
}
