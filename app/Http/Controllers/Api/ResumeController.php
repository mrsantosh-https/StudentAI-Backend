<?php

namespace App\Http\Controllers\Api;

use App\Models\Resume;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ResumeController extends Controller
{
    public function index(Request $request)
    {
        $resumes = Resume::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'resumes' => $resumes,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateResume($request);

            $resume = Resume::create([
                'user_id' => $request->user()->id,
                ...$this->prepareResumeData($validated),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Resume saved successfully.',
                'resume' => $resume,
            ], 201);
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            Log::error('Resume store error', [
                'message' => $error->getMessage(),
                'trace' => $error->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, Resume $resume)
    {
        $this->authorizeResume($request, $resume);

        return response()->json([
            'success' => true,
            'resume' => $resume,
        ]);
    }

    public function update(Request $request, Resume $resume)
    {
        try {
            $this->authorizeResume($request, $resume);

            $validated = $this->validateResume($request);

            $resume->update($this->prepareResumeData($validated));

            return response()->json([
                'success' => true,
                'message' => 'Resume updated successfully.',
                'resume' => $resume->fresh(),
            ]);
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            Log::error('Resume update error', [
                'message' => $error->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, Resume $resume)
    {
        $this->authorizeResume($request, $resume);

        $resume->delete();

        return response()->json([
            'success' => true,
            'message' => 'Resume deleted successfully.',
        ]);
    }

    private function validateResume(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'fullName' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'linkedin' => ['nullable', 'url', 'max:500'],
            'github' => ['nullable', 'url', 'max:500'],
            'portfolio' => ['nullable', 'url', 'max:500'],
            'careerObjective' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'education' => ['nullable', 'string'],
            'skills' => ['nullable', 'string'],
            'projects' => ['nullable', 'string'],
            'experience' => ['nullable', 'string'],
        ]);
    }

    private function prepareResumeData(array $validated): array
    {
        return [
            'title' => $validated['title'] ?? 'My Resume',
            'full_name' => $validated['fullName'],
            'designation' => $validated['designation'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'linkedin' => $validated['linkedin'] ?? null,
            'github' => $validated['github'] ?? null,
            'portfolio' => $validated['portfolio'] ?? null,
            'career_objective' => $validated['careerObjective'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'education' => $validated['education'] ?? null,
            'skills' => $validated['skills'] ?? null,
            'projects' => $validated['projects'] ?? null,
            'experience' => $validated['experience'] ?? null,
        ];
    }

    private function authorizeResume(Request $request, Resume $resume): void
    {
        abort_if(
            $resume->user_id !== $request->user()->id,
            403,
            'You are not allowed to access this resume.'
        );
    }
}