<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GoogleAuthController extends Controller
{
  /**
   * Redirect the user to the Google authentication page.
   */
  public function redirectToGoogle(Request $request)
  {
    $mode = $request->query('mode', 'login');

    /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
    $driver = Socialite::driver('google');

    $response = $driver
      ->stateless()
      ->redirect();

    return $response->withCookie(cookie('google_auth_mode', $mode, 10));
  }

  /**
   * Obtain the user information from Google.
   */
  public function handleGoogleCallback(Request $request)
  {
    try {
      $mode = $request->cookie('google_auth_mode') ?? 'login';

      /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
      $driver = Socialite::driver('google');
      $googleUser = $driver->stateless()->user();

      $email = $googleUser->getEmail();

      // Domain validation
      if (!str_ends_with(strtolower($email), '@neustcarranglan.ph.education')) {
        return redirect('/login?error=Access denied. Only @neustcarranglan.ph.education domain is allowed.');
      }

      // Find existing user by google_id or exact email
      $user = User::where('google_id', $googleUser->getId())
        ->orWhere('email', $email)
        ->first();

      $loginRedirect = "/login";
      $linkRedirect = "/dashboard";

      if ($user) {
        // If matching by email but google_id missing, auto-link it
        if (!$user->google_id) {
          $user->update([
            'google_id' => $googleUser->getId(),
            'google_email' => $email,
            'is_google_linked' => true,
          ]);
        }

        if (!$user->is_active) {
          return redirect($loginRedirect . '?error=Account deactivated');
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $userJson = urlencode(json_encode($user->load('roles', 'permissions', 'student', 'faculty')));
        $permsJson = urlencode(json_encode($user->getAllPermissions()->pluck('name')));

        // Redirect back to frontend with credentials
        return redirect($loginRedirect . "?token={$token}&user={$userJson}&permissions={$permsJson}");
      }

      // User does not exist, student must register
      $usernamePart = explode('@', $email)[0];
      $idNumber = str_ireplace('car', '', $usernamePart); // car12345 -> 12345

      $googleInfo = urlencode(json_encode([
        'id' => $googleUser->getId(),
        'email' => $email,
        'name' => $googleUser->getName(),
        'id_number' => $idNumber,
      ]));

      // If it was just a link request, we fallback
      if ($mode === 'link') {
        return redirect($linkRedirect . "?google_user={$googleInfo}");
      }

      return redirect($loginRedirect . "?requires_setup=true&google_user={$googleInfo}");

    } catch (\Exception $e) {
      Log::error('Google callback error: ' . $e->getMessage(), [
        'exception' => $e,
        'redirect_config' => config('services.google.redirect'),
      ]);
      $message = $e->getMessage();
      if (str_contains($message, 'redirect') || str_contains($message, '401') || str_contains($message, 'invalid_request')) {
        return redirect('/login?error=' . urlencode('Google authentication failed: redirect URI mismatch. Configured: ' . config('services.google.redirect') . '. Error: ' . $message));
      }
      return redirect('/login?error=' . urlencode('Google authentication failed: ' . $message));
    }
  }

  /**
   * Link Google account to the currently authenticated user.
   */
  public function linkGoogle(Request $request)
  {
    $request->validate([
      'google_id' => 'required|string',
      'google_email' => 'required|email',
    ]);

    $user = $request->user();

    if ($user->is_google_linked) {
      return response()->json(['message' => 'Account is already linked to a Google account.'], 422);
    }

    // Duplication Check: Is this Google account used by someone else?
    $existing = User::where('google_id', $request->google_id)->first();
    if ($existing) {
      return response()->json(['message' => 'This Google account is already linked to another user.'], 422);
    }

    $user->update([
      'google_id' => $request->google_id,
      'google_email' => $request->google_email,
      'is_google_linked' => true,
    ]);

    return response()->json([
      'message' => 'Google account linked successfully!',
      'user' => $user->load('roles', 'permissions', 'student', 'faculty'),
    ]);
  }

  /**
   * Unlink Google account.
   */
  public function unlinkGoogle(Request $request, $id = null)
  {
    // Admin can unlink by ID, users can unlink their own
    $user = $id ? User::findOrFail($id) : $request->user();

    if ($id && $request->user()->role !== 'admin' && $request->user()->id != $id) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    $user->update([
      'google_id' => null,
      'google_email' => null,
      'is_google_linked' => false,
    ]);

    return response()->json(['message' => 'Google account unlinked successfully.']);
  }

  /**
   * Finalize registration for a new student who signed in via Google.
   */
  public function registerStudent(Request $request)
  {
    $request->validate([
      'firstname' => 'required|string|max:255',
      'middlename' => 'nullable|string|max:255',
      'lastname' => 'required|string|max:255',
      'section' => 'nullable|string|max:255',
      'section_id' => 'nullable|string',
      'course' => 'nullable|string|max:255',
      'google_id' => 'required|string',
      'email' => 'required|email|unique:users,email',
    ]);

    $email = $request->email;
    if (!str_ends_with(strtolower($email), '@neustcarranglan.ph.education')) {
      return response()->json(['message' => 'Invalid domain. Only @neustcarranglan.ph.education domain is allowed.'], 403);
    }

    // Duplicate check for google_id just in case
    if (User::where('google_id', $request->google_id)->exists()) {
      return response()->json(['message' => 'Google account already associated with another user.'], 422);
    }

    $usernamePart = explode('@', $email)[0];
    $idNumber = str_ireplace('car', '', $usernamePart);

    DB::beginTransaction();
    try {
      $user = User::create([
        'id_number' => $idNumber,
        'email' => $email,
        'firstname' => $request->firstname,
        'lastname' => $request->lastname,
        'middlename' => $request->middlename,
        'role' => 'student',
        'google_id' => $request->google_id,
        'google_email' => $email,
        'is_google_linked' => true,
        'is_active' => true,
        'email_verified_at' => now(),
      ]);

      $user->assignRole('Student');

      $sectionName = $request->section;
      if (!$sectionName && $request->section_id) {
          $section = \App\Models\Section::find($request->section_id);
          if ($section) {
              $sectionName = $section->name;
          }
      }

      \App\Models\Student::create([
        'user_id' => $user->id,
        'course' => $request->course,
        'section' => $sectionName,
        'section_id' => $request->section_id,
      ]);

      DB::commit();

      $token = $user->createToken('auth_token')->plainTextToken;
      return response()->json([
        'message' => 'Registration successful.',
        'token' => $token,
        'user' => $user->load('roles', 'permissions', 'student', 'faculty'),
        'permissions' => $user->getAllPermissions()->pluck('name'),
      ]);

    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Student Google registration error: ' . $e->getMessage());
      return response()->json(['message' => 'Failed to register student. System error.'], 500);
    }
  }
}
