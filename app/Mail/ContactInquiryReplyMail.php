<?php

namespace App\Mail;

use App\Models\ContactInquiryReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactInquiryReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactInquiryReply $reply,
        public string $siteName = 'ZBC News',
    ) {}

    public function build(): self
    {
        $inquiry = $this->reply->inquiry;

        return $this
            ->subject($this->reply->subject)
            ->replyTo(config('mail.from.address'), $this->siteName)
            ->view('emails.contact-inquiry-reply', [
                'siteName' => $this->siteName,
                'recipientName' => $inquiry->name,
                'originalSubject' => $inquiry->subject,
                'originalMessage' => $inquiry->message,
                'replyBody' => $this->reply->body,
            ]);
    }
}
