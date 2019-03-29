<?php

namespace App\Mail;

use App\User;
use App\Verification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordEmail extends Mailable
{
	use Queueable, SerializesModels;

    protected $user;
    protected $verification;

    public function __construct(User $user, Verification $verification)
    {
        $this->user = $user;
        $this->verification = $verification;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('reset_password_email')
        			->subject('パスワードリセット通知')
                    ->with([
                        'username' => $this->user->username,
                        'token' => $this->verification->verification_key
                    ]);
    }

}