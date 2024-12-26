<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Graduate;

class AlumniSurveyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $alumni;

    /**
     * Create a new message instance.
     *
     * @param Graduate $alumni
     * @return void
     */
    public function __construct(Graduate $alumni)
    {
        $this->alumni = $alumni;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Invitation to Participate in Alumni Survey')
                    ->view('emails.alumni_survey') // Email blade view
                    ->with([
                        'firstname' => $this->alumni->firstname,
                    ]);
    }
}
