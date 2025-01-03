<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\AlumniEvent;
use App\Models\EventPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\EventCreated;
use App\Models\Notification;
use App\Models\Alumni;
use App\Models\AlumniNotification;
use App\Models\EventFeedback;
use App\Models\PostEventPhoto;
use App\Mail\EventRegistered;
use Illuminate\Support\Facades\Mail;
use App\Models\EventFeedbackPhoto;

class EventController extends Controller
{
    /**
     * Create a new event (Admin only).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
    */
    public function createEvent(Request $request)
    {
        // Validate the input data and the image files
        $validatedData = $request->validate([
            'event_name' => 'required|string|max:255',
            'event_date' => 'required|date',
            'location' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'organization' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'photos.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20048', // Validate multiple images
        ]);

        try {
            // Create the event
            $event = Event::create($validatedData);

            // Handle file uploads
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('event_photos', 'public'); // Store in the 'public' disk

                    // Save the photo path in the event_photos table
                    EventPhoto::create([
                        'event_id' => $event->event_id,
                        'photo_path' => $path,
                    ]);
                }
            }

            // Create a notification for the event
            $notification = Notification::create([
                'type' => 'eventInvitation',
                'alert' => 'New Event Created',
                'title' => $validatedData['event_name'],
                'message' => 'You are invited to the event: ' . $validatedData['event_name'] . ' on ' . $validatedData['event_date'] . ' at ' . $validatedData['location'],
                'link' => '/events/' . $event->event_id, // Link to the event details
            ]);

            // Fetch all alumni IDs
            $alumniIds = Alumni::pluck('alumni_id');

            // Attach the notification to all alumni
            foreach ($alumniIds as $alumniId) {
                AlumniNotification::create([
                    'alumni_id' => $alumniId,
                    'notification_id' => $notification->notification_id,
                    'is_read' => false,
                ]);
            }

            // broadcast(new EventCreated($notification, $event))->toOthers();

            return response()->json(['success' => true, 'event' => $event], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create event.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    
    /**
     * Update an existing event.
     *
     * @param Request $request
     * @param int $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEvent(Request $request, $eventId)
    {
        $validatedData = $request->validate([
            'event_name' => 'required|string|max:255',
            'event_date' => 'required|date',
            'location' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'organization' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'photos.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20048',
            'existing_photos' => 'nullable|array', // Photos to keep
            'photos_to_delete' => 'nullable|array', // Photos to delete
        ]);
    
        $event = Event::find($eventId);
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
    
        $event->update($validatedData);
    
        // Handle existing photos (deletion)
        if ($request->has('photos_to_delete')) {
            $photosToDelete = $request->input('photos_to_delete');
            foreach ($photosToDelete as $photoId) {
                $photo = EventPhoto::find($photoId);
                if ($photo) {
                    // Delete the photo from storage
                    \Storage::disk('public')->delete($photo->photo_path);
    
                    // Delete the record from the database
                    $photo->delete();
                }
            }
        }
    
        // Handle new photos if they are uploaded
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('event_photos', 'public');
    
                // Save new photo in the database
                EventPhoto::create([
                    'event_id' => $event->event_id,
                    'photo_path' => $path,
                ]);
            }
        }
    
        return response()->json(['success' => true, 'message' => 'Event updated successfully.', 'event' => $event], 200);
    }
    
    /**
     * Delete an existing event.
     *
     * @param int $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteEvent($eventId)
    {
        // Find the event by ID
        $event = Event::find($eventId);
    
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
    
        // Check if any alumni are registered for this event
        $registeredAlumniCount = $event->alumniEvents()->count();
    
        if ($registeredAlumniCount > 0) {
            return response()->json([
                'error' => 'Cannot delete the event. There are ' . $registeredAlumniCount . ' alumni registered for this event. Please remove or cancel their registrations before deleting the event.'
            ], 400);
        }
    
        // Delete the event if there are no registered alumni
        $event->delete();

        // delete all notifications for the event
        $notification = Notification::where('type', 'eventInvitation')
            ->where('link', '/events/' . $event->event_id)
            ->first();

        if ($notification) {
            $notification->delete();
        }

        // $photos = EventPhoto::where('event_id', $event->event_id)->get();
        // foreach ($photos as $photo) {
        //     \Storage::disk('public')->delete($photo->photo_path);
        //     $photo->delete();
        // }
    
        return response()->json(['success' => true, 'message' => 'Event deleted successfully.'], 200);
    }

        /**
     * End an event (Admin only).
     *
     * @param int $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function endEvent($eventId)
    {
        // Find the event by ID
        $event = Event::find($eventId);
    
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
    
        // Check if the event is already inactive
        if (!$event->is_active) {
            return response()->json(['error' => 'Event has already ended'], 400);
        }
    
        // Mark the event as ended (inactive)
        $event->is_active = false;
        $event->save();
    
        // Remove the notification for the new event created
        $invitationNotification = Notification::where('type', 'eventInvitation')
            ->where('link', '/events/' . $event->event_id)
            ->first();
    
        if ($invitationNotification) {
            $invitationNotification->delete();
        }
    
        // Notify alumni that the event has ended
        $notification = Notification::create([
            'type' => 'eventEnded',
            'alert' => 'Event Ended',
            'title' => $event->event_name,
            'message' => 'The event "' . $event->event_name . '" has ended.',
            'link' => '/events/' . $event->event_id, // Link to the event details
        ]);
    
        // Fetch all alumni IDs
        $alumniIds = Alumni::pluck('alumni_id');
    
        // Attach the notification to all alumni
        foreach ($alumniIds as $alumniId) {
            AlumniNotification::create([
                'alumni_id' => $alumniId,
                'notification_id' => $notification->notification_id,
                'is_read' => false,
            ]);
        }
    
        return response()->json(['success' => true, 'message' => 'Event ended successfully.'], 200);
    }

            /**
     * Unend event (Admin only).
     *
     * @param int $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function unendEvent($eventId)
    {
        // Find the event by ID
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        // Check if the event is already active
        if ($event->is_active) {
            return response()->json(['error' => 'Event is already active'], 400);
        }

        // Mark the event as active
        $event->is_active = true;
        $event->save();

        // remove the notification for the new event created
        $EndedNotifcation = Notification::where('type', 'eventEnded')
            ->where('link', '/events/' . $event->event_id)
            ->first();

        if ($EndedNotifcation) {
            $EndedNotifcation->delete();
        }

        // notify alumni that the event has ended
        $notification = Notification::create([
            'type' => 'eventInvitation',
            'alert' => 'Event Started',
            'title' => $event->event_name,
            'message' => 'The event "' . $event->event_name . '" has started.',
            'link' => '/events/' . $event->event_id, // Link to the event details
        ]);

        // Fetch all alumni IDs
        $alumniIds = Alumni::pluck('alumni_id');

        // Attach the notification to all alumni
        foreach ($alumniIds as $alumniId) {
            AlumniNotification::create([
                'alumni_id' => $alumniId,
                'notification_id' => $notification->notification_id,
                'is_read' => false,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Event unended successfully.'], 200);
    }


    /**
     * Submit feedback and upload photos for an event.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitFeedback(Request $request, $eventId)
    {
        // Validate request data
        $request->validate([
            'feedback_text' => 'required|string|max:1000',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
    
        // Find the event
        $event = Event::find($eventId);
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
    
        // Check if the event is still active
        if ($event->is_active) {  // Assuming is_active is a boolean or 1/0
            return response()->json(['error' => 'Event is still active. Feedback cannot be submitted.'], 403);
        }
    
        // Get the alumni_id from the authenticated user (JWT or session)
        $alumniId = auth()->user()->alumni_id;
    
        // Check if the alumni is registered for the event
        $isRegistered = AlumniEvent::where('event_id', $event->event_id)
            ->where('alumni_id', $alumniId)
            ->exists();
    
        if (!$isRegistered) {
            return response()->json(['error' => 'You are not registered for this event.'], 403);
        }
    
        // Store feedback
        $feedback = EventFeedback::create([
            'event_id' => $event->event_id,
            'alumni_id' => $alumniId,
            'feedback_text' => $request->input('feedback_text'),
        ]);
    
        // Handle photo uploads (if any)
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                // Store the photo in the public storage folder "event_photos"
                $photoPath = $photo->store('event_photos', 'public');
    
                // Create a new photo entry for the feedback
                EventFeedbackPhoto::create([
                    'event_feedback_id' => $feedback->event_feedback_id, // Link photo to the feedback
                    'photo_url' => $photoPath,
                ]);
            }
        }
    
        // Fetch the feedback with photos and alumni details
        $feedbackDetails = $feedback->load(['alumni', 'photos']);
    
        // Format feedback data, including alumni details and photo URLs
        $formattedFeedback = [
            'feedback_id' => $feedbackDetails->event_feedback_id,
            'feedback_text' => $feedbackDetails->feedback_text,
            'created_at' => $feedbackDetails->created_at,
            'alumni' => [
                'alumni_id' => $feedbackDetails->alumni->alumni_id,
                'first_name' => $feedbackDetails->alumni->first_name,
                'last_name' => $feedbackDetails->alumni->last_name,
                'email' => $feedbackDetails->alumni->email,
                'profile_picture' => $feedbackDetails->alumni->profile_picture
                    ? url('storage/' . $feedbackDetails->alumni->profile_picture) // Full URL to profile picture
                    : null, // If no profile picture, return null
            ],
            'photos' => $feedbackDetails->photos->map(function ($photo) {
                return [
                    'photo_id' => $photo->photo_id,
                    'photo_path' => url('storage/' . $photo->photo_url), // Full URL to the image
                ];
            }),
        ];
    
        // Return a successful response with the newly created feedback data
        return response()->json([
            'success' => true,
            'message' => 'Feedback submitted successfully.',
            'feedback' => $formattedFeedback,
        ]);
    }




    /**
     * Get all registered alumni for a specific event.
     *
     * @param int $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRegisteredAlumniForEvent($eventId)
    {
        // Check if the event exists
        $event = Event::find($eventId);
    
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
    
        // Get all alumni registered for the specific event along with their details
        $registeredAlumni = AlumniEvent::with(['alumni' => function ($query) {
                // Explicitly select the fields using the correct primary key
                $query->select('alumni_id', 'first_name', 'last_name', 'email', 'phone', 'degree', 'graduation_year');
            }])
            ->where('event_id', $eventId)
            ->get()
            ->map(function ($alumniEvent) {
                // Check if the alumni relationship is properly loaded before accessing its attributes
                if ($alumniEvent->alumni) {
                    return [
                        'alumni_id' => $alumniEvent->alumni->alumni_id,
                        'first_name' => $alumniEvent->alumni->first_name,
                        'last_name' => $alumniEvent->alumni->last_name,
                        'email' => $alumniEvent->alumni->email,
                        'phone' => $alumniEvent->alumni->phone,
                        'degree' => $alumniEvent->alumni->degree,
                        'graduation_year' => $alumniEvent->alumni->graduation_year,
                        'registration_date' => $alumniEvent->registration_date,
                    ];
                }
                return null;
            })->filter(); // Remove any null values in case of missing alumni records
    
        return response()->json(['success' => true, 'registered_alumni' => $registeredAlumni], 200);
    }

    /**
     * Register an alumni for a specific event.
     *
     * @param Request $request
     * @param int $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerAlumniToEvent(Request $request, $eventId)
    {
        try {
            $alumniId = Auth::id(); // Get the authenticated alumni ID
    
            // Check if the event exists
            $event = Event::find($eventId);
            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }
        
            // Check if the alumni is already registered for the event
            $existingRegistration = AlumniEvent::where('event_id', $eventId)
                ->where('alumni_id', $alumniId)
                ->first();
        
            if ($existingRegistration) {
                return response()->json(['error' => 'You are already registered for this event.'], 400);
            }
        
            // Register the alumni to the event
            $alumniEvent = AlumniEvent::create([
                'alumni_id' => $alumniId,
                'event_id' => $eventId,
                'registration_date' => now(),
            ]);
    
        
            // Mark the event notification as read
            $notification = Notification::where('type', 'eventInvitation')
                ->where('link', '/events/' . $eventId)
                ->first();
    
        
            if ($notification) {
                $alumniNotification = AlumniNotification::where('alumni_id', $alumniId)
                    ->where('notification_id', $notification->notification_id)
                    ->first();
        
                if ($alumniNotification) {
                    $alumniNotification->update(['is_read' => true]);
                }
            }
        
            // Send the event registration email to the alumni
            $alumni = Alumni::find($alumniId);
            Mail::to($alumni->email)->send(new EventRegistered($event));
        
            return response()->json(['success' => true, 'registration' => $alumniEvent], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the list of events.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEvents()
    {
        // Fetch all events with their associated photos
        $events = Event::with('photos')
            ->where('is_active', true)
            ->orderBy('event_date', 'desc')
            ->get();
    
        // Update photo_path to include the full URL
        $events->transform(function ($event) {
            $event->photos->transform(function ($photo) {
                // Prepend the storage URL to the photo_path
                $photo->photo_path = url('storage/' . $photo->photo_path);
                return $photo;
            });
            return $event;
        });
    
        return response()->json(['success' => true, 'events' => $events], 200);
    }

    public function getInactiveEvents()
    {
        try {
            // Fetch all events with their associated photos
            $events = Event::with(['photos', 'postEventPhotos'])
                ->where('is_active', false)
                ->orderBy('event_date', 'desc')
                ->get();
    
            if ($events->isEmpty()) {
                // Return empty response if no inactive events are found
                return response()->json(['success' => true, 'events' => $events], 200);
            }
    
            // Update photo_path to include the full URL for both photos and postEventPhotos
            $events->transform(function ($event) {
                // Transform regular photos
                $event->photos->transform(function ($photo) {
                    $photo->photo_path = url('storage/' . $photo->photo_path);
                    return $photo;
                });
    
                // Transform post event photos
                $event->postEventPhotos->transform(function ($postPhoto) {
                    $postPhoto->photo_path = url('storage/' . $postPhoto->photo_path);
                    return $postPhoto;
                });
    
                return $event;
            });
    
            return response()->json(['success' => true, 'events' => $events], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    


    /**
     * Get the details of a specific event.
     *
     * @param int $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEventDetails($eventId)
    {
        // Fetch the event details along with registered alumni and event photos
        $event = Event::with(['alumniEvents.alumni', 'photos'])->find($eventId);
    
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
    
        // Format the response
        $eventDetails = [
            'event_id' => $event->event_id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date,
            'location' => $event->location,
            'type' => $event->type,
            'category' => $event->category,
            'organization' => $event->organization,
            'description' => $event->description,
            'is_active' => $event->is_active,
            'photos' => $event->photos->map(function ($photo) {
                return [
                    'photo_id' => $photo->photo_id,
                    'photo_path' => url('storage/' . $photo->photo_path), // Return full URL to the image
                ];
            }),
            'post_event_photos' => $event->postEventPhotos->map(function ($postPhoto) {
                return [
                    'photo_id' => $postPhoto->photo_id,
                    'photo_path' => url('storage/' . $postPhoto->photo_path), // Return full URL to the image
                ];
            }),
            'registered_alumni' => $event->alumniEvents->map(function ($alumniEvent) {
                return [
                    'alumni_id' => $alumniEvent->alumni->alumni_id,
                    'first_name' => $alumniEvent->alumni->first_name,
                    'last_name' => $alumniEvent->alumni->last_name,
                    'email' => $alumniEvent->alumni->email,
                ];
            }),
        ];
    
        return response()->json(['success' => true, 'event' => $eventDetails], 200);
    }

    /**
     * Fetch all feedback for a specific event if the event is inactive.
     *
     * @param int $eventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEventFeedbacks($eventId)
    {
        // Fetch the event to check if it's inactive
        $event = Event::find($eventId);
    
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
    
        // Check if the event is not active
        if ($event->is_active) {
            return response()->json(['error' => 'Event is still active. Feedback cannot be fetched.'], 403);
        }
    
        // Fetch the feedback for the event, including alumni and related photos
        $feedbacks = EventFeedback::with(['alumni', 'photos'])
            ->where('event_id', $eventId)
            ->get();
    
        // If there are no feedbacks, return an empty response
        if ($feedbacks->isEmpty()) {
            return response()->json(['message' => 'No feedback available for this event.'], 200);
        }
    
        // Format feedback data including alumni and photos
        $formattedFeedbacks = $feedbacks->map(function ($feedback) {
            return [
                'feedback_id' => $feedback->event_feedback_id,
                'feedback_text' => $feedback->feedback_text,
                'created_at' => $feedback->created_at,
                'alumni' => [
                    'alumni_id' => $feedback->alumni->alumni_id,
                    'first_name' => $feedback->alumni->first_name,
                    'last_name' => $feedback->alumni->last_name,
                    'email' => $feedback->alumni->email,
                    'profile_picture' => $feedback->alumni->profile_picture
                        ? url('storage/' . $feedback->alumni->profile_picture) // Full URL to profile picture
                        : null, // If no profile picture, return null
                ],
                'photos' => $feedback->photos->map(function ($photo) {
                    return [
                        'feedback_event_photo_id' => $photo->feedback_event_photo_id,
                        'photo_url' => url('storage/' . $photo->photo_url), // Full URL to the image
                    ];
                }),
            ];
        });
    
        // Return the feedback data
        return response()->json(['success' => true, 'feedbacks' => $formattedFeedbacks], 200);
    }
    

    public function getEventDetailsWithStatus($eventId)
    {
        // Get the authenticated alumni from the token
        $alumni = auth()->user();
    
        // Check if the alumni is authenticated
        if (!$alumni) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    
        // Fetch the event details along with registered alumni
        $event = Event::with(['alumniEvents.alumni' => function ($query) {
                $query->select('alumni_id', 'first_name', 'last_name', 'email');
            }])
            ->find($eventId);
    
        // If the event is not found, return a 404 response
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
    
        // Check if the specific alumni is registered for the event
        $isRegistered = $event->alumniEvents->contains('alumni_id', $alumni->alumni_id);
    
        // Format the response to include alumni details
        $eventDetails = [
            'event_id' => $event->event_id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date,
            'location' => $event->location,
            'type' => $event->type, 
            'category' => $event->category, 
            'organization' => $event->organization, 
            'description' => $event->description,
            'is_active' => $event->is_active,
            'registered_alumni' => $event->alumniEvents->map(function ($alumniEvent) {
                return [
                    'alumni_id' => $alumniEvent->alumni->alumni_id,
                    'first_name' => $alumniEvent->alumni->first_name,
                    'last_name' => $alumniEvent->alumni->last_name,
                    'email' => $alumniEvent->alumni->email,
                ];
            }),
            'is_alumni_registered' => $isRegistered
        ];
    
        return response()->json(['success' => true, 'event' => $eventDetails], 200);
    }


}
