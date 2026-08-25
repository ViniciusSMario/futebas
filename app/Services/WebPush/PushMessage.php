<?php

namespace App\Services\WebPush;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The payload handed to the service worker's `push` event. Kept as a small
 * DTO so notifications describe *what* to show without knowing anything
 * about the encryption below it.
 *
 * @implements Arrayable<string, mixed>
 */
class PushMessage implements Arrayable
{
    private string $body = '';

    private ?string $url = null;

    private ?string $tag = null;

    private ?string $icon = '/images/icons/icon-192.png';

    private ?string $badge = '/images/icons/badge-72.png';

    /** @var array<int, array{action: string, title: string}> */
    private array $actions = [];

    private ?int $ttl = null;

    private bool $requireInteraction = false;

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /** Where the browser navigates when the notification is clicked. */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Notifications sharing a tag replace one another on the device, so a
     * player who misses three SOS alerts doesn't wake up to three banners
     * for the same match.
     */
    public function tag(string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function badge(?string $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    public function action(string $action, string $title): self
    {
        $this->actions[] = ['action' => $action, 'title' => $title];

        return $this;
    }

    /** Seconds the push service should keep retrying an offline device. */
    public function ttl(int $seconds): self
    {
        $this->ttl = $seconds;

        return $this;
    }

    /** Keep the banner on screen until the user acts on it. */
    public function requireInteraction(bool $requireInteraction = true): self
    {
        $this->requireInteraction = $requireInteraction;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function data(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function ttlSeconds(): ?int
    {
        return $this->ttl;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'actions' => $this->actions,
            'requireInteraction' => $this->requireInteraction,
            'data' => $this->data,
        ], fn ($value) => $value !== null && $value !== [] && $value !== false);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
