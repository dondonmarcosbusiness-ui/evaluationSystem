<x-mail::message>
@if ($event === 'open')
# Evaluation is Now Open!

Hi {{ $studentName }},

The Faculty Evaluation window for **{{ implode(', ', $departments) }}** is now open for the **{{ $semester }}** of Academic Year **{{ $academicYear }}**.

Your feedback is crucial in maintaining and improving the quality of instruction at our institution. Please log in to your account and complete the evaluations for your assigned instructors.

<x-mail::button :url="config('app.url') . '/login'">
Log In to Evaluate
</x-mail::button>
@else
# Evaluation Window has Closed

Hi {{ $studentName }},

The Faculty Evaluation window for **{{ implode(', ', $departments) }}** has now closed for the **{{ $semester }}** of Academic Year **{{ $academicYear }}**.

If you were unable to submit your evaluations during the window, please contact the office handling the evaluation period.

<x-mail::button :url="config('app.url') . '/login'">
Log In
</x-mail::button>
@endif

Thank you for your participation!

Best regards,<br>
{{ config('app.name') }}
</x-mail::message>
