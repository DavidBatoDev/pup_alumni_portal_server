<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Graduate; // Your model for graduates table
use App\Mail\AlumniSurveyEmail;

class AlumniEmailController extends Controller
{
    /**
     * Send emails to specific alumni based on their IDs.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEmailToAlumni(Request $request)
    {
        try {
            // Validate the request input
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:graduates,id', // IDs must exist in graduates table
            ]);

            // Fetch alumni based on the provided IDs
            $alumniList = Graduate::whereIn('id', $request->ids)->get();

            if ($alumniList->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No alumni found for the provided IDs.',
                ], 404);
            }

            foreach ($alumniList as $alumni) {
                // Send an email to each alumni
                Mail::to($alumni->email_address)->send(new AlumniSurveyEmail($alumni));
            }

            return response()->json([
                'success' => true,
                'message' => 'Emails sent successfully!',
                'emails_sent_to' => $alumniList->pluck('email_address'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
