<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\QuickSurveyResponse;

class QuickSurveyResponseController extends Controller
{
    /**
     * Submit quick survey response
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitQuickSurvey(Request $request)
    {
        $request->validate([
            'selected_options' => 'required|array',
            'selected_options.*' => 'string|max:255',
            'other_response' => 'nullable|string|max:255',
        ]);

        // Use the default guard
        $alumniId = Auth::guard('api')->id();


        if (!$alumniId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Save or update quick survey response
        QuickSurveyResponse::updateOrCreate(
            ['alumni_id' => $alumniId],
            [
                'selected_options' => json_encode($request->selected_options),
                'other_response' => $request->other_response,
            ]
        );

        return response()->json(['message' => 'Quick survey submitted successfully!'], 200);
    }


    /**
     * See quick survey response
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkStatus()
    {
        $alumniId = Auth::id();

        if (!$alumniId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $quickSurvey = QuickSurveyResponse::where('alumni_id', $alumniId)->first();

        if ($quickSurvey) {
            return response()->json([
                'answered' => true,
                'selected_options' => json_decode($quickSurvey->selected_options),
                'other_response' => $quickSurvey->other_response,
            ]);
        }

        return response()->json(['answered' => false]);
    }
}
