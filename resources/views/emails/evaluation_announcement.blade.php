<x-mail::message>
# Evaluation System is Now Open!

Hi {{ $studentName }},

We are pleased to inform you that the Faculty Evaluation System for the **{{ $semester }}** of Academic Year **{{ $academicYear }}** is now officially open.

Your feedback is crucial in maintaining and improving the quality of instruction at our institution. Please log in to your account and complete the evaluations for your assigned instructors.

<x-mail::button :url="config('app.url') . '/login'">
Log In to Evaluate
</x-mail::button>

Thank you for your participation!

Best regards,<br>
{{ config('app.name') }}
</x-mail::message>
