<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AttemptsReachedMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function build()
    {
        return $this->view('email', ['content' => 'You know, pools are perfect for holding water.']);
    }
}
