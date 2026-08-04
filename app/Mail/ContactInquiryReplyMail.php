<?php

namespace App\Mail;

use App\Models\ContactInquiryReply;
use App\Support\MailSender;
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
        $siteName = $this->siteName !== '' ? $this->siteName : MailSender::name();

        return $this
            ->from(MailSender::address(), MailSender::name())
            ->subject($this->reply->subject)
            ->replyTo(MailSender::address(), $siteName)
            ->view('emails.contact-inquiry-reply', [
                'siteName' => $siteName,
                'recipientName' => $inquiry->name,
                'originalSubject' => $inquiry->subject,
                'originalMessage' => $inquiry->message,
                'replyBody' => $this->reply->body,
            ]);
    }
}
