<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Question;
use App\Rules\ValidEvaluateeType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuestionnaireController extends Controller
{
    /**
     * Columns the sector list may be ordered by (sort_by/sort_dir query params).
     */
    private function categorySortable(): array
    {
        return [
            'category_name' => 'evaluation_categories.category_name',
            'category_name_tl' => 'evaluation_categories.category_name_tl',
            'weight' => 'evaluation_categories.weight',
            'created_at' => 'evaluation_categories.created_at',
        ];
    }

    /**
     * Columns the metric list may be ordered by, including a correlated
     * sub-query for the parent sector name.
     */
    private function questionSortable(): array
    {
        return [
            'question_text' => 'evaluation_questions.question_text',
            'question_text_tl' => 'evaluation_questions.question_text_tl',
            'category' => Category::select('category_name')->whereColumn('evaluation_questions.category_id', 'evaluation_categories.id'),
            'created_at' => 'evaluation_questions.created_at',
        ];
    }

    public function index(Request $request)
    {
        $settings = \App\Models\Setting::cachedAll();
        $activeSemester = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        $query = Category::withCount('questions')->with('questions');

        // Filter by evaluatee_type (default: faculty)
        $evaluateeType = $request->input('evaluatee_type', 'faculty');
        $query->where('evaluatee_type', $evaluateeType);

        if ($activeSemester) {
            $query->where('semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $query->where('academic_year', $activeAcademicYear);
        }

        $query->when($request->query('query'), function ($q, $search) {
            $q->where(function ($sq) use ($search) {
                $sq->where('category_name', 'like', "%{$search}%")
                    ->orWhere('category_name_tl', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        });

        $sortBy = $request->query('sort_by');
        $sortable = $this->categorySortable();

        if ($sortBy && isset($sortable[$sortBy])) {
            $direction = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sortable[$sortBy], $direction)->orderBy('evaluation_categories.id', $direction);
        }

        if ($request->has('paginate') && $request->paginate != 'false') {
            $perPage = min(max((int) $request->input('per_page', 4), 1), 100);
            return response()->json($query->paginate($perPage));
        }

        return response()->json($query->get());
    }

    public function stats(Request $request)
    {
        $settings = \App\Models\Setting::cachedAll();
        $activeSemester = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        $query = Category::with('questions');

        // Filter by evaluatee_type (default: faculty)
        $evaluateeType = $request->input('evaluatee_type', 'faculty');
        $query->where('evaluatee_type', $evaluateeType);

        if ($activeSemester) {
            $query->where('semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $query->where('academic_year', $activeAcademicYear);
        }

        $categories = $query->get();
        $totalWeight = $categories->sum('weight') * 100;
        $totalQuestions = $categories->sum(function($cat) {
            return $cat->questions->count();
        });

        return response()->json([
            'total_weight' => $totalWeight,
            'total_questions' => $totalQuestions,
            'total_categories' => $categories->count()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required',
            'reference' => 'nullable|string|max:255',
            'weight' => 'required|numeric|min:0|max:1',
            'evaluatee_type' => ['required', new ValidEvaluateeType()],
        ]);

        $settings = \App\Models\Setting::cachedAll();
        $data = $request->all();
        $data['semester'] = $settings->get('active_semester');
        $data['academic_year'] = $settings->get('active_academic_year');

        $category = Category::create($data);
        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'category_name' => 'required',
            'reference' => 'nullable|string|max:255',
            'weight' => 'required|numeric|min:0|max:1',
            'evaluatee_type' => ['sometimes', new ValidEvaluateeType()],
        ]);

        $category = Category::findOrFail($id);
        $category->update($request->only(['category_name', 'category_name_tl', 'reference', 'weight', 'evaluatee_type']));
        return response()->json($category);
    }

    public function questions(Request $request, $categoryId)
    {
        $query = Question::where('category_id', $categoryId);

        $sortBy = $request->query('sort_by');
        $sortable = $this->questionSortable();

        if ($sortBy && isset($sortable[$sortBy])) {
            $direction = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sortable[$sortBy], $direction)->orderBy('evaluation_questions.id', $direction);
        }

        if ($request->has('paginate') && $request->paginate != 'false') {
            $perPage = min(max((int) $request->input('per_page', 4), 1), 100);
            return response()->json($query->paginate($perPage));
        }

        return response()->json($query->get());
    }

    public function storeQuestion(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:evaluation_categories,id',
            'question_text' => 'required',
            'reference' => 'nullable|string|max:255',
        ]);

        $question = Question::create($request->all());
        return response()->json($question, 201);
    }

    public function updateQuestion(Request $request, $id)
    {
        $request->validate([
            'question_text' => 'required',
            'reference' => 'nullable|string|max:255',
        ]);

        $question = Question::findOrFail($id);
        $question->update($request->only(['question_text', 'question_text_tl', 'reference']));
        return response()->json($question);
    }

    public function destroy($id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(['message' => 'Questionnaire deleted successfully']);
    }

    public function destroyQuestion($id)
    {
        Question::findOrFail($id)->delete();
        return response()->json(['message' => 'Question deleted successfully']);
    }
}

