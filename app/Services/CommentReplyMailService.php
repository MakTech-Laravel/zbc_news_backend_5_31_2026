<?php

namespace App\Services;

use App\Models\ArticleComment;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\MailSender;
use Illuminate\Support\Facades\Mail;

class CommentReplyMailService
{
    public function send(ArticleComment $reply): void
    {
        $reply->loadMissing(['user', 'article', 'parent.user']);
        $parent = $reply->parent;

        if (! $parent?->user_id || (int) $parent->user_id === (int) $reply->user_id) {
            return;
        }

        $parentUser = User::query()->find($parent->user_id);
        if (! $parentUser || ! filled($parentUser->email)) {
            return;
        }

        if (! NotificationPreference::wants($parentUser->id, 'comment_replies')) {
            return;
        }

        $siteName = MailSender::name();
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $articleSlug = $reply->article?->slug;
        $articleUrl = $articleSlug
            ? $frontendUrl.'/'.ltrim((string) $articleSlug, '/')
            : $frontendUrl.'/';
        $replierName = $reply->authorName();
        $articleTitle = $reply->article?->title ?? 'an article';
        $excerpt = mb_substr(trim(strip_tags((string) $reply->body)), 0, 200);

        $subject = "{$replierName} replied to your comment on {$siteName}";
        $html = view('emails.comment-reply', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'recipientName' => $parentUser->name,
            'replierName' => $replierName,
            'articleTitle' => $articleTitle,
            'articleUrl' => $articleUrl,
            'replyExcerpt' => $excerpt,
        ])->render();

        Mail::html($html, function ($message) use ($parentUser, $subject, $siteName): void {
            $message->to((string) $parentUser->email, (string) $parentUser->name)
                ->subject($subject)
                ->from(MailSender::address(), $siteName);
        });
    }
}
