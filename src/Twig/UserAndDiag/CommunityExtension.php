<?php

namespace App\Twig\UserAndDiag;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CommunityExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('social_embed', [$this, 'socialEmbed'], ['is_safe' => ['html']]),
            new TwigFilter('emojify', [$this, 'emojify'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Converts YouTube and Twitter/X URLs into embedded players/widgets.
     */
    public function socialEmbed(?string $text): string
    {
        if (!$text)
            return '';

        // YouTube: https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID
        $text = preg_replace(
            '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w\-]+)(?:&[^\s]*)?/i',
            '<div class="embed-responsive" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;margin:12px 0;">
                <iframe src="https://www.youtube.com/embed/$1" style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;" allowfullscreen loading="lazy"></iframe>
            </div>',
            $text
        );

        // Twitter/X: https://twitter.com/user/status/ID or https://x.com/user/status/ID
        $text = preg_replace(
            '/(?:https?:\/\/)?(?:www\.)?(?:twitter\.com|x\.com)\/([\w]+)\/status\/(\d+)\S*/i',
            '<blockquote class="twitter-tweet" data-theme="dark">
                <a href="https://twitter.com/$1/status/$2"></a>
            </blockquote>
            <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>',
            $text
        );

        return $text;
    }

    /**
     * Converts common text emoticons to emojis for display.
     */
    public function emojify(?string $text): string
    {
        if (!$text)
            return '';

        $map = [
            ':plant:' => '🌱',
            ':sun:' => '☀️',
            ':rain:' => '🌧️',
            ':bug:' => '🐛',
            ':leaf:' => '🍃',
            ':fruit:' => '🍎',
            ':tomato:' => '🍅',
            ':corn:' => '🌽',
            ':tractor:' => '🚜',
            ':check:' => '✅',
            ':warning:' => '⚠️',
            ':fire:' => '🔥',
            ':heart:' => '❤️',
            ':thumbsup:' => '👍',
            ':thumbsdown:' => '👎',
            ':star:' => '⭐',
            ':question:' => '❓',
            ':idea:' => '💡',
            ':trophy:' => '🏆',
            ':wave:' => '👋',
            ':clap:' => '👏',
            ':eyes:' => '👀',
            ':pray:' => '🙏',
            ':muscle:' => '💪',
            ':wheat:' => '🌾',
            ':olive:' => '🫒',
            ':droplet:' => '💧',
            ':bee:' => '🐝',
            ':ant:' => '🐜',
            ':mushroom:' => '🍄',
        ];

        return str_replace(array_keys($map), array_values($map), $text);
    }
}
